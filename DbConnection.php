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

		if($this->hasEventHandler('onAfterOpen'))
			$this->onAfterOpen(new CEvent($this));
	}

	/**
	 * This event is raised after the connection open
	 * @param CEvent $event
	 */
	public function onAfterOpen(CEvent $event)
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
