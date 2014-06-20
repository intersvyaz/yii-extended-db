<?php

/**
 * DBConnection
 * Расширение стандартного класса соединения с БД.
 * Позволяет биндить и раскомментировать код в SQL запросах.
 */
class DbConnection extends CDbConnection
{
	/**
	 * @inheritdoc
	 */
	protected function open()
	{
		parent::open();
		$event = new CModelEvent($this);
		$this->onAfterOpen($event);

		return $event->isValid;
	}

	/**
	 * This event is raised after the connection open
	 * @param $event
	 */
	public function onAfterOpen($event)
	{
		$this->raiseEvent('onAfterOpen', $event);
	}

	/**
	 * @inheritdoc
	 */
	public function createCommand($sql = null, $params = null)
	{
		$this->setActive(true);

		return new DbCommand($this, $sql, $params);
	}
}
