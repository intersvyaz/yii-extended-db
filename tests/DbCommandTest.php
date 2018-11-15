<?php

namespace Intersvyaz\ExtendedDb\tests;

use Intersvyaz\ExtendedDb\DbCommand as DbCommand;
use Yii;
use PDO;
use ReflectionMethod;

/**
 * @coversDefaultClass \Intersvyaz\ExtendedDb\DbCommand
 */
class DbCommandTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return DbCommand
     */
    protected function makeCommand()
    {
        return new DbCommand(Yii::app()->db, '');
    }

    /**
     * @covers ::simplifyParams
     */
    public function testSimplifyParams()
    {
        $params = [
            'simpleName' => 'simpleValue1',
            'arrayName' => [0, 1, 2, 3],
            'complexNameSimpleValue' => ['bind' => true, 'value' => 'simpleValue2'],
            'complexNameNoBind' => ['bind' => false],
            'complexNameArrayValue' => ['bind' => true, 'value' => [4, 5, 6, 7]],
        ];
        $simplifiedParams = [
            'simpleName' => 'simpleValue1',
            'arrayName_0' => 0,
            'arrayName_1' => 1,
            'arrayName_2' => 2,
            'arrayName_3' => 3,
            'complexNameSimpleValue' => 'simpleValue2',
            'complexNameArrayValue_0' => 4,
            'complexNameArrayValue_1' => 5,
            'complexNameArrayValue_2' => 6,
            'complexNameArrayValue_3' => 7,
        ];

        $cmd = $this->makeCommand();

        $method = new ReflectionMethod('\Intersvyaz\ExtendedDb\DbCommand', 'simplifyParams');
        $method->setAccessible(true);

        $this->assertEquals($simplifiedParams, $method->invoke($cmd, $params));
    }

    /**
     * @covers ::bindValues
     */
    public function testBindValues()
    {
        $query = "SELECT 1 FROM dual WHERE :foo = 'bar'";
        $values = ['foo' => 'bar'];

        $mock = $this->getMock('\Intersvyaz\ExtendedDb\DbCommand', ['simplifyParams'], [Yii::app()->db, $query]);

        $mock->expects($this->once())
            ->method('simplifyParams')
            ->will($this->returnArgument(0))
            ->with($values);

        $mock->bindValues($values);
    }

    public function testBindParams()
    {
        $params = [
            'simpleName' => 'simpleValue',
            'arrayName' => [4, 5, 6, 7],
            'complexNameNoBind' => ['bind' => false],
            'complexName' => ['bind' => true, 'value' => 'foo'],
            'complexNameNoType' => ['bind' => true, 'value' => 'foo', 'length' => 31337],
            'complexNameFull' => ['bind' => true, 'value' => 'foo', 'type' => PDO::PARAM_BOOL, 'length' => 31337],
            'complexNameArray' => ['bind' => true, 'value' => [7, 8, 9, 10]],
            'complexNameArrayFull' => [
                'bind' => true,
                'value' => [4, 5, 6, 7],
                'type' => PDO::PARAM_BOOL,
                'length' => 31337
            ],
        ];

        $mock = $this->getMock('\Intersvyaz\ExtendedDb\DbCommand', ['bindParam'], [Yii::app()->db, '']);

        $i = 0;
        foreach ($params as $key => $value) {
            if (is_array($value) && array_key_exists('bind', $value)) {
                if ($value['bind'] == true) {
                    $type = array_key_exists('type', $value) ? $value['type'] : null;
                    $length = array_key_exists('length', $value) ? $value['length'] : null;
                    if ($length && is_null($type) && !is_array($value['value'])) {
                        $type = $mock->getConnection()->getPdoType(gettype(($value['value'])));
                    }
                    if (!is_array($value['value'])) {
                        $mock->expects($this->at($i++))
                            ->method('bindParam')
                            ->with($key, $value['value'], $type, $length);
                    } else {
                        foreach ($value['value'] as $valKey => &$valVal) {
                            $mock->expects($this->at($i++))
                                ->method('bindParam')
                                ->with($key . '_' . $valKey, $valVal);
                        }
                    }
                }
            } elseif (is_array($value)) {
                foreach ($value as $valKey => $valVal) {
                    $mock->expects($this->at($i++))
                        ->method('bindParam')
                        ->with($key . '_' . $valKey, $valVal);
                }
            } else {
                $mock->expects($this->at($i++))
                    ->method('bindParam')
                    ->with($key, $value);
            }
        }

        $mock->bindParams($params);
    }

    /**
     * @covers ::prepareSql
     */
    public function testPrepareSqlClearExtraNewLines()
    {
        $cmd = $this->makeCommand();

        $method = new ReflectionMethod('\Intersvyaz\ExtendedDb\DbCommand', 'prepareSql');
        $method->setAccessible(true);

        $query = "\n\n\n\n\n";
        $params = ['foo' => 'bar'];

        $this->assertEquals("\n", $method->invokeArgs($cmd, [$query, &$params]));
    }

    /**
     * Данные для теста функции prepareSql
     * @return array
     */
    public function prepareSqlData()
    {
        $params = [
            'foo' => 'bar',
            'param1' => 1,
            'paRam2' => 2,
            'param3' => 3,
            'paraM_arr' => [1, 2],
        ];
        /* $query, $params, $returnQuery */
        return [
            ['/*param1 sql1 */', $params, ' sql1 '],
            ['/*Param2 sql2 */', $params, ' sql2 '],
            ['/*Param2 :paraM2 */', $params, ' :paraM2 '],
            ['/*PARAM1 sql3 --*not_param sql5 */', $params, ' sql3 '],
            ['/*ParaM1 --*param2 sql4 --*not_param sql10 */', $params, '  sql4 '],
            ['--*param1 sql5', $params, ' sql5'],
            [':@param1', $params, ':@param1'],
            ["--*param1 sql6\n :@param_arr", $params, " sql6\n :paraM_arr_0,:paraM_arr_1"],
            ["--*param1 sql7\n", $params, " sql7\n"],
            ['--*param1 sql8 --*not_param --*param2 sql9', $params, ' sql8 '],
        ];
    }

    /**
     * @covers ::prepareSql
     * @dataProvider prepareSqlData
     */
    public function testPrepareSql(
        $query,
        $params,
        $returnQuery
    ) {
        $cmd = $this->makeCommand();
        $method = new ReflectionMethod('\Intersvyaz\ExtendedDb\DbCommand', 'prepareSql');
        $method->setAccessible(true);

        $this->assertEquals($returnQuery,
            $method->invokeArgs($cmd,
                [$query, &$params]));
    }

    /**
     * Данные для теста функции replaceComment
     * @return array
     */
    public function replaceCommentData()
    {
        /* $query, $comment, $queryInComment, $paramName, $params, $returnQuery */
        return [
            // paramName not listed in params
            ['begin /*notInParams sql */ end', '/*notInParams sql */', ' sql ', 'notInParams', [], 'begin  end'],
            [
                'begin /*param1 sql */ end',
                '/*param1 sql */',
                ' sql ',
                'param1',
                ['param1' => 'foobar'],
                'begin  sql  end'
            ],
            [
                'begin /*param2 sql */ end',
                '/*param2 sql */',
                ' sql ',
                'param2',
                ['param2' => 'foobar'],
                'begin  sql  end'
            ],
            [
                'begin :@param3 end',
                ' :@param3 ',
                ' :@param3 ',
                'param3',
                ['param3' => [4, 5, 6]],
                'begin :param3_0,:param3_1,:param3_2 end',
                false
            ],
            [
                'begin :@Param3 end',
                ' :@Param3 ',
                ' :@Param3 ',
                'Param3',
                ['paRAm3' => [4, 5, 6]],
                'begin :paRAm3_0,:paRAm3_1,:paRAm3_2 end',
                false
            ],
            [
                'begin :@param3 end',
                ' :@param3 ',
                ' :@param3 ',
                'param3',
                ['param2' => [4, 5, 6]],
                'begin :@param3 end',
                false
            ],
            [
                'begin /*param3 :@param3 */ end',
                '/*param3 :@param3 */',
                ' :@param3 ',
                'param3',
                ['param3' => [4, 5, 6]],
                'begin  :param3_0,:param3_1,:param3_2  end'
            ],
            [
                'begin /*param4 :@param4 */ end',
                '/*param4 :@param4 */',
                ' :@param4 ',
                'param4',
                ['param4' => ['bind' => true, 'value' => [4, 5, 6]]],
                'begin  :param4_0,:param4_1,:param4_2  end'
            ],
            [
                'begin /*param5 count(*) */ end',
                '/*param5 count(*) */',
                ' count(*) ',
                'param5',
                ['param5' => ['bind' => false]],
                'begin  count(*)  end'
            ],
            [
                'begin /*OLOLO OLOLO */ end',
                '/*OLOLO OLOLO */',
                ' OLOLO ',
                'OLOLO',
                ['OLOLO' => ['bind' => 'text', 'value' => 'WOLOLO']],
                'begin  WOLOLO  end'
            ],
        ];
    }

    /**
     * @covers ::replaceComment
     * @dataProvider replaceCommentData
     */
    public function testReplaceComment(
        $query,
        $comment,
        $queryInComment,
        $paramName,
        $params,
        $returnQuery,
        $replaceNotFoundParam = true
    ) {
        $cmd = $this->makeCommand();
        $method = new ReflectionMethod('\Intersvyaz\ExtendedDb\DbCommand', 'replaceComment');
        $method->setAccessible(true);

        $this->assertEquals($returnQuery,
            $method->invokeArgs($cmd,
                [$query, $comment, $queryInComment, $paramName, &$params, $replaceNotFoundParam]));
    }

    /**
     * @expectedException \PHPUnit_Framework_Error
     */
    public function testFileGetNotExists()
    {
        $command = new DbCommand(Yii::app()->db, __DIR__ . '/fakes/file_not_exists.sql');
    }

    public function testFileGet()
    {
        $command = new DbCommand(Yii::app()->db, __DIR__ . '/fakes/list.sql');
        $this->assertEquals('select 1 from dual where 1=1 /*id AND id=:id*/', $command->getText());
    }
}
