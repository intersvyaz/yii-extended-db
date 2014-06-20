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
			'complexNameArrayFull' => ['bind' => true, 'value' => [4, 5, 6, 7], 'type' => PDO::PARAM_BOOL, 'length' => 31337],
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
						foreach ($value['value'] as $valKey => &$valVal)
							$mock->expects($this->at($i++))
								->method('bindParam')
								->with($key . '_' . $valKey, $valVal);
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
	 * @covers ::prepareSql
	 */
	public function testPrepareSql()
	{
		$mock = $this->getMock('\Intersvyaz\ExtendedDb\DbCommand', ['replaceComment'], [Yii::app()->db, '']);
		$method = new ReflectionMethod('\Intersvyaz\ExtendedDb\DbCommand', 'prepareSql');
		$method->setAccessible(true);

		$params = ['foo' => 'bar'];

		$query = '
			/*param1 sql1 */
			/*param2 sql2 */
			--*param3 sql3
			--*param6 --*param7 sql7
			/*param4 --*param5 sql5 */
			/*param8 --*param9 --*param10 sql10 */
		';
		$expectedArgs = [
			['/*param1 sql1 */', 'param1', ' sql1 '],
			['/*param2 sql2 */', 'param2', ' sql2 '],
			['/*param4 --*param5 sql5 */', 'param4', ' --*param5 sql5 '],
			['/*param8 --*param9 --*param10 sql10 */', 'param8', ' --*param9 --*param10 sql10 '],
			['--*param3 sql3', 'param3', ' sql3'],
			['--*param6 --*param7 sql7', 'param6', ' --*param7 sql7'],
			['--*param5 sql5 ', 'param5', ' sql5 '],
			['--*param9 --*param10 sql10 ', 'param9', ' --*param10 sql10 '],
			['--*param7 sql7', 'param7', ' sql7'],
			['--*param10 sql10 ', 'param10', ' sql10 '],
		];

		$i = 0;
		foreach ($expectedArgs as $args) {
			$mock->expects($this->at($i++))
				->method('replaceComment')
				->will($this->returnCallback(function ($q, $c, $cq, $pn, $ps) {
					return str_replace($c, $cq, $q);
				}))
				->with($this->anything(), $args[0], $args[2], $args[1], $params);
		}

		$method->invokeArgs($mock, [$query, &$params]);
	}

	public function replaceCommentData()
	{
		/* $query, $comment, $queryInComment, $paramName, $params, $returnQuery */
		return [
			// paramName not listed in params
			['begin /*notInParams sql */ end', '/*notInParams sql */', ' sql ', 'notInParams', [], 'begin  end'],
			['begin /*param1 sql */ end', '/*param1 sql */', ' sql ', 'param1', ['param1' => 'foobar'], 'begin  sql  end'],
			['begin /*param2 sql */ end', '/*param2 sql */', ' sql ', 'param2', ['param2' => 'foobar'], 'begin  sql  end'],
			['begin /*param3 :@param */ end', '/*param3 :@param */', ' :@param3 ', 'param3', ['param3' => [4, 5, 6]], 'begin  :param3_0,:param3_1,:param3_2  end'],
			['begin /*param4 :@param */ end', '/*param4 :@param */', ' :@param4 ', 'param4', ['param4' => ['bind' => true, 'value' => [4, 5, 6]]], 'begin  :param4_0,:param4_1,:param4_2  end'],
			['begin /*param5 count(*) */ end', '/*param5 count(*) */', ' count(*) ', 'param5', ['param5' => ['bind' => false]], 'begin  count(*)  end'],
			['begin /*OLOLO OLOLO */ end', '/*OLOLO OLOLO */', ' OLOLO ', 'OLOLO', ['OLOLO' => ['bind' => 'text', 'value' => 'WOLOLO']], 'begin  WOLOLO  end'],
		];
	}

	/**
	 * @covers ::replaceComment
	 * @dataProvider replaceCommentData
	 */
	public function testReplaceComment($query, $comment, $queryInComment, $paramName, $params, $returnQuery)
	{
		$cmd = $this->makeCommand();
		$method = new ReflectionMethod('\Intersvyaz\ExtendedDb\DbCommand', 'replaceComment');
		$method->setAccessible(true);

		$this->assertEquals($returnQuery, $method->invokeArgs($cmd, [$query, $comment, $queryInComment, $paramName, &$params]));
	}
}
