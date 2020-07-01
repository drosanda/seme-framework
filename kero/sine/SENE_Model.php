<?php
abstract class SENE_Model
{
    protected $db;
    protected $directories;
    protected $config;
    public $field = array();
    
    public function __construct()
    {
        $this->directories = $GLOBALS['SEMEDIR'];
        $this->config = $GLOBALS['SEMECFG'];
        $this->loadEngine($this->config->database);
    }
    private function loadEngine($db)
    {
        if (!is_object($db)) {
            $db = new stdClass();
        }
        if (!isset($db->engine)) {
            $db->engine = '';
        }
        if ($db->engine=="mysql") {
            require_once($this->directories->kero_sine."SENE_MySQL_Engine.php");
            $this->db = new SENE_MySQLi_Engine($db);
        } elseif ($db->engine=="pdo") {
            require_once($this->directories->kero_sine."SENE_PDO_Engine.php");
            $this->db = new SENE_MySQLi_Engine($db);
        } else {
            require_once($this->directories->kero_sine."SENE_MySQLi_Engine.php");
            $this->db = new SENE_MySQLi_Engine($db);
        }
    }
    public function exec($sql)
    {
        // $this->field = $this->engine->getField();
        return $this->db->exec($sql);
    }
    
    public function multiExec($sql)
    {
        // $this->field = $this->engine->getField();
        $res = $this->db->multiExec($sql);
    }
    
    public function select($sql, $cache_engine=0, $flushcache=0, $tipe="object")
    {
        //die($tipe);
        return $this->db->select($sql, $cache_engine, $flushcache, $tipe);
    }
    public function lastId()
    {
        return $this->db->lastId();
    }
    public function esc($str)
    {
        return $this->db->esc($str);
    }
    public function prettyName($name)
    {
        $name=strtolower(trim($name));
        $names=explode("_", $name);
        $name='';
        foreach ($names as $n) {
            $name=$name.''.ucfirst($n).' ';
        }
        return $name;
    }
    
    public function filter(&$str)
    {
        $str=filter_var($str, FILTER_SANITIZE_SPECIAL_CHARS);
    }
    public function getLastQuery()
    {
        return $this->db->getLastQuery();
    }
    public function setDebug($is_debug)
    {
        return $this->db->setDebug($is_debug);
    }
}
