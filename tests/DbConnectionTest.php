<?php
namespace Intersvyaz\ExtendedDb\tests;

use Yii;
/**
 * @coversDefaultClass \Intersvyaz\ExtendedDb\DbConnection
 */
class DbConnectionTest extends \PHPUnit_Framework_TestCase
{
	protected $afterOpened;

	public function testOpen()
	{
		Yii::app()->db->setActive(false);
		Yii::app()->db->attachEventHandler('onAfterOpen', [$this, 'eventHandler']);
		Yii::app()->db->setActive(true);
		$this->assertTrue($this->afterOpened);

		$this->afterOpened = false;
		Yii::app()->db->detachEventHandler('onAfterOpen', [$this, 'eventHandler']);
		Yii::app()->db->setActive(false);
		Yii::app()->db->setActive(true);
		$this->assertFalse($this->afterOpened);
	}

	public function eventHandler()
	{
		$this->afterOpened = true;
	}

	public function testCreateCommand()
	{
		$command = Yii::app()->db->createCommand();
		$this->assertInstanceOf('Intersvyaz\ExtendedDb\DbCommand', $command);
	}
}
