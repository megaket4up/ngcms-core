<?php

namespace NG\DB;

use Exception;
use mysqli_sql_exception;
use NGEvents;
use NGErrorHandler;
use NG\Core\Container;

class MYSQLiDriver extends AbstractDriver
{
    protected $db = null;
    protected $qCount = 0;
    protected $qList = [];
    protected $softErrors = false;
    protected $errorSecurity = 0;
    protected $eventLogger = null;
    protected $errorHandler = null;
    protected $dbCharset = 'UTF8';

    public function __construct($params)
    {
        if (!is_array($params)) {
            throw new Exception('NG_MySQLi: Parameters lost for constructor');
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        // Init params
        if (isset($params['softErrors'])) {
            $this->softErrors = $params['softErrors'];
        }

        if (isset($params['errorSecurity'])) {
            $this->errorSecurity = $params['errorSecurity'];
        }

        if (!isset($params['user'])) {
            throw new Exception('NG_MySQLi: User is not specified');
        }

        if (!isset($params['pass'])) {
            throw new Exception('NG_MySQLi: Password is not specified');
        }

        if (!isset($params['host'])) {
            throw new Exception('NG_MySQLi: Host is not specified');
        }

        if (isset($params['eventLogger'])) {
            if (!($params['eventLogger'] instanceof NGEvents)) {
                throw new Exception('NGMySQLi: Passed eventLogger is not an instance of NGEvents class');
            }

            $this->eventLogger = $params['eventLogger'];
        } else {
            $this->eventLogger = Container::getInstance()->getEvents();
        }

        if (isset($params['errorHandler'])) {
            if (!($params['errorHandler'] instanceof NGErrorHandler)) {
                throw new Exception('NGMySQLi: Passed eventLogger is not an instance of NGErrorHandler class');
            }

            $this->errorHandler = $params['errorHandler'];
        } else {
            $this->errorHandler = Container::getInstance()->getErrorHandler();
        }

        if (isset($params['charset'])) {
            $this->dbCharset = $params['charset'];
        }

        // Mark start of DB connection procedure
        $tStart = $this->eventLogger->tickStart();

        try {
            $this->db = mysqli_connect($params['host'], $params['user'], $params['pass'], $params['db']);
        } catch (Exception $e) {
            throw new Exception('NGMYSQLi: Error connecting to DB ('.$e->getCode().') ['.$e->getMessage().']', $e->getCode());
        }

        // Try to switch CHARSET
        try {
            mysqli_query($this->db, "/*!40101 SET NAMES '".$this->dbCharset."' */");
        } catch (Exception $e) {
            throw new Exception("NGMYSQLi: Error switching to charset '".$this->dbCharset."' (".$e->getCode().') ['.$e->getMessage().']');
        }

        $this->eventLogger->registerEvent('NGMYSQLi', '', '* DB Connection established', $this->eventLogger->tickStop($tStart));

        return true;
    }

    public function query($sql, $params = [])
    {
        $tStart = $this->eventLogger->tickStart();
        $this->qCount++;

        try {
            if (is_array($params)) {
                foreach ($params as $key => $value) {
                    $sql = str_replace(':'.$key, is_int($value) ? $value : '\''.$value.'\'', $sql);
                }
            }

            $query = mysqli_query($this->db, $sql);

            $r = [];
            while ($item = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
                $r[] = $item;
            }
        } catch (mysqli_sql_exception $e) {
            $this->errorReport('query', $sql, $e);
            $r = null;
        }
        $duration = $this->eventLogger->tickStop($tStart);
        $this->eventLogger->registerEvent('NG_MySQLi', 'QUERY', $sql, $duration);
        $this->qList[] = ['query' => $sql, 'duration' => $duration, 'start' => $tStart];

        return $r;
    }

    public function record($sql, $params = [])
    {
        $tStart = $this->eventLogger->tickStart();
        $this->qCount++;

        try {
            $query = mysqli_query($this->db, $sql);

            $r = mysqli_fetch_array($query, MYSQLI_ASSOC);
        } catch (mysqli_sql_exception $e) {
            $this->errorReport('record', $sql, $e);
            $r = null;
        }
        $duration = $this->eventLogger->tickStop($tStart);
        $this->eventLogger->registerEvent('NG_MySQLi', 'RECORD', $sql, $duration);
        $this->qList[] = ['query' => $sql, 'duration' => $duration];

        return $r;
    }

    public function exec($sql, $params = [])
    {
        $tStart = $this->eventLogger->tickStart();
        $this->qCount++;

        $r = null;

        try {
            $split = explode(' ', $sql);
            if (mb_strtolower(trim($split['0'])) == 'use') {
                if (mysqli_select_db($this->db, $split['1'])) {
                    $r = true;
                } else {
                    $r = null;
                }
            } else {
                if (is_array($params)) {
                    foreach ($params as $key => $value) {
                        $sql = str_replace(':'.$key, is_int($value) ? $value : '\''.$value.'\'', $sql);
                    }
                }

                $r = mysqli_query($this->db, $sql);
            }
        } catch (mysqli_sql_exception $e) {
            $this->errorReport('exec', $sql, $e);
            $r = null;
        }
        $duration = $this->eventLogger->tickStop($tStart);
        $this->eventLogger->registerEvent('NG_MySQLi', 'EXEC', $sql, $duration);
        $this->qList[] = ['query' => $sql, 'duration' => $duration];

        return $r;
    }

    public function result($sql, $params = [])
    {
        $tStart = $this->eventLogger->tickStart();
        $this->qCount++;

        try {
            $query = mysqli_query($this->db, $sql);
            //$r = $this->mysqli_result($query, 0);
            $r = mysqli_fetch_array($query, MYSQLI_ASSOC);
        } catch (mysqli_sql_exception $e) {
            $this->errorReport('result', $sql, $e);
            $r = null;
        }
        $duration = $this->eventLogger->tickStop($tStart);
        $this->eventLogger->registerEvent('NG_MySQLi', 'RESULT', $sql, $duration);
        $this->qList[] = ['query' => $sql, 'duration' => $duration];

        if (is_array($r) && count($r)) {
            return $r[array_shift(array_keys($r))];
        }

        return null;
    }

    public function num_rows($query)
    {
        try {
            return mysqli_num_rows($query);
        } catch (mysqli_sql_exception $e) {
            $this->errorReport('num_rows', $query, $e);
        }
    }

    public function fetch_row($query)
    {
        try {
            return mysqli_fetch_row($query);
        } catch (mysqli_sql_exception $e) {
            $this->errorReport('fetch_row', $query, $e);
        }
    }

    public function lastid($table = '')
    {
        try {
            if (empty($table)) {
                return mysqli_insert_id($this->db);
            } else {
                $r = $this->record('SHOW TABLE STATUS LIKE \''.prefix.'_'.$table.'\'');

                return $r['Auto_increment'] - 1;
            }
        } catch (mysqli_sql_exception $e) {
            $this->errorReport('lastid', '', $e);
        }
    }

    public function affected_rows()
    {
        try {
            return mysqli_affected_rows($this->db);
        } catch (mysqli_sql_exception $e) {
            $this->errorReport('affected_rows', 'mysqli_affected_rows', $e);
        }
    }

    public function close()
    {
        try {
            mysqli_close($this->db);
        } catch (mysqli_sql_exception $e) {
            $this->errorReport('close', '', $e);
        }
    }

    public function db_errno()
    {
        try {
            return mysqli_errno($this->db);
        } catch (mysqli_sql_exception $e) {
            $this->errorReport('db_errno', '', $e);
        }
    }

    public function mysqli_result($result, $row, $field = 0)
    {
        $result->data_seek($row);
        $datarow = $result->fetch_array();

        return $datarow[$field];
    }

    /**
     * @param $string
     *
     * @return string
     */
    public function quote($string)
    {
        return mysqli_real_escape_string($this->db, $string);
    }

    /**
     * @return string
     */
    public function getEngineType()
    {
        return 'MySQLi';
    }

    /**
     * @return MySQLi Instance of MySQLi driver for low level access
     */
    public function getDriver()
    {
        return $this->db;
    }

    /**
     * @return version MySQLi
     */
    public function getVersion()
    {
        return mysqli_get_server_info($this->db);
    }

    /**
     * // Report an SQL error.
     *
     * @param $type string Query type
     * @param $query string Query content
     * @param Exception $e
     */
    public function errorReport($type, $query, $e)
    {
        $errNo = 'n/a';
        $errMsg = 'n/a';
        if (get_class($e) == 'mysqli_sql_exception') {
            $errNo = $e->getCode();
            $errMsg = $e->getMessage();
        }
        $this->errorHandler->throwError('SQL', ['errNo' => $errNo, 'errMsg' => $errMsg, 'type' => $type, 'query' => $query], $e);
    }

    public function getQueryCount()
    {
        return $this->qCount;
    }

    public function getQueryList()
    {
        return $this->qList;
    }

    // Cursor based operations

    /**
     * @param $query
     * @param array $params
     *
     * @return PDOStatement
     */
    public function createCursor($query, array $params = [])
    {
        if (is_array($params)) {
            foreach ($params as $key => $value) {
                $query = str_replace(':'.$key, is_int($value) ? $value : '\''.$value.'\'', $query);
            }
        }

        return mysqli_query($this->db, $query);
    }

    /**
     * @param PDOStatement $cursor
     *
     * @return mixed
     */
    public function fetchCursor($cursor)
    {
        return mysqli_fetch_assoc($cursor);
    }

    public function closeCursor($cursor)
    {
    }

    public function tableExists($name)
    {
        return is_array($this->record('show tables like \''.$name.'\'')) ? true : false;
    }
}
