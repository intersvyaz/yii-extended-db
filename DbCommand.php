<?php

namespace Intersvyaz\ExtendedDb;

/**
 * Класс команды базы данных, расширяет стандартный класс CDbCommand.
 *
 * Класс поддерживает параметры/значения для биндинга в двух формах:
 * <ul>
 * <li> Стандартноне представление:
 * <pre>array('param_name' => $value)</pre>
 * </li>
 * <li> Расширенное представление:
 * <pre>array('param_name' => array('bind' => true/false/'text', 'value' => $value, 'type' => $type, 'length' => $length))</pre>
 * </li>
 * </ul>
 * Вложенные многострочные комментарии не поддерживаются.
 * В случае 'OLOLO' => ['bind' => 'text'...] замена будет проводится только
 * в соответствующем закоментированном блоке и будет произведена замена, <pre>
 * select
 * --*OLOLO OLOLO
 * from dual
 * </pre>
 */
class DbCommand extends \CDbCommand
{
    /**
     * @param DbConnection $connection Соединение с базой.
     * @param mixed $query SQL statement or sql file path to be executed.
     * @param array $params Параметры построения запроса.
     */
    public function __construct(DbConnection $connection, $query = null, $params = null)
    {
        parent::__construct($connection, is_array($query) ? $query : []);
        if (!is_array($query)) {
            $this->setText($query, $params);
        }
    }

    /**
     * Specifies the SQL statement or sql file to be executed.
     * Any previous execution will be terminated or cancel.
     * @param string $value The SQL statement or sql file to be executed.
     * @param array $params Параметры построения запроса.
     * @return DbCommand Для цепочечных вызовов.
     */
    public function setText($value, &$params = null)
    {
        if (!empty($value)) {
            if (substr($value, -4) === '.sql') {
                $value = file_get_contents($value);
            }

            $value = $this->prepareSql($value, $params);
        }

        return parent::setText($value);
    }

    /**
     * Конвертирует параметры запроса из расширенного формата в key=>value массив.
     * @param array $params Параметры построения запроса.
     * @return array
     */
    protected function simplifyParams($params)
    {
        if (empty($params)) {
            return $params;
        }

        $newParams = array();
        foreach ($params as $key => $value) {
            if (is_array($value) && array_key_exists('bind', $value)) {
                if ($value['bind'] === true) {
                    if (!is_array($value['value'])) {
                        $newParams[$key] = $value['value'];
                    } else {
                        foreach ($value['value'] as $valKey => $valVal) {
                            $newParams[$key . '_' . $valKey] = $valVal;
                        }
                    }
                }
            } elseif (is_array($value)) {
                foreach ($value as $valKey => $valVal) {
                    $newParams[$key . '_' . $valKey] = $valVal;
                }
            } else {
                $newParams[$key] = $value;
            }
        }

        return $newParams;
    }

    /**
     * Функция разбора и подготовки текста sql запроса.
     * @param  string $query Запрос который нужно подготовить.
     * @param array $params Параметры построения запроса.
     * @return string Готовый текст sql запроса.
     */
    protected function prepareSql($query, &$params = null)
    {
        if (empty($params)) {
            return $query;
        }

        // Разбор многострочных комментариев
        if (preg_match_all('#/\*([\w|]+)(.+?)\*/#s', $query, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $query = $this->replaceComment($query, $matches[0][$i], $matches[2][$i], $matches[1][$i], $params);
            }
        }

        // Многоитерационный разбор однострочных комментариев
        while (true) {
            if (preg_match_all('#--\*([\w|]+)(.+)#', $query, $matches)) {
                $count = count($matches[0]);
                for ($i = 0; $i < $count; $i++) {
                    $query = $this->replaceComment($query, $matches[0][$i], $matches[2][$i], $matches[1][$i], $params);
                }
            } else {
                break;
            }
        }

        // разбор переменных - массивов, которые находились изначально вне комментариев
        if (preg_match_all('#:@(\w+)#', $query, $matches)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $query = $this->replaceComment($query, $matches[0][$i], $matches[0][$i], $matches[1][$i], $params, false);
            }
        }

