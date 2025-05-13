<?php

class Schema_Model extends \SENE_Model
{
    public $table_migrate = 'migration_history';
    public $information_schema = 'information_schema';

    public function __construct()
    {
        parent::__construct();
    }

    public function getTables()
    {
        $tables = array();
        foreach(json_decode(json_encode($this->db->query("SHOW TABLES")), true) as $t) {
            $t = array_values($t);
            if(!isset($t[0])) {
                continue;
            }
            $tables[] = $t[0];
        }
        return $tables;
    }
    public function describeTable($table_name)
    {
        return $this->db->query("DESCRIBE $table_name");
    }
    public function getMigrateVersion($table_migrate, $version_length = 4)
    {
        $found = 0;
        foreach($this->getTables() as $table_name) {
            if($table_name == $this->table_migrate) {
                $found = 1;
                break;
            }
        }
        if(!$found) {
            $this->db->query('CREATE TABLE `'.$table_migrate.'` ( `version` int('.$version_length.') unsigned zerofill NOT NULL, PRIMARY KEY (`version`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
        }
        $t = $this->db->from($this->table_migrate)->get_first();
        if(!isset($t->version)) {
            return '0';
        }
        return $t->version;
    }
    public function alter($table_name, $method, $props)
    {
        $sql = 'ALTER TABLE `'.$table_name.'` '.strtoupper($method).' '.$props.';';
        return $this->db->exec($sql);
    }
    public function checkTable($db_name, $table_name)
    {
        $sql = 'SELECT COUNT(*) "total" FROM information_schema.tables WHERE table_schema = "'.$db_name.'" AND table_name = "'.$table_name.'";';
        $d =  $this->db->query($sql);
        if(isset($d[0]->total)) {
            return $d[0]->total;
        }
        return 0;
    }
    public function createTable($table_name, $params)
    {
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0;');
        $is_auto_increment = 0;
        $primary_key = '';
        $indexes = array();
        $is_text = 0;

        $sql = 'CREATE TABLE `'.$table_name.'` (';
        foreach($params as $p) {
            $sql .= "\r\n\t".' `'.$p->name.'` ';
            if($p->type == 'string' || $p->type == 'varchar') {
                if(!isset($p->length)) {
                    $p->length = 255;
                }
                if($p->length > 255) {
                    $p->type = 'text';
                    $sql .= ' '.$p->type.'';
                    $is_text = 1;
                } else {
                    $p->type = 'varchar';
                    $sql .= ' '.$p->type.'('.$p->length.')';
                }
            } elseif($p->type == 'enum') {
                $sql .= ' '.$p->type.'('.implode(',', $p->value).')';
            } elseif($p->type == 'int') {
                $sql .= ' '.$p->type.'('.$p->length.')';
            } else {
                $sql .= ' '.$p->type.' ';
            }

            if(isset($p->is_unsigned) && !empty($p->is_unsigned)) {
                $sql .= ' UNSIGNED';
            }
            if($is_text && isset($p->is_null)) {
                if(!empty($p->is_null)) {
                    $sql .= ' NULL';
                } else {
                    $sql .= ' NOT NULL';
                }
            } else {
                $sql .= ' NULL';
            }
            if(empty($is_text) && isset($p->is_primary) && isset($p->is_index)) {
                if(!empty($p->is_primary)) {
                    if(isset($p->is_auto_increment) && !empty($p->is_auto_increment)) {
                        $is_auto_increment = 1;
                        $sql .= ' AUTO_INCREMENT';
                        $primary_key = $p->name;
                    }
                } elseif(!empty($p->is_index)) {
                    $indexes[] = $p->name;
                }
            }
            if(empty($is_text) && isset($p->default_value)) {
                if(strlen($p->default_value)) {
                    $sql .= ' DEFAULT '.addslashes($p->default_value).'';
                } else {
                    if($p->type == 'varchar') {
                        $sql .= ' DEFAULT ""';
                    }
                }

            }
            if(empty($is_text) && isset($p->on_update) && strlen($p->on_update)) {
                $sql .= ' ON UPDATE  '.$p->on_update.'';
            }
            $sql .= ',';
        }
        if(strlen($primary_key)) {
            $sql .= 'PRIMARY KEY(`'.$primary_key.'`),';
        }
        if(count($indexes)) {
            foreach($indexes as $idx) {
                $sql .= 'KEY `idx_'.$idx.'` ('.$idx.'),';
            }
        }
        $sql = rtrim($sql, ',');
        $sql .= "\r\n".') ENGINE=InnoDB ';
        if(!empty($is_auto_increment)) {
            $sql .= 'AUTO_INCREMENT=1 ';
        }
        $sql .= 'DEFAULT CHARSET=utf8mb4;';

        $this->db->exec($sql);
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1;');
    }
}
