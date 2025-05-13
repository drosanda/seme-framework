<?php
class DB extends JI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load('schema_model','s');
    }
    public function index()
    {
        echo 'DB';
    }
    public function schema()
    {
      $cdate = date('ymdHis');
      $ldate = $cdate;
      $migrate_content = new stdClass();
      $migrate_content->cdate = $cdate;
      $migrate_content->ldate = $ldate;
      $migrate_content->tables = new stdClass();

      $schema_file = SEMEROOT.DS.$this->schema_file;
      if(!file_exists($schema_file)){
        $fh = fopen($schema_file,'w+');
        fwrite($fh, json_encode($migrate_content,JSON_PRETTY_PRINT));
        fclose($fh);
      }
      if(filesize($schema_file)<=70){
        $fh = fopen($schema_file,'w+');
        fwrite($fh, json_encode($migrate_content,JSON_PRETTY_PRINT));
        fclose($fh);
      }else{
        $fh = fopen($schema_file,'r');
        $migrate_existing = json_decode(@fread($fh, filesize($schema_file)));
        fclose($fh);
      }



      if(isset($migrate_existing->cdate)) $migrate_content->cdate = $migrate_existing->cdate;

      $tables = $this->s->getTables();
      foreach($tables as $table_name){
        $table_name = strtolower($table_name);
        if($table_name == $this->migrate_table) continue;
        if(!isset($migrate_content->tables->{$table_name})) $migrate_content->tables->{$table_name} = new stdClass();
        if(!isset($migrate_content->tables->{$table_name}->columns)) $migrate_content->tables->{$table_name}->columns = new stdClass();
        if(!isset($migrate_content->tables->{$table_name}->indexes)) $migrate_content->tables->{$table_name}->indexes = new stdClass();
        if(!isset($migrate_content->tables->{$table_name}->relations)) $migrate_content->tables->{$table_name}->relations = new stdClass();

        $table_defs = $this->s->describeTable($table_name);
        foreach($table_defs as $def){
          $name = strtolower($def->Field);
          if(!isset($migrate_content->tables->{$table_name}->columns->{$name})) $migrate_content->tables->{$table_name}->columns->{$name} = new stdClass();
          $migrate_content->tables->{$table_name}->columns->{$name}->name = $def->Field;
          $migrate_content->tables->{$table_name}->columns->{$name}->type = $def->Type;
          $migrate_content->tables->{$table_name}->columns->{$name}->length = '';
          $migrate_content->tables->{$table_name}->columns->{$name}->value = array();
          $migrate_content->tables->{$table_name}->columns->{$name}->is_null = (strtolower($def->Null) == 'no') ? false : true;
          $migrate_content->tables->{$table_name}->columns->{$name}->default_value = $def->Default;
          $migrate_content->tables->{$table_name}->columns->{$name}->is_index = false;

          $type = $this->__parseType($def->Type);
          if(isset($type->type)){
            if($type->type == 'enum'){
              $migrate_content->tables->{$table_name}->columns->{$name}->type = $type->type;
              $migrate_content->tables->{$table_name}->columns->{$name}->value = $type->values;
              $migrate_content->tables->{$table_name}->columns->{$name}->length = $type->length;
            }else if($type->type == 'int'){
              $migrate_content->tables->{$table_name}->columns->{$name}->type = $type->type;
              $migrate_content->tables->{$table_name}->columns->{$name}->value = $type->values;
              $migrate_content->tables->{$table_name}->columns->{$name}->length = $type->length;
              $migrate_content->tables->{$table_name}->columns->{$name}->is_unsigned = (stripos($def->Type, "unsigned") !== false) ? true : false;
              $migrate_content->tables->{$table_name}->columns->{$name}->is_zerofill = (stripos($def->Type, "zerofill") !== false) ? true : false;
            }else if($type->type == 'string'){
              $migrate_content->tables->{$table_name}->columns->{$name}->type = $type->type;
              $migrate_content->tables->{$table_name}->columns->{$name}->length = $type->length;
              unset($migrate_content->tables->{$table_name}->columns->{$name}->value);
            }else if($type->type == 'timestamp'){
              $migrate_content->tables->{$table_name}->columns->{$name}->type = $type->type;
              $migrate_content->tables->{$table_name}->columns->{$name}->on_update = (stripos($def->Extra, "on update") !== false) ? 'current_timestamp()' : false;
              if(!$migrate_content->tables->{$table_name}->columns->{$name}->on_update) unset($migrate_content->tables->{$table_name}->columns->{$name}->on_update);
              unset($migrate_content->tables->{$table_name}->columns->{$name}->length);
              unset($migrate_content->tables->{$table_name}->columns->{$name}->value);
            }
          }

          if(isset($def->Key)){
            $migrate_content->tables->{$table_name}->columns->{$name}->is_index = false;
            if($def->Key == 'PRI'){
              $migrate_content->tables->{$table_name}->columns->{$name}->is_index = true;
              $migrate_content->tables->{$table_name}->columns->{$name}->is_primary = true;
              $migrate_content->tables->{$table_name}->columns->{$name}->is_auto_increment = (stripos($def->Extra, "auto_increment") !== false) ? true : false;
            }else if($def->Key == 'MUL'){
              unset($migrate_content->tables->{$table_name}->columns->{$name}->is_primary);
              unset($migrate_content->tables->{$table_name}->columns->{$name}->is_auto_increment);
              $migrate_content->tables->{$table_name}->columns->{$name}->is_index = true;
            }else{
              unset($migrate_content->tables->{$table_name}->columns->{$name}->is_primary);
              unset($migrate_content->tables->{$table_name}->columns->{$name}->is_auto_increment);
              unset($migrate_content->tables->{$table_name}->columns->{$name}->is_index);
            }
          }
        }
      }
      print_r($migrate_content);
      $fh = fopen($schema_file,'w+');
      fwrite($fh, json_encode($migrate_content, JSON_PRETTY_PRINT));
      fclose($fh);
    }
}
