<?php
require_once SEMEROOT.'kero/sine/SENE_MySQLi_Column_Builder.php';

class SENE_MySQLi_Table_Builder
{
    public $__mysqli;
    public $if_not_exists=false;
    public $primary_key_column_name=null;
    public $table_name=null;
    public $columns=null;
    public $charset='latin1';
    public $database_name='';
    public $database_engine='innodb';
    public $sql='';
    public $is_debug=true;
    public $unique_keys;
    public $indexes;

    public function __construct($__mysqli, $database_name, $charset='latin1')
    {
        $this->__mysqli = $__mysqli;
        $this->database_name = $database_name;
        $this->charset = $charset;
        $this->reset();
    }

    private function reset()
    {
        $this->sql = '';
        $this->table_name = null;
        $this->primary_key_column_name = null;
        $this->if_not_exists = false;
        $this->columns = array();
        $this->unique_keys = array();
        $this->indexes = array();

        return $this;
    }

    private function if_not_exists_query()
    {
        if ($this->if_not_exists == true) {
            return 'IF NOT EXISTS';
        } else {
            return '';
        }
    }

    private function null_not_null($column){
        if (isset($column->is_not_null) && $column->is_not_null == true) {
            return 'NOT NULL';
        } else {
            return 'NULL';
        }
    }

    private function default_value($column){
        if (is_string($column->default_value) && strlen($column->default_value) == 0) {
            return 'DEFAULT ""';
        } else if (is_string($column->default_value) && strlen($column->default_value) > 0) {
            return 'DEFAULT "'.$column->default_value.'"';
        } else if (!is_null($column->default_value) && is_numeric($column->default_value)) {
            return 'DEFAULT '.$column->default_value.'';
        } else if (isset($column->is_not_null) && $column->is_not_null == false && is_null($column->default_value)) {
            return 'DEFAULT NULL';
        } else {
            return '';
        }
    }

    public function build_query()
    {
        $table_name = $this->table_name;
        $if_not_exists = $this->if_not_exists_query();
        $sql = "CREATE TABLE $if_not_exists `$table_name` (";

        if ($this->primary_key_column_name !== false && $this->primary_key_column_name !== null) {
            foreach ($this->columns as $column) {
                if ($column->name == $this->primary_key_column_name) {
                    $sql .= ' `'.$column->name.'` '.$column->data_type.'('.$column->data_type_length.') '.$column->data_type_extra.' NOT NULL';
                    if ($column->data_type == 'int') {
                        $sql .= ' AUTO_INCREMENT';
                    }
                    $sql .= ', ';
                }
            }
        }
        foreach ($this->columns as $column) {
            if ($column->name == $this->primary_key_column_name) {
                continue;
            }
            $null_not_null = $this->null_not_null($column);
            $default_value = $this->default_value($column);
            $sql .= ' `'.$column->name.'` '.$column->data_type;
            if (in_array($column->data_type, ['int', 'decimal','varchar', 'text', 'blob', 'mediumtext', 'longtext', 'enum', 'set'])) {
                $sql .= '('.$column->data_type_length.') ';
            }
            $sql .= $column->data_type_extra.' '.$null_not_null.' '.$default_value.', ';
        }
        if (!is_null($this->primary_key_column_name)) {
            $sql .= ' PRIMARY KEY (`'.$this->primary_key_column_name.'`), ';
        }
        foreach ($this->unique_keys as $key=>$values) {
            $sql .= "UNIQUE KEY `$key` (";
            foreach ($values as $value) {
                $sql .= "`".$value."`, ";
            }
            $sql = rtrim($sql, ', ')."), ";
        }
        foreach ($this->indexes as $key=>$index) {
            $sql .= "KEY `$key` (";
            foreach ($index->columns as $column) {
                $sql .= "`".$column."`, ";
            }
            $sql = rtrim($sql, ', ').') USING '.$index->algorithm.", ";
        }

        $sql = rtrim($sql, ', ');
        $sql .= ") ENGINE=".$this->database_engine." DEFAULT CHARSET=".$this->charset." COLLATE=".$this->charset."_general_ci;";

        $this->sql = $sql;

        return $this;
    }

