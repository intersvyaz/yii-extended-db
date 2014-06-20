<?php
/**
 * DBConnection
 * Расширение стандартного класса соединения с БД.
 * Позволяет биндить и раскомментировать код в SQL запросах.
 * Также умеет устанавливать
 */
class DbConnection extends CDbConnection
{
	/**
	 * Установка переменных коннекта к базе. По умолчанию false.
	 * @var bool
	 */
	public $autoSetConnectIdent = false;

	/**
	 * Инициализация соединения, переопределяем стандартный метод чтоб добавить установку пользователя и скрипта
	 * от имени которого работает интерфейс
	 * @param $pdo the PDO instance
	 */
	protected function initConnection($pdo)
	{
		parent::initConnection($pdo);
		if ($this->autoSetConnectIdent) {
			$this->setConnectIdent();
		}
	}

	/**
	 * Creates a command for execution.
	 * @param null $sql текст запроса, или имя файла с запросом
	 * @param null $params параметры построения запроса (массив типа $arr[name_param]=array('bind'=>true/false/text, 'value'=>value)
	 * @throws CDbException
	 * @return DbCommand the DB command
	 */
	public function createCommand($sql = null, $params = null)
	{
		$this->setActive(true);

		return new DbCommand($this, $sql, $params);
	}

	/**
	 * Устанавливает переменные сессии/коннекта к базе данных.
	 * @param int $id идентификатор USER_ID пользователя, если не передан, то определяется автоматически
	 */
	public function setConnectIdent($id = null)
	{
		if ($this->getDriverName() == 'oci') {
			$url = $ip = $login = '';
			if (Yii::app() instanceof CWebApplication) {
				$url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown';
				$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
				$id = is_null($id) ? Yii::app()->getComponent('user')->getId() : $id;
			} elseif (Yii::app() instanceof CConsoleApplication) {
				$url = implode(' ', $_SERVER['argv']);
				$ip = '';
				if (is_null($id)) {
					$login = $this->username;
				}
			}

			$id = is_numeric($id) ? $id : null;

			$this->createCommand('
			BEGIN
				a_dba.env_pkg.set_client_info_prc (login_in => :login, user_id_in => :user_id, url_in => :url);
				a_dba.env_pkg.set_ip_addr_prc (ip_addr_in => :ip);
			END;')
				->execute(array(':login' => $login, ':user_id' => $id, ':url' => $url, ':ip' => $ip));
		}
	}
}
