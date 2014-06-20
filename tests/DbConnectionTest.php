<?php

class DbConnectionTest extends PHPUnit_Framework_TestCase
{
	protected $afterOpened;

	public function testOpen()
	{
		Yii::app()->db->setActive(false);
		Yii::app()->db->onAfterOpen = function () {
			$this->afterOpened = true;
		};

		Yii::app()->db->setActive(true);
		$this->assertTrue($this->afterOpened);
	}

	public function testCreateCommand()
	{
		$command = Yii::app()->db->createCommand();
		$this->assertInstanceOf('DbCommand', $command);
	}
}
