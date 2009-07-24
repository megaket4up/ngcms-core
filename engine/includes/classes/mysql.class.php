<?php

class mysql {

	function connect($host, $user, $pass, $db, $noerror = 0) {
		global $lang, $timer;

		$this->queries = 0;
		$this->query_list = array();
		$this->error = 0;
		$this->conn_db = $db;
		$this->queryTimer = (isset($timer) && (method_exists($timer, 'stop')));

		$this->connect = @mysql_connect($host, $user, $pass, true) or die('<h1>An Error Occurred</h1><hr />Unable to connect to the database!');
		@mysql_query("/*!40101 SET NAMES 'cp1251' */", $this->connect);
		if (!@mysql_select_db($db)) {
			if (!$noerror) {
				die('<h1>An Error Occurred</h1><hr />Unable to find the database <i>'.$db.'</i>!');
			}
			$this->error = 1;
		}
	}

	// Report an SQL error
	// $type	- query type
	// $query	- text of the query
	function errorReport($type, $query){
		print "<div style='font: 12px verdana; background-color: #EEEEEE; border: #ABCDEF 1px solid; margin: 1px; padding: 3px;'><span style='color: red;'>MySQL ERROR [".$type."]: ".$query."</span><br/><span style=\"font: 9px arial;\">(".mysql_errno($this->connect).'): '.mysql_error($this->connect).'</span></div>';
	}

	function select($sql, $assocMode = 0) {
	        global $timer;
	        if ($this->queryTimer) { $tX = $timer->stop(4); }

		$this->queries++;
		if (!($query = @mysql_query($sql, $this->connect))) {
			$this->errorReport('select', $sql);
			return array();
		}

		$result = array();

		switch ($assocMode) {
			case -1: $am = MYSQL_NUM; break;
			case  1: $am = MYSQL_ASSOC; break;
			case  0:
			default: $am = MYSQL_BOTH;
		}

		while($item = mysql_fetch_array($query, $am)) {
			$result[] = $item;
		}

		if ($this->queryTimer) { $tX = '[ '.($timer->stop(4) - $tX).' ] '; } else { $tX = ''; }
		array_push ($this->query_list, $tX.$sql);

		return $result;
	}

	function record($sql, $assocMode = 0) {
	        global $timer;
	        if ($this->queryTimer) { $tX = $timer->stop(4); }

		$this->queries++;
		if (!($query = @mysql_query($sql, $this->connect))) {
			$this->errorReport('record', $sql);
			return array();
		}
		switch ($assocMode) {
			case -1: $am = MYSQL_NUM; break;
			case  1: $am = MYSQL_ASSOC; break;
			case  0:
			default: $am = MYSQL_BOTH;
		}

		$item = mysql_fetch_array($query, $am);

		if ($this->queryTimer) { $tX = '[ '.($timer->stop(4) - $tX).' ] '; } else { $tX = ''; }
		array_push ($this->query_list, $tX.$sql);

		return $item;
	}

	function query($sql) {
		global $timer;

	    if ($this->queryTimer) { $tX = $timer->stop(4); }
		$this->queries++;
		if (!($query = @mysql_query($sql, $this->connect))) {
			$this->errorReport('query', $sql);
			return array();
		}

		if ($this->queryTimer) { $tX = '[ '.($timer->stop(4) - $tX).' ] '; } else { $tX = ''; }
		array_push ($this->query_list, $tX.$sql);

		return $query;
	}

	function result($sql) {
	        global $timer;
	        if ($this->queryTimer) { $tX = $timer->stop(4); }

		$this->queries++;
		if (!($query = @mysql_query($sql, $this->connect))) {
			$this->errorReport('result', $sql);
			return false;
		}


		if ($this->queryTimer) { $tX = '[ '.($timer->stop(4) - $tX).' ] '; } else { $tX = ''; }
		array_push ($this->query_list, $tX.$sql);

		if ($query) {
			return @mysql_result($query, 0);
		}
	}

	// check if table exists
	function table_exists($table) {

		if (is_array($this->table_list)) {
			return $this->table_list[$table]?1:0;
		}
		$list = mysql_list_tables($this->conn_db, $this->connect);
		if (!$list) { return 0; }

		$this->table_list=array();
		while ($row = mysql_fetch_row($list)) {
			$this->table_list[$row[0]]=1;
		}
		return $this->table_list[$table]?1:0;
	}

	function affected_rows() {
		return mysql_affected_rows($this->connect);
	}

	function qcnt() {
		return $this->queries;
	}

	function lastid($table) {

		$row = $this->record("SHOW TABLE STATUS LIKE '".prefix."_".$table."'");

		return ($row['Auto_increment'] - 1);
	}

	function close() {
		@mysql_close($this->connect);
	}
}