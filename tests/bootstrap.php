<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../vendor/yiisoft/yii/framework/yiit.php');

$config = array(
	'basePath' => __DIR__ . DIRECTORY_SEPARATOR . '..',
	'aliases' => [
		'fakes' => __DIR__ . '/fakes',
	],
	'components' => array(
		'db' => array(
			'class' => '\Intersvyaz\ExtendedDb\DbConnection',
			'connectionString' => 'sqlite:' . __DIR__ . DIRECTORY_SEPARATOR . 'test.db',
		),
	),
);

Yii::createConsoleApplication($config);

// fix Yii's autoloader (https://github.com/yiisoft/yii/issues/1907)
Yii::$enableIncludePath = false;
Yii::import('fakes.*');

// create DUAL table
$db = new PDO($config['components']['db']['connectionString']);
$db->exec('CREATE TABLE IF NOT EXISTS dual(a PRIMARY KEY)');
$db->exec('insert OR IGNORE into dual values(1)');
unset($db);
