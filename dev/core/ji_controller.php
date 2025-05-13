<?php
class JI_Controller extends SENE_Controller
{
  public $schema_file = 'kero/schema.json';
  public $migrate_dir = 'kero/migrate';
  public $migrate_table = '__migrate';
  public $migrate_files = '';
  public $version_length = 4;

  public $r = "\r\n";
  public $rn = "\r\n";

  public $t = "\t";
  public function __construct(){
    parent::__construct();
    $this->migrate_dir = SEMEROOT.DS.$this->migrate_dir;
    $this->migrate_files = array();

    $this->rn2 = $this->rn.$this->rn;
    $this->t1 = $this->t;
    $this->t2 = $this->t1.$this->t;
    $this->t3 = $this->t2.$this->t;
    $this->t4 = $this->t3.$this->t;
    $this->t5 = $this->t4.$this->t;
    $this->t6 = $this->t5.$this->t;
    $this->t7 = $this->t6.$this->t;

  }

  protected function __int($type){
    $values = 0;
    $types = explode(' ',$type);
    preg_match("/\d./", $type, $values);
    if (is_array($values) && isset($values[0])) {
      $values = (int) $values[0];
    }

    return (object) [
      "type" => 'int',
      "values" => ''.(isset($types[1])) ? $types[1] : '',
      "length" => ''.(is_array($values) ? 0 : $values)
    ];
  }
  protected function __varchar($type){
    $values = 0;
    preg_match("/\d./", $type, $values);
    if (is_array($values) && isset($values[0])) {
      $values = (int) $values[0];
    }
    return (object) [
      "type" => 'string',
      "values" => '',
      "length" => ''.(is_array($values) ? 0 : $values)
    ];
  }
  protected function __parseType($type){
    if(stripos($type,'enum') !== false){
      return $this->__enum($type);
    }else if(stripos($type,'int') !== false){
      return $this->__int($type);
    }else if(stripos($type,'varchar') !== false){
      return $this->__varchar($type);
    }else if(stripos($type,'text') !== false){
      return (object) [
        "type" => 'string',
        "values" => '',
        "length" => '16777215'
      ];
    }else if(stripos($type,'timestamp') !== false){
      return (object) [
        "type" => 'timestamp',
        "values" => '',
        "length" => ''
      ];
    }else{
      return $type;
    }
  }

  protected function __reParseType($column_definition){
    if($column_definition->type == 'string'){
      if($column_definition->length > 255){
        $column_definition->type = 'text';
      }else{
        $column_definition->type = 'varchar';
      }
    }
    return (object) [
      "name" => $column_definition->name,
      "type" => $column_definition->type,
    ];
  }
  protected function __enum($type){
    $values = array();
    preg_match("/^enum\(\'(.*)\'\)$/", $type, $values);
    if (is_array($values) && isset($values[1])) {
      $values = explode("','", $values[1]);
    }
    return (object) [
      "type" => 'enum',
      "values" => $values,
      "length" => ''.count($values)
    ];
  }
  protected function __checkMigrateDir(){
    if(is_dir($this->migrate_dir)){
      if(is_file($this->migrate_dir)){
        trigger_error('Please check migrate directory on: '.$this->migrate_dir.'');
        return false;
      }
    }else{
      mkdir($this->migrate_dir,0775);
    }

    return $this->migrate_dir;
  }
  protected function __getMigrateFiles(){
    $this->migrate_files = array();
    if ($handle = opendir($this->__checkMigrateDir())) {
      while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
          $this->migrate_files[] = $entry;
        }
      }
      closedir($handle);
    }
    return $this->migrate_files;
  }
  protected function __currentMigrateFiles($current_version,$filename_prefix){
    $unused = '';
    $current_version = (int) $current_version;
    $migrate_files = $this->__getMigrateFiles();
    foreach($migrate_files as $file){
      $pi = pathinfo($file);
      $filename_int = (int) $pi['filename'];
      $used[$filename_int] = 0;
      if($filename_int > $current_version){
        $unused = $file;
      }
    }
    if(empty($unused)){
      $unused = $this->migrate_dir.DS.$filename_prefix.'.json';
    }

    return $unused;
  }
  public function index(){}
}
