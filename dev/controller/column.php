<?php
class Column extends JI_Controller
{
  public function __construct()
  {
      parent::__construct();
      $this->load('schema_model','s');
  }
  public function index($utype='add',$table_name='',$column=''){
    if($utype == 'add'){

    }else{

    }
  }
  public function add($table_name,...$columns){
    $migrate_content = new stdClass();
    $table_name = strtolower($table_name);
    $migrate_content->{$table_name} = new stdClass();
    $migrate_content->{$table_name}->columns = new stdClass();
    $migrate_content->{$table_name}->indexes = new stdClass();
    $migrate_content->{$table_name}->relations = new stdClass();
    if(strlen($table_name)<=0){
      echo 'please fill this method with column name parameter format. E.g.: column table_name add name_column:type_column:length:default_value:is_null';
      return;
    }
    if(count($columns)<=0){
      echo 'please fill this method with column name parameter format. E.g.: column add table_name name_column:type_column:length:default_value:is_null';
      return;
    }
    foreach($columns as $c){
      $c = explode(':',strtolower($c));
      $name = isset($c[0]) ? $c[0] : $c;
      $migrate_content->{$table_name}->columns->{$name} = new stdClass();
      $migrate_content->{$table_name}->columns->{$name}->type = isset($c[1]) ? $c[1] : 'varchar';
      $migrate_content->{$table_name}->columns->{$name}->size = isset($c[2]) ? $c[2] : '255';
      $migrate_content->{$table_name}->columns->{$name}->default_value = isset($c[3]) ? $c[3] : '';
      $migrate_content->{$table_name}->columns->{$name}->is_null = false;
      if(isset($c[4]) && !empty($c[4])) $migrate_content->{$table_name}->is_null = true;
      if($migrate_content->{$table_name}->columns->{$name}->type == 'string'){
        $migrate_content->{$table_name}->columns->{$name}->type = 'varchar';
        $migrate_content->{$table_name}->columns->{$name}->size = '255';
      }
    }

    $migrate_version = (int) $this->s->getMigrateVersion($this->migrate_table,$this->version_length);
    $current_version = $migrate_version;
    if($migrate_version <= 0) $migrate_version = 1;
    $filename_prefix = str_pad($migrate_version,$this->version_length,'0',STR_PAD_LEFT);
    $unused = $this->__currentMigrateFiles($current_version,$filename_prefix);

    if(!file_exists($unused)){
      $fh = fopen($unused,'w+');
      fwrite($fh, json_encode($migrate_content,JSON_PRETTY_PRINT));
      fclose($fh);
    }
    if(filesize($unused)<=4){
      $fh = fopen($unused,'w+');
      fwrite($fh, json_encode($migrate_content,JSON_PRETTY_PRINT));
      fclose($fh);
    }
    $fh = fopen($unused,'r');
    $migrate_existing = json_decode(fread($fh,filesize($unused)));
    fclose($fh);

    if(!isset($migrate_existing->{$table_name})) $migrate_existing->{$table_name} = new stdClass();
    if(!isset($migrate_existing->{$table_name}->columns)) $migrate_existing->{$table_name}->columns = new stdClass();
    if(!isset($migrate_existing->{$table_name}->indexes)) $migrate_existing->{$table_name}->indexes = new stdClass();
    if(!isset($migrate_existing->{$table_name}->relations)) $migrate_existing->{$table_name}->relations = new stdClass();

    if(isset($migrate_existing->{$table_name}->columns)){
      $migrate_existing->{$table_name}->columns = $migrate_content->{$table_name}->columns;
    }
    $json = json_encode($migrate_existing, JSON_PRETTY_PRINT);
    $fh = fopen($unused,'w+');
    fwrite($fh, $json);
    fclose($fh);
  }

}
