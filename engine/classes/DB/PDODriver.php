<?php

namespace NG\DB;

use Exception;
use NG\Core\Container;
use NGErrorHandler;
use NGEvents;
use PDO;
use PDOException;

class PDODriver extends AbstractDriver
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
            throw new Exception('NG_PDO: Parameters lost for constructor');
        }

        // Init params
        if (isset($params['softErrors'])) {
            $this->softErrors = $params['softErrors'];
        }

        if (isset($params['errorSecurity'])) {
            $this->errorSecurity = $params['errorSecurity'];
        }

        if (!isset($params['user'])) {
            throw new Exception('NG_PDO: User is not specified');
        }

        if (!isset($params['pass'])) {
            throw new Exception('NG_PDO: Password is not specified');
        }

        if (!isset($params['host'])) {
            throw new Exception('NG_PDO: Host is not specified');
        }

        if (isset($params['eventLogger'])) {
            if (!($params['eventLogger'] instanceof NGEvents)) {
                throw new Exception('NGPDO: Passed eventLogger is not an instance of NGEvents class');
            }

            $this->eventLogger = $params['eventLogger'];
        } else {
            $this->eventLogger = Container::getInstance()->getEvents();
        }

        if (isset($params['errorHandler'])) {
            if (!($params['errorHandler'] instanceof NGErrorHandler)) {
                throw new Exception('NGPDO: Passed eventLogger is not an instance of NGErrorHandler class');
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
            $this->db = new PDO('mysql:host='.$params['host'].(isset($params['db']) ? ';dbname='.$params['db'] : ''), $params['user'], $params['pass']);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception('NG_PDO: Error connecting to DB ('.$e->getCode().') ['.$e->getMessage().']', $e->getCode());
        }

        // Try to switch CHARSET
        try {
            $this->db->exec("/*!40101 SET NAMES '".$this->dbCharset."' */");
        } catch (PDOException $e) {
            throw new Exception("NG_PDO: Error switching to charset '".$this->dbCharset."' (".$e->getCode().') ['.$e->getMessage().']');
        }

        $this->eventLogger->registerEvent('NG_PDO', '', '* DB Connection established', $this->eventLogger->tickStop($tStart));

        return true;
    }

    public function query($sql, $params = [])
    {
        $tStart = $this->eventLogger->tickStart();
        $this->qCount++;

        try {
            // Check if we should prepare
            if (is_array($params) && count($params)) {
                $st = $this->db->prepare($sql);
                $st->execute($params);
                $r = $st->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $r = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $this->errorReport('query', $sql, $e);
            $r = null;
        }
        $duration = $this->eventLogger->tickStop($tStart);
        $this->eventLogger->registerEvent('NG_PDO', 'QUERY', $sql, $duration);
        $this->qList[] = ['query' => $sql, 'duration' => $duration, 'start' => $tStart];

        return $r;
    }

    public function record($sql, $params = [])
    {
        $tStart = $this->eventLogger->tickStart();
        $this->qCount++;

        try {
            // Check if we should prepare
            if (is_array($params) && count($params)) {
                $st = $this->db->prepare($sql);
                $st->execute($params);
                $r = $st->fetch(PDO::FETCH_ASSOC);
                $st->closeCursor();
            } else {
                $r = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $this->errorReport('record', $sql, $e);
            $r = null;
        }
        $duration = $this->eventLogger->tickStop($tStart);
        $this->eventLogger->registerEvent('NG_PDO', 'RECORD', $sql, $duration);
        $this->qList[] = ['query' => $sql, 'duration' => $duration];

        return $r;
    }

    public function exec($sql, $params = [])
    {
        $tStart = $this->eventLogger->tickStart();
        $this->qCount++;

        $r = null;

        try {
            // Check if we should prepare
            if (is_array($params) && count($params)) {
                $st = $this->db->prepare($sql);
                $st->execute($params);
                $st->closeCursor();
            } else {
                $r = $this->db->query($sql);
            }
        } catch (PDOException $e) {
            $this->errorReport('exec', $sql, $e);
            $r = null;
        }
        $duration = $this->eventLogger->tickStop($tStart);
        $this->eventLogger->registerEvent('NG_PDO', 'EXEC', $sql, $duration);
        $this->qList[] = ['query' => $sql, 'duration' => $duration];

        return $r;
    }

    public function result($sql, $params = [])
    {
        $tStart = $this->eventLogger->tickStart();
        $this->qCount++;

        try {
            // Check if we should prepare
            if (is_array($params) && count($params)) {
                $st = $this->db->prepare($sql);
                $st->execute($params);
                $r = $st->fetch(PDO::FETCH_ASSOC);
                $st->closeCursor();
            } else {
                $r = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $this->errorReport('result', $sql, $e);
            $r = null;
        }
        $duration = $this->eventLogger->tickStop($tStart);
        $this->eventLogger->registerEvent('NG_PDO', 'RESULT', $sql, $duration);
        $this->qList[] = ['query' => $sql, 'duration' => $duration];

        if (is_array($r) && count($r)) {
            return $r[array_shift(array_keys($r))];
        }

        return null;
    }

    public function num_rows($st)
    {
        try {
            $r = $st->fetchColumn();
        } catch (PDOException $e) {
            $this->errorReport('num_rows', '', $e);
            $r = null;
        }

        return $r;
    }

    public function fetch_row($st)
    {
        try {
            $r = $st->fetch(PDO::FETCH_NUM);
        } catch (PDOException $e) {
            $this->errorReport('fetch_row', '', $e);
        }

        return $r;
    }

    public function lastid($table = '')
    {
        try {
            if (empty($table)) {
                return $id = $this->db->lastInsertId();
            } else {
                $r = $this->record('SHOW TABLE STATUS LIKE \''.prefix.'_'.$table.'\'');

                return $r['Auto_increment'] - 1;
            }
        } catch (PDOException $e) {
            $this->errorReport('lastid', '', $e);
        }
    }

    public function affected_rows($st)
    {
        try {
            return $id = $st->rowCount();
        } catch (PDOException $e) {
            $this->errorReport('affected_rows', '', $e);
        }
    }

    public function close($query)
    {
        try {
            if ($this->db != null) {
                $this->db = null;
            }
        } catch (PDOException $e) {
            $this->errorReport('close', '', $e);
        }
    }

    public function db_errno()
    {
        try {
            $this->db->errorInfo()[0];
        } catch (\PDOException $e) {
            $this->errorReport('db_errno', '', $e);
        }
    }

    /**
     * @param $string
     *
     * @return string
     */
    public function quote($string)
    {
        return mb_substr($this->db->quote($string), 1, -1);
    }

    /**
     * @return string
     */
    public function getEngineType()
    {
        return 'PDO';
    }

    /**
     * @return PDO Instance of PDO driver for low level access
     */
    public function getDriver()
    {
        return $this->db;
    }

    /**
     * @return version PDO
     */
    public function getVersion()
    {
        return $this->getDriver()->getAttribute(constant('PDO::ATTR_SERVER_VERSION'));
    }

    /**
     * // Report an SQL error.
     *
     * @param $type string Query type
     * @param $query string Query content
     * @param PDOException $e
     */
    public function errorReport($type, $query, PDOException $e)
    {
        $errNo = 'n/a';
        $errMsg = 'n/a';
        if (get_class($e) == 'PDOException') {
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
        $cursor = $this->db->prepare($query);
        if (is_array($params)) {
            foreach ($params as $key => $value) {
                $cursor->bindParam(':'.$key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
        }
        $cursor->execute();

        return $cursor;
    }

    /**
     * @param PDOStatement $cursor
     *
     * @return mixed
     */
    public function fetchCursor($cursor)
    {
        return $cursor->fetch(PDO::FETCH_ASSOC);
    }

    public function closeCursor($cursor)
    {
        return $cursor->closeCursor();
    }

    public function tableExists($name)
    {
        return is_array($this->record('show tables like :name', ['name' => $name])) ? true : false;
    }
}
