<?php

class SENE_MySQLi_Column_Builder
{
    public $charset;
    public $name='';
    public $data_type=null;
    public $data_type_length=null;
    public $data_type_extra=null;
    public $is_not_null=false;
    public $default_value=null;
    public $extra=null;

    public function __construct($charset='latin1')
    {
        $this->charset = $charset;
        $this->create('', '', null);
    }

    public function create($name, $data_type, $data_type_length, $data_type_extra=null, $is_not_null = false, $default_value = null, $extra = null)
    {
        $this->name = $name;
        $this->data_type = $data_type;
        $this->data_type_length = $data_type_length;
        $this->data_type_extra = $data_type_extra;
        $this->is_not_null = $is_not_null;
        $this->default_value = $default_value;
        $this->extra = $extra;

        return $this;
    }

    public function varchar($name, $data_type_length, $is_not_null = false, $default_value = null, $extra = null)
    {
        $this->name = $name;
        $this->data_type = 'varchar';
        $this->data_type_length = $data_type_length;
        $this->data_type_extra = '';
        $this->is_not_null = $is_not_null;
        $this->default_value = $default_value;
        $this->extra = $extra;

        return $this;
    }

    public function int($name, $data_type_length, $is_unsigned, $is_not_null, $default_value, $extra = null, $is_zerofill = false)
    {
        $this->name = $name;
        $this->data_type = 'int';
        $this->data_type_length = $data_type_length;
        if ($is_unsigned && $is_zerofill) {
            $data_type_extra = 'UNSIGNED ZEROFILL';
        } elseif ($is_unsigned) {
            $data_type_extra = 'UNSIGNED';
        } else {
            $data_type_extra = '';
        }

        $this->data_type_extra = $data_type_extra;
        $this->is_not_null = $is_not_null;
        $this->default_value = $default_value;
        $this->extra = $extra;

        return $this;
    }

    public function decimal($name, $first_data_type_length, $second_data_type_length, $is_unsigned = false, $is_zerofill = false, $is_not_null = false, $default_value = null, $extra = null)
    {
        $this->name = $name;
        $this->data_type = 'decimal';
        $first_data_type_length = (int) $first_data_type_length;
        $second_data_type_length = (int) $second_data_type_length;
        if (($second_data_type_length + 1) >= $first_data_type_length) {
            trigger_error("second_data_type_length must be less than first_data_type_length: decimal($first_data_type_length, $second_data_type_length)", E_USER_WARNING);
            return $this;
        }
        $data_type_length = "$first_data_type_length, $second_data_type_length";
        $this->data_type_length = $data_type_length;

        $this->data_type_extra = '';
        $this->is_not_null = $is_not_null;
        $this->default_value = $default_value;
        $this->extra = $extra;

        return $this;
    }

    public function date($name, $is_not_null = false, $default_value = null, $extra = null)
    {
        $this->name = $name;
        $this->data_type = 'datetime';
        $this->data_type_length = '';
        $this->data_type_extra = '';
        $this->is_not_null = $is_not_null;
        $this->default_value = $default_value;
        $this->extra = $extra;

        return $this;
    }

    public function datetime($name, $is_not_null = false, $default_value = null, $extra = null)
    {
        $this->name = $name;
        $this->data_type = 'datetime';
        $this->data_type_length = '';
        $this->data_type_extra = '';
        $this->is_not_null = $is_not_null;
        $this->default_value = $default_value;
        $this->extra = $extra;

        return $this;
    }

    public function timestamp($name, $is_default_value = true, $is_extra = true)
    {
        $this->name = $name;
        $this->data_type = 'timestamp';
        $this->data_type_length = '';
        $this->data_type_extra = '';
        $is_not_null = true;
        if (!is_null($is_default_value) && $is_default_value == true) {
            $default_value = 'CURRENT_TIMESTAMP';
        } else {
            $default_value = null;
        }
        if ($is_extra == true) {
            $extra = 'ON UPDATE current_timestamp()';
        }else{
            $extra = null;
        }
        $this->is_not_null = $is_not_null;
        $this->default_value = $default_value;
        $this->extra = $extra;

        return $this;
    }

    public function compile()
    {
        $compiled_object = new \stdClass();
        $compiled_object->name = $this->name;
        $compiled_object->data_type = $this->data_type;
        $compiled_object->data_type_length = $this->data_type_length;
        $compiled_object->data_type_extra = $this->data_type_extra;
        $compiled_object->is_not_null = $this->is_not_null;
        $compiled_object->default_value = $this->default_value;
        $compiled_object->extra = $this->extra;

        return $compiled_object;
    }
}