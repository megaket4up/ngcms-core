<?php

namespace NG\DB;

use NG\Core\Container;

//
// Implements LEGACY DB backward compatibility
class NGLegacyDB
{
    // StandAlone mode:
    // - true   :: Creates independent connection to DB
    // - false  :: Reuses current connection to DB
    protected $isStandalone = true;
    /**
     * @var PDODriver Instance of PDO connection
     */
    protected $db;

    public function __construct($standAlone = true)
    {
        $this->isStandalone = $standAlone;
    }

    public function connect($host, $user, $pass, $db = '', $noerror = 0)
    {
        // Legacy compatibility
        if (!$this->isStandalone) {
            $this->db = Container::getInstance()->getDB();
        } else {
            $this->db = new PDODriver(['host' => $host, 'user' => $user, 'pass' => $pass, 'db' => $db]);
        }
    }

    public function select_db($db)
    {
        return $this->db->exec('use '.$db);
    }

    public function select($sql, $assocMode = 1)
    {
        return $this->db->query($sql);
    }

    public function record($sql, $assocMode = 1)
    {
        return $this->db->record($sql);
    }

    public function query($sql)
    {
        return $this->db->exec($sql);
    }

    public function result($sql)
    {
        return $this->db->result($sql);
    }

    public function db_quote($string)
    {
        return $this->db->quote($string);
    }

    public function qcnt()
    {
        return $this->db->getQueryCount();
    }

    public function num_rows($query)
    {
        return $this->db->num_rows($query);
    }

    public function fetch_row($query)
    {
        return $this->db->fetch_row($query);
    }

    public function affected_rows()
    {
        return $this->db->affected_rows(null);
    }

    public function lastid($table = '')
    {
        return $this->db->lastid($table);
    }

    public function mysql_version()
    {
        return $this->db->getVersion();
    }

    public function close($query)
    {
        return $this->db->close($query);
    }

    public function db_errno()
    {
        return $this->db->db_errno();
    }

    public function __get($name)
    {
        if ($name == 'query_list') {
            $qList = [];
            foreach ($this->db->getQueryList() as $r) {
                $qList[] = sprintf('%6.2f %6.2f %s', $r['start'], $r['duration'], $r['query']);
            }

            return $qList;
        }

        return null;
    }
}
