<?php

class SENE_Migration extends \SENE_Model
{
    public $table_builder;
    public function __construct()
    {
        parent::__construct();
        $this->table_builder = $this->db->table_builder;
    }

    protected function execute($sql)
    {
        return $this->db->exec($sql);
    }

    protected function drop_table($table_name, $if_exists = false)
    {
        $sql = 'DROP TABLE';
        if ($if_exists) {
            $sql .= ' IF EXISTS';
        }
        $sql .= ' '.$table_name.';';

        return $this->execute($sql);
    }

    protected function drop_index($table_name, $index_name)
    {
        $this->db->table_builder->index_drop($table_name, $index_name);
    }
}