    public function create($table_name, $primary_key_column_name=false, $if_not_exists = false)
    {
        $this->table_name = $table_name;
        $this->primary_key_column_name = $primary_key_column_name;
        $this->if_not_exists = $if_not_exists;

        return $this;
    }

    private function instantiate_column_object()
    {
        return new \SENE_MySQLi_Column_Builder($this->charset);
    }

    public function varchar($name, $length)
    {
        $column = $this->instantiate_column_object();
        $this->columns[$name] = $column->varchar($name, $length)->compile();
        return $this;
    }

    public function int($name, $length, $is_unsigned = false, $is_zerofill = false, $is_not_null = false, $default_value = null)
    {
        $column = $this->instantiate_column_object();
        $this->columns[$name] = $column->int($name, $length, $is_unsigned, $is_zerofill, $is_not_null, $default_value)->compile();
        return $this;
    }

    public function timestamps()
    {
        $column = $this->instantiate_column_object();
        $this->columns['created_at'] = $column->datetime('created_at', true)->compile();

        $column = $this->instantiate_column_object();
        $this->columns['updated_at'] = $column->timestamp('updated_at', false, null, 'ON UPDATE current_timestamp()');
        return $this;
    }

    public function default_flags()
    {
        $column = $this->instantiate_column_object();
        $this->columns['is_deleted'] = $column->int('is_deleted', 1, true, true, 0)->compile();
        $this->index("is_deleted_idx", ["is_deleted"]);

        $column = $this->instantiate_column_object();
        $this->columns['is_active'] = $column->int('is_active', 1, true, true, 1)->compile();
        $this->index("is_active_idx", ["is_active"]);
        return $this;
    }

    public function primary_key($column_name)
    {
        if (isset($this->columns[$column_name]))
        {
            $this->primary_key_column_name = $column_name;
        }

        return $this;
    }

    public function unique_key($unique_key_name, $columns)
    {
        if (!isset($this->unique_keys[$unique_key_name]))
        {
            $this->unique_keys[$unique_key_name] = array();
        }
        $this->unique_keys[$unique_key_name] = $columns;

        return $this;
    }

    public function index($index_key_name, $columns, $algorithm='BTREE')
    {
        if (!isset($this->indexes[$index_key_name]))
        {
            $this->indexes[$index_key_name] = new \stdClass();
            $this->indexes[$index_key_name]->columns = array();
            $this->indexes[$index_key_name]->algorithm = '';
        }
        $this->indexes[$index_key_name]->columns = $columns;
        $this->indexes[$index_key_name]->algorithm = $algorithm;

        return $this;
    }
    
    private function execute_mysqli()
    {
        $res = $this->__mysqli->query($this->sql);
        if ($res) {
            $this->reset();
            return 1;
        } else {
            if ($this->is_debug) {
                trigger_error(TEM_ERR.': '.$this->__mysqli->error.'. '.$this->sql, E_USER_NOTICE);
            }
            return 0;
        }
    }

    public function process()
    {
        $this->build_query();
        return $this->execute_mysqli();
    }

    public function index_create($table_name, $column_name, $index_name='', $using_method='BTREE'){
        $database_name = $this->database_name;
        if (strlen($index_name) <= 4) {
            $index_name = $table_name.'_'.$column_name.'_idx';
        }
        $this->sql = "CREATE INDEX $index_name USING $using_method ON $database_name.$table_name ($column_name);";
        return $this->execute_mysqli();
    }

    public function index_drop($table_name, $index_name){
        $database_name = $this->database_name;
        $this->sql = "ALTER TABLE $database_name.$table_name DROP INDEX $index_name;";
        return $this->execute_mysqli();
    }
}