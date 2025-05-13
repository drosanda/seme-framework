<?php
class Sync extends JI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load('schema_model','s');
    }
    public function index()
    {
        echo 'dev/home';
    }
    /**
     * Synchronize Table from Schema File
     * @param  string $table_name  Name of table
     * @return void
     */
    public function from($table_name='')
    {
      echo '========================================='.$this->rn;
      echo '|  '.$this->rn;
      echo '|  Starting Synchronization process'.$this->rn;
      echo '|  from schema to database'.$this->rn;
      echo '|  '.$this->rn;
      echo '|----------------------------------------'.$this->rn;
      $dropped = array();
      $diff_a = array();
      $schema_file = SEMEROOT.DS.$this->schema_file;
      echo '|  Loading schema file: ...';
      if(filesize($schema_file)<=70){
        echo ' failed.'.$this->rn;
        echo '|  Schema file on: '.$schema_file.' not exist or empty.'.$this->rn.'Please generate one `php -f ./sine schema:generate`';
      }else{
        $fh = fopen($schema_file,'r');
        $migrate_existing = json_decode(@fread($fh, filesize($schema_file)));
        fclose($fh);
        echo ' loaded.'.$this->rn;

        if(strlen($table_name)){
          if(isset($migrate_existing->tables->{$table_name})){
            $check_table = $this->s->checkTable($this->config->database->name,$table_name);
            if(!empty($check_table)){
              $after = '';
              $table_columns = $this->s->describeTable($table_name);
              foreach($table_columns as $col){
                $name = strtolower($col->Field);
                $type = $this->__parseType($col->Type);
                if(!isset($migrate_existing->tables->{$table_name}->columns->{$name})){
                  $dropped[] = $col;
                }else{
                  $diff_a[$name] = $migrate_existing->tables->{$table_name}->columns->{$name};
                  $migrate_existing->tables->{$table_name}->columns->{$name}->is_processed = true;
                  $migrate_existing->tables->{$table_name}->columns->{$name}->after = $after;
                  if(empty($after)){
                    unset($migrate_existing->tables->{$table_name}->columns->{$name}->after);
                  }
                }
                $diff_a[$name] = $col;
                $after = $name;
              }
              $is_text = 0;
              $is_datetime = 0;
              foreach($migrate_existing->tables->{$table_name}->columns as $unprocessed){
                $diff_b[$unprocessed->name] = $unprocessed;
                if(!isset($unprocessed->is_processed)){
                  $props = '`'.$unprocessed->name.'` ';
                  if($unprocessed->type == 'string' || $unprocessed->type == 'text'|| $unprocessed->type == 'varchar'){
                    if($unprocessed->length > 255){
                      $is_text = 1;
                      $unprocessed->type = 'text';
                    }else{
                      $unprocessed->type = 'varchar';
                    }
                  }

                  if($unprocessed->type == 'varchar' || $unprocessed->type == 'int' || $unprocessed->type == 'integer' || $unprocessed->type == 'number' || $unprocessed->type == 'decimal' || $unprocessed->type == 'float'){
                    $props .= strtoupper($unprocessed->type).'('.$unprocessed->length.') ';
                  }elseif($unprocessed->type == 'enum'){
                    $props .= strtoupper($unprocessed->type).'(';
                    foreach($unprocessed->value as $vls){
                      $props .= ''.addslash($vls).',';
                    }
                    $props = rtrim($props,',');
                    $props .= ') ';
                  }else{
                    $props .= strtoupper($unprocessed->type).' ';
                  }
                  if($unprocessed->type == 'datetime'){
                    $is_datetime = 1;
                  }

                  if($unprocessed->is_null == false){
                    $props .= 'NOT NULL ';
                  }else{
                    $props .= 'NULL ';
                  }
                  if(is_null(strtolower($unprocessed->default_value)) || strtolower($unprocessed->default_value) == 'null'){
                    $props .= 'DEFAULT NULL ';
                  }else if(strlen($unprocessed->default_value)){
                    if($unprocessed->type == 'timestamp'){
                      $props .= 'DEFAULT '.addslashes($unprocessed->default_value).' ';
                    }else{
                      $props .= 'DEFAULT \''.addslashes($unprocessed->default_value).'\' ';
                    }

                  }else if(empty($is_datetime)){
                    if($unprocessed->type == 'string' || $unprocessed->type == 'varchar' || $unprocessed->type == 'text'){
                      $props .= 'DEFAULT \'\' ';
                    }else{
                      $props .= 'DEFAULT \'0\' ';
                    }
                  }
                  if(isset($unprocessed->after)){
                    if(strlen($unprocessed->after)){
                      $props .= 'AFTER `'.$unprocessed->after.'`';
                    }else{
                      $props .= 'FIRST ';
                    }
                  }else{
                    $props .= 'FIRST ';
                  }

                  $result = $this->s->alter($table_name,'add',$props);
                  if($result){
                    echo '|  New column `'.$unprocessed->name.'` on table: '.$table_name.' has been added succesfully.'.$this->rn;
                  }else{
                    echo '|  Failed to add `'.$unprocessed->name.'` column on table: '.$table_name.'.'.$this->rn;
                  }
                }
              }
              foreach($dropped as $col){
                $name = strtolower($col->Field);
                $props = '';
                $props .= '`'.$name.'` ';
                $result = $this->s->alter($table_name,'drop',$props, '');
                if($result){
                  echo '|  Existing column `'.$name.'` on table: '.$table_name.' has been dropped succesfully.'.$this->rn;
                }else{
                  echo '|  Failed to drop `'.$name.'`` column on  table: '.$table_name.'.'.$this->rn;
                }
              }

              //sync column props
              $is_text = 0;
              $is_datetime = 0;
              foreach($diff_b as $key=>$b){
                if(isset($diff_a[$key])) {
                  $a = new stdClass();
                  $a->name = $diff_a[$key]->Field;
                  $a->type = $diff_a[$key]->Type;
                  $a->name = $diff_a[$key]->Field;
                  $a->type = $diff_a[$key]->Type;
                  $a->length = '';
                  $a->value = array();
                  $a->is_null = (strtolower($diff_a[$key]->Null) == 'no') ? false : true;
                  $a->default_value = $diff_a[$key]->Default;

                  $type = $this->__parseType($a->type);
                  if(isset($type->type)){
                    if($type->type == 'enum'){
                      $a->type = $type->type;
                      $a->value = $type->values;
                      $a->length = $type->length;
                    }else if($type->type == 'int'){
                      $a->type = $type->type;
                      $a->value = $type->values;
                      $a->length = $type->length;
                      $a->is_unsigned = (stripos($diff_a[$key]->Type, "unsigned") !== false) ? true : false;
                      $a->is_zerofill = (stripos($diff_a[$key]->Type, "zerofill") !== false) ? true : false;
                    }else if($type->type == 'string'){
                      $a->type = $type->type;
                      $a->length = $type->length;
                    }else if($type->type == 'timestamp'){
                      $a->type = $type->type;
                      $a->on_update = (stripos($diff_a[$key]->Extra, "on update") !== false) ? 'current_timestamp()' : false;
                    }
                  }

                  if(($a->type != $b->type) || ($a->is_null != $b->is_null)){
                    $mod = '`'.$a->name.'`';
                    if($b->type == 'string' || $b->type == 'text'|| $b->type == 'varchar'){
                      if($b->length > 255){
                        $is_text = 1;
                        $b->type = 'text';
                      }else{
                        $b->type = 'varchar';
                      }
                    }

                    if($b->type == 'varchar' || $b->type == 'int' || $b->type == 'integer' || $b->type == 'number' || $b->type == 'decimal' || $b->type == 'float'){
                      $mod .= strtoupper($b->type).'('.$b->length.') ';
                    }elseif($b->type == 'enum'){
                      $mod .= strtoupper($b->type).'(';
                      foreach($b->value as $vls){
                        $mod .= ''.addslash($vls).',';
                      }
                      $mod = rtrim($mod,',');
                      $mod .= ') ';
                    }else{
                      $mod .= strtoupper($b->type).' ';
                    }

                    if($b->type == 'datetime'){
                      $is_datetime = 1;
                    }

                    if($b->is_null == false){
                      $mod .= 'NOT NULL ';
                    }else{
                      $mod .= 'NULL ';
                    }
                    if(is_null(strtolower($b->default_value)) || strtolower($b->default_value) == 'null'){
                      $mod .= 'DEFAULT NULL ';
                    }else if(strlen($b->default_value)){
                      if($b->type == 'timestamp'){
                        $mod .= 'DEFAULT '.addslashes($b->default_value).' ';
                      }else{
                        $mod .= 'DEFAULT \''.addslashes($b->default_value).'\' ';
                      }

                    }else if(empty($is_datetime)){
                      if($b->type == 'string' || $b->type == 'varchar' || $b->type == 'text'){
                        $mod .= 'DEFAULT \'\' ';
                      }else{
                        $mod .= 'DEFAULT \'0\' ';
                      }
                    }
                    if(isset($b->after)){
                      if(strlen($b->after)){
                        $mod .= 'AFTER `'.$b->after.'`';
                      }else{
                        $mod .= 'FIRST ';
                      }
                    }else{
                      $mod .= 'FIRST ';
                    }

                    $result = $this->s->alter($table_name,'MODIFY COLUMN',$mod);
                    if($result){
                      echo '|  Column `'.$a->name.'` on table: '.$table_name.' has been modified succesfully.'.$this->rn;
                    }else{
                      echo '|  Failed to modify `'.$a->name.'` column on table: '.$table_name.'.'.$this->rn;
                    }
                  }

                }

              }
            }else{
              $this->s->createTable($table_name,$migrate_existing->tables->{$table_name}->columns);
              echo '|  Table `'.$table_name.'`` was created succesfully.'.$this->rn;
            }
          }else{
            echo '|  Schema for table: '.$table_name.' is undefined.'.$this->rn;
          }
        }else{
          echo '|  Schema date created: '.$migrate_existing->cdate.$this->rn;
          echo '|  Schema last update: '.$migrate_existing->ldate.$this->rn;
        }
      }
      echo '|----------------------------------------'.$this->rn;
      echo '|  Synchronization process: finisihed.'.$this->rn;
      echo '|----------------------------------------'.$this->rn;

    }
}
