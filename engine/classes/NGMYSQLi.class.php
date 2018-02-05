<?php

class NGMYSQLi extends NGDB {

    protected $db               = null;
    protected $qCount           = 0;
    protected $qList            = array();
    protected $softErrors       = false;
    protected $errorSecurity    = 0;
    protected $eventLogger      = null;
    protected $errorHandler     = null;
    protected $dbCharset        = 'UTF8';

    function __construct($params) {
        if (!is_array($params)) {
            throw new Exception('NG_MySQLi: Parameters lost for constructor');
        }

        // Init params
        if (isset($params['softErrors']))
            $this->softErrors = $params['softErrors'];

        if (isset($params['errorSecurity']))
            $this->errorSecurity = $params['errorSecurity'];

        if (!isset($params['user']))
            throw new Exception('NG_MySQLi: User is not specified');

        if (!isset($params['pass']))
            throw new Exception('NG_MySQLi: Password is not specified');

        if (!isset($params['host']))
            throw new Exception('NG_MySQLi: Host is not specified');

        if (isset($params['eventLogger'])) {
            if (!($params['eventLogger'] instanceof NGEvents))
                throw new Exception('NGMySQLi: Passed eventLogger is not an instance of NGEvents class');

            $this->eventLogger = $params['eventLogger'];
        } else {
            $this->eventLogger  = NGEngine::getInstance()->getEvents();
        }

        if (isset($params['errorHandler'])) {
            if (!($params['errorHandler'] instanceof NGErrorHandler))
                throw new Exception('NGMySQLi: Passed eventLogger is not an instance of NGErrorHandler class');

            $this->errorHandler = $params['errorHandler'];
        } else {
            $this->errorHandler = NGEngine::getInstance()->getErrorHandler();
        }

        if (isset($params['charset']))
            $this->dbCharset = $params['charset'];


        // Mark start of DB connection procedure
        $tStart = $this->eventLogger->tickStart();

        try {
			$this->db = mysqli_connect($params['host'], $params['user'], $params['pass'], $params['db']);
        } catch(Exception $e) {
            throw new Exception('NGMYSQLi: Error connecting to DB ('.$e->getCode().") [".$e->getMessage()."]");
        }

        // Try to switch CHARSET
        try {
			mysqli_query($this->db, "/*!40101 SET NAMES '".$this->dbCharset."' */");
        } catch (Exception $e) {
            throw new Exception("NGMYSQLi: Error switching to charset '".$this->dbCharset."' (".$e->getCode().") [".$e->getMessage()."]");
        }

        $this->eventLogger->registerEvent('NGMYSQLi', '', '* DB Connection established', $this->eventLogger->tickStop($tStart));
        return true;
    }

    function query($sql, $params = array()) {

        $tStart = $this->eventLogger->tickStart();
        $this->qCount++;

        try {
			$query = @mysqli_query($this->db, $sql);
			
			$r = array();
			while ($item = mysqli_fetch_array($query)) {
				$r[] = $item;
			}
			
        } catch (Exception $e) {
            $this->errorReport('query', $sql, $e);
            $r = null;
        }
        $duration = $this->eventLogger->tickStop($tStart);
        $this->eventLogger->registerEvent('NG_MySQLi', 'QUERY', $sql, $duration);
        $this->qList []= array('query' => $sql, 'duration' => $duration, 'start' => $tStart);

        return $r;
    }

    function record($sql, $params = array()) {
        $tStart = $this->eventLogger->tickStart();
        $this->qCount++;

        try {
            $query = mysqli_query($this->db, $sql);
			
			$r = mysqli_fetch_array($query, MYSQLI_BOTH);
        } catch (Exception $e) {
            $this->errorReport('record', $sql, $e);
            $r = null;
        }
        $duration = $this->eventLogger->tickStop($tStart);
        $this->eventLogger->registerEvent('NG_MySQLi', 'RECORD', $sql, $duration);
        $this->qList []= array('query' => $sql, 'duration' => $duration);

        return $r;
    }


    function exec($sql, $params = array()) {
        $tStart = $this->eventLogger->tickStart();
        $this->qCount++;

        $r = null;
        try {
			$split = explode(" ", $sql);
			if(mb_strtolower(trim($split['0'])) == 'use' ){
				mysqli_select_db($this->db, $split['1']);
			} else {
				$r = @mysqli_query($this->db, $sql);
			}
        } catch (Exception $e) {
            $this->errorReport('exec', $sql, $e);
            $r = null;
        }
        $duration = $this->eventLogger->tickStop($tStart);
        $this->eventLogger->registerEvent('NG_MySQLi', 'EXEC', $sql, $duration);
        $this->qList []= array('query' => $sql, 'duration' => $duration);

        return $r;
    }

    function result($sql, $params = array()) {

        $tStart = $this->eventLogger->tickStart();
        $this->qCount++;

        try {
            $query = @mysqli_query($this->db, $sql);
			$r = $this->mysqli_result($query, 0);
        } catch (Exception $e) {
            $this->errorReport('result', $sql, $e);
            $r = null;
        }
        $duration = $this->eventLogger->tickStop($tStart);
        $this->eventLogger->registerEvent('NG_MySQLi', 'RESULT', $sql, $duration);
        $this->qList []= array('query' => $sql, 'duration' => $duration);

        if (count($r)) {
            return $r[array_shift(array_keys($r))];
        }
        return null;
    }

    /**
     * @param $string
     * @return string
     */
    function quote($string)  {
		return mysqli_real_escape_string($this->db, $string);
    }


    /**
     * @return string
     */
    function getEngineType() {
        return 'MySQLi';
    }

    /**
     * @return MySQLi Instance of MySQLi driver for low level access
     */
    function getDriver() {
        return $this->db;
    }

		/**
     * @return version MySQLi
     */
	function getVersion() {
        return mysqli_get_server_info($this->db);
    }
	
    /**
    // Report an SQL error

     * @param $type string Query type
     * @param $query string Query content
     * @param Exception $e
     */
    function errorReport($type, $query, Exception $e) {
        $errNo = 'n/a';
        $errMsg = 'n/a';
        if (get_class($e) == 'Exception') {
            $errNo = $e->getCode();
            $errMsg = $e->getMessage();
        }
        $this->errorHandler->throwError('SQL', array('errNo' => $errNo, 'errMsg' => $errMsg, 'type' => $type, 'query' => $query), $e);
	}

    function getQueryCount() {
        return $this->qCount;
    }

    function getQueryList() {
        return $this->qList;
    }

    function tableExists($name) {
        // Check if data are already saved
		if (getIsSet($this->table_list[$table]) && is_array($this->table_list)) {
			return $this->table_list[$table] ? 1 : 0;
		}

		try {
            $query = @mysqli_query($this->db, "show tables");

			while ($item = mysqli_fetch_array($query, MYSQLI_NUM)) {
				$this->table_list[$item[0]] = 1;
			}
        } catch (Exception $e) {
            $this->errorReport('query', $sql, $e);
            $r = null;
        }
		
		return $this->table_list[$table] ? 1 : 0;
    }
	
	function mysqli_result($result, $row, $field = 0) {

		$result->data_seek($row);
		$datarow = $result->fetch_array();

		return $datarow[$field];
	}
	
	// Cursor based operations
    /**
     * @param $query
     * @param array $params
     * @return PDOStatement
     */
    function createCursor($query, array $params = array()) {
        
    }

    /**
     * @param PDOStatement $cursor
     * @return mixed
     */
    function fetchCursor(PDOStatement $cursor) {
        
    }

    function closeCursor(PDOStatement $cursor) {
        
    }
}


