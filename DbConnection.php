<?php
namespace Intersvyaz\ExtendedDb;
/**
 * DBConnection
 * Расширение стандартного класса соединения с БД.
 * Позволяет биндить и раскомментировать код в SQL запросах.
 */
class DbConnection extends \CDbConnection
{
    /**
     * @var array mapping between PDO driver and schema class name.
     * A schema class can be specified using path alias.
     */
    public $driverMap=array(
        'cubrid'=>'CCubridSchema',  // CUBRID
        'pgsql'=> '\Intersvyaz\ExtendedDb\PgsqlSchema',   // PostgreSQL
        'mysqli'=>'CMysqlSchema',   // MySQL
        'mysql'=>'CMysqlSchema',    // MySQL,MariaDB
        'sqlite'=>'CSqliteSchema',  // sqlite 3
        'sqlite2'=>'CSqliteSchema', // sqlite 2
        'mssql'=>'CMssqlSchema',    // Mssql driver on windows hosts
        'dblib'=>'CMssqlSchema',    // dblib drivers on linux (and maybe others os) hosts
        'sqlsrv'=>'CMssqlSchema',   // Mssql
        'oci'=>'COciSchema',        // Oracle driver
    );

    /**
	 * @inheritdoc
	 */
	protected function open()
	{
		parent::open();

		if($this->hasEventHandler('onAfterOpen'))
			$this->onAfterOpen(new \CEvent($this));
	}

	/**
	 * This event is raised after the connection open
	 * @param \CEvent $event
	 */
	public function onAfterOpen(\CEvent $event)
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
