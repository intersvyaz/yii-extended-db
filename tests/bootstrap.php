<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../vendor/yiisoft/yii/framework/yiit.php');

Yii::setPathOfAlias('extdb', realpath(__DIR__ . '/../'));
Yii::import('extdb.*');

$config = array(
	'basePath' => __DIR__ . DIRECTORY_SEPARATOR . '..',
	'components' => array(
		'db' => array(
			'class' => 'extdb.DbConnection',
			'connectionString' => 'sqlite:' . __DIR__ . '/test.db',
		),
	),
);

Yii::createConsoleApplication($config);

// create DUAL table
Yii::app()->db->createCommand('CREATE TABLE IF NOT EXISTS dual(a PRIMARY KEY);')->execute();
Yii::app()->db->createCommand('insert OR IGNORE into dual values(1)')->execute();
