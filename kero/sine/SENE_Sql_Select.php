<?php

#[AllowDynamicProperties]
class SENE_Sql_Select extends \SENE_Sql
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Sets the SELECT clause of the query.
     *
     * @param mixed $sql Either an array of column names or a single column name as a string, or empty for all columns ('*')
     * 
     * @return self For method chaining
     */
    public function select($sql = '')
    {
        if (is_array($sql)) {
            $this->query_string = implode(', ', array_map(function ($s) {
                return "`$s`";
            }, $sql));
        } elseif (false !== $sql && $sql !== '*') {
            $this->query_string = "'`{$sql}`', ";
        } else {
            $this->query_string = '*' . ($sql === '' ? '' : ', ');
        }
    
        return $this;
    }

    /**
     * Select column or fields with alias.
     *
     * @param array|string $selected_column
     * @param string|null $aliased_column
     * 
     * @return $this
     */
    public function select_as($selected_column, string $aliased_column = null)
    {
        if (is_array($selected_column)) {
            foreach ($selected_column as $k => $v) {
                $this->query_string .= $k . " AS '" . ($v ?? $k) . "', ";
            }
        } else {
            $this->query_string .= $selected_column . " AS '" . ($selected_column ?? $aliased_column) . "', ";
        }

        // Remove trailing comma and space
        if (substr($this->query_string, -2) === ', ') {
            $this->query_string = substr($this->query_string, 0, -2);
        }

        return $this;
    }
}