        return preg_replace("/\n+/", "\n", $query);
    }

    /**
     * Заменяем коментарий и не только комментарий в запросе на соответствующе преобразованный блок или удаляем.
     * Используется также для замены параметра-массива - :@<param_name> не помещенного в комментарий, но только если такой
     * параметр есть в массиве параметров. Отдельную функцию делать не стали, потому что функционал одинаковый.
     * Либо можно переименовать функцию.
     * @param string $query Текст запроса.
     * @param string $comment Заменямый комментарий.
     * @param string $queryInComment Текст внутри комментария.
     * @param string $paramName Имя параметра.
     * @param array $params Параметры построения запроса.
     * @param boolean $replaceNotFoundParam заменять ли комментарий, если не нашли соответствующего параметра в списке
     * @return string Запрос с замененным комментирием.
     *
     * Приведение регистра ключей параметров можно было бы сделать и в prepareSql, но тогда ломаются тесты для replaceComment.
     * Надо будет тогда и тесты переделывать и гарантировать, чтобы там всегда приходили только правильные параметры.
     */
    protected function replaceComment($query, $comment, $queryInComment, $paramName, $params, $replaceNotFoundParam = true)
    {
        $paramNameLower = mb_strtolower($paramName);

        // Формируем имя параметра точно такое же, какое и забиндено в параметры.
        foreach ($params as $key => $value) {
            if (mb_strtolower($key) == $paramNameLower) {
                $paramName = $key;
                break;
            }
        }

        $params = array_change_key_case($params, CASE_LOWER);

        if (strpos($paramNameLower, '|')) {
            $found = false;

            foreach (explode('|', $paramNameLower) as $param) {
                if (array_key_exists($param, $params)) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $queryInComment = '';
            }
        } elseif (array_key_exists($paramNameLower, $params)) {
            $param = $params[$paramNameLower];
            $value = null;
            if (is_array($param) && array_key_exists('bind', $param)) {
                $bind = $param['bind'];
                if ($param['bind'] !== false) {
                    $value = $param['value'];
                }
            } else {
                $value = $param;
                $bind = true;
            }

            if ($bind === true && is_array($value)) {
                $valArr = [];
                foreach (array_keys($value) as $keyVal) {
                    $valArr[] = ':' . $paramName . '_' . $keyVal;
                }
                $replacement = implode(',', $valArr);
                $queryInComment = preg_replace('/:@' . preg_quote($paramName) . '/i', $replacement, $queryInComment);
            } elseif ($bind === 'text') {
                $queryInComment = preg_replace('/' . preg_quote($paramName) . '/i', $value, $queryInComment);
            }
        } elseif ($replaceNotFoundParam) {
            $queryInComment = '';
        }

        $query = str_replace($comment, $queryInComment, $query);

        return $query;
    }

    /**
     * Биндинг переменных в SQL запросе.
     * @param array $params Параметры построения запроса.
     * @return DbCommand Для цепочечных вызовов.
     */
    function bindParams(&$params)
    {
        if (empty($params)) {
            return $this;
        }

        $connect = $this->getConnection();

        foreach ($params as $key => &$value) {
            if (is_array($value) && array_key_exists('bind', $value)) {
                if ($value['bind'] == true) {
                    $type = array_key_exists('type', $value) ? $value['type'] : null;
                    $length = array_key_exists('length', $value) ? $value['length'] : null;
                    if ($length && is_null($type) && !is_array($value['value'])) {
                        $type = $connect->getPdoType(gettype(($value['value'])));
                    }
                    if (!is_array($value['value'])) {
                        $this->bindParam($key, $value['value'], $type, $length);
                    } else {
                        foreach ($value['value'] as $valKey => &$valVal) {
                            $this->bindParam($key . '_' . $valKey, $valVal, $type, $length);
                        }
                    }
                }
            } elseif (is_array($value)) {
                foreach ($value as $valKey => &$valVal) {
                    $this->bindParam($key . '_' . $valKey, $valVal);
                }
            } else {
                $this->bindParam($key, $value);
            }
        }

        return $this;
    }

    /**
     * Биндинг значений в SQL запросе.
     * @param array $values Параметры построения запроса.
     * @return DbCommand Для цепочечных вызовов.
     */
    public function bindValues($values)
    {
        $values = $this->simplifyParams($values);

        return parent::bindValues($values);
    }
}
