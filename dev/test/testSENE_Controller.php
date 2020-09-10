<?php
// Path to run ./vendor/bin/phpunit --bootstrap vendor/autoload.php FileName.php
// Butuh Framework PHPUnit
use PHPUnit\Framework\TestCase;

//constants
define('ADMIN_URL','localhost');
define('SENEVIEW','../../app/view');

// Class yang mau di TEST.
//require_once "../../kero/sine/SENE_Controller.php";

// Class untuk run Testing.
class mockSENE_Controller extends SENE_Controller {
  public function __construct(){
    parent::__construct();
  }
  public function index(){

  }
}

class testSENE_Controller extends TestCase
{
  public function __construct(){
    parent::__construct();
    if (!defined('SEME_VERSION')) {
      define('SEME_VERSION', '4.0.0');
    }
    if (!defined('SEMEROOT')) {
      define('SEMEROOT', realpath('../../'));
    }

    $GLOBALS['SEMEDIR'] = new stdClass();

    /**
    * Register a direcotyr
    * @param  string $directory directory name
    * @param  string $name      alias
    */
    $regdir = function ($directory, $name, $constant='') {
      $directory = SEMEROOT.'/'.$directory;
      if (realpath($directory) !== false) {
        $directory = realpath($directory).'/';
      }
      if (!is_dir($directory)) {
        trigger_error('Missing '.$directory.'');
      }
      $directory = rtrim($directory, '/').'/';
      if (!is_dir($directory)) {
        trigger_error('Missing '.$directory.'');
      }
      $GLOBALS['SEMEDIR']->$name = $directory;
      if (strlen($constant)>0) {
        if (!defined(strtoupper($constant))) {
          define(strtoupper($constant), $directory);
        }
      }
    };


    // directory register
    $regdir('app', 'app');
    $regdir('app/cache', 'app_cache', 'SENECACHE');
    $regdir('app/config', 'app_config');
    $regdir('app/controller', 'app_controller');
    $regdir('app/core', 'app_core');
    $regdir('app/model', 'app_model');
    $regdir('app/view', 'app_view');
    $regdir('kero', 'kero');
    $regdir('kero/lib', 'kero_lib');
    $regdir('kero/sine', 'kero_sine');
    $regdir('kero/bin', 'kero_bin');

    // remove function
    unset($regdir);

    //cli validation
    if (!isset($_SERVER)) {
      $_SERVER = array();
    }
    if (!isset($_SERVER['HTTP_HOST'])) {
      $_SERVER['HTTP_HOST'] = 'localhost';
    }
    if (!isset($_SERVER['REQUEST_URI'])) {
      $_SERVER['REQUEST_URI'] = '/';
    }
    if (!isset($_SERVER['DOCUMENT_ROOT'])) {
      $_SERVER['DOCUMENT_ROOT'] = __DIR__;
    }
    if (!isset($_SERVER['HTTP_HOST'])) {
      $_SERVER['HTTP_HOST'] = 'localhost';
    }

    // find configuration files and loaded it
    $config_values = array();
    $config_file_found = 0;
    $config_file_array = array('development.php','staging.php','production.php');
    $semevar = array();
    $routes = array();
    foreach ($config_file_array as $cfa) {
      if (file_exists($GLOBALS['SEMEDIR']->app_config.$cfa) && is_readable($GLOBALS['SEMEDIR']->app_config.$cfa)) {
        $config_file_found++;
        $config_values['file'] = $GLOBALS['SEMEDIR']->app_config.$cfa;
        $config_values['environment'] = rtrim($cfa, '.php');
        require_once($GLOBALS['SEMEDIR']->app_config.$cfa);
      }
    }
    if (empty($config_file_found)) {
      die('No settings file found in : '.$GLOBALS['SEMEDIR']->app_config);
    }

    $db = array();
    $db['host']  = 'localhost';
    $db['user']  = 'root';
    $db['pass']  = '';
    $db['name']  = 'seme_framework';
    $db['port'] = '3306';
    $db['charset'] = 'latin1';
    $db['engine'] = 'mysqli';

    $core_prefix = '';
    $core_controller = '';
    $core_model = '';
    $controller_main = '';
    $controller_404 = '';
    $routes = array();
    $semevar = array();

    // apply configuration
    $config_values['baseurl'] = 'https://localhost';
    $config_values['method'] = 'PATH_INFO';
    $config_values['baseurl_admin'] = 'admin';
    $config_values['cdn_url'] = '';
    $config_values['database'] = new stdClass();
    $config_values['saltkey'] = '';
    $config_values['core_prefix'] = $core_prefix;
    $config_values['core_controller'] = $core_controller;
    $config_values['core_model'] = $core_model;
    $config_values['controller_main'] = $controller_main;
    $config_values['controller_404'] = $controller_404;
    $config_values['timezone'] = 'Asia/Jakarta';
    $config_values['routes'] = array();
    $config_values['semevar'] = array();

    // DB config convert to object
    foreach ($db as $k=>$v) {
      $config_values['database']->{$k} = $v;
    }
    foreach ($semevar as $k=>$v) {
      $config_values['semevar']->{$k} = $v;
    }
    unset($db,$k,$v,$routes,$semevar,$controller_404,$controller_main);

    //convert to object
    $cv = new stdClass();
    foreach ($config_values as $k=>$v) {
      $cv->$k = $v;
    }
    unset($config_values,$k,$v);

    //register config to globals
    $GLOBALS['SEMECFG'] = $cv;
    unset($cv,$semevar,$core_model,$admin_secret_url);

    //include core file
    //require_once $GLOBALS['SEMEDIR']->kero.'Functions.php';
    require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_Engine.php';
    require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_Input.php';
  }
  /**
  * Call protected/private method of a class.
  *
  * @param object &$object    Instantiated object that we will run method on.
  * @param string $methodName Method name to call
  * @param array  $parameters Array of parameters to pass into method.
  *
  * @return mixed Method return.
  */
  public function invokeMethod(&$object, $methodName, array $parameters = array())
  {
    $reflection = new \ReflectionClass(get_class($object));
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);
    return $method->invokeArgs($object, $parameters);
  }
  public function testTitle()
  {
    $tc = new mockSENE_Controller();
    $ts = "Welcome to Seme Framework"; // 4 Kata ..
    $this->invokeMethod($tc, 'setTitle', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getTitle', array()));
    $this->assertNotEquals(strtolower($ts), $this->invokeMethod($tc, 'getTitle', array()));
  }
  public function testDescription()
  {
    $tc = new mockSENE_Controller();
    $ts = "Seme Framework is lightweight PHP MVC Framework for creating small and medium web application with fast delivery"; // 4 Kata ..
    $this->invokeMethod($tc, 'setDescription', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getDescription', array()));
    $this->assertNotEquals(strtolower($ts), $this->invokeMethod($tc, 'getDescription', array()));
  }
  public function testLang()
  {
    $tc = new mockSENE_Controller();
    $ts = 'id-ID';
    $this->invokeMethod($tc, 'setLang', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getLang', array()));
    $this->assertNotEquals(strtolower($ts), $this->invokeMethod($tc, 'getLang', array()));
  }
  public function testRobots()
  {
    $tc = new mockSENE_Controller();
    $ts = 'INDEX,FOLLOW';
    $this->invokeMethod($tc, 'setRobots', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getRobots', array()));
    $this->assertNotEquals(strtolower($ts), $this->invokeMethod($tc, 'getRobots', array()));
    $ts = 'NOINDEX,NOFOLLOW';
    $this->invokeMethod($tc, 'setRobots', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getRobots', array()));
    $this->assertNotEquals(strtolower($ts), $this->invokeMethod($tc, 'getRobots', array()));
    $ts = 'anything';
    $this->invokeMethod($tc, 'setRobots', array($ts));
    $this->assertEquals('NOINDEX,NOFOLLOW', $this->invokeMethod($tc, 'getRobots', array()));
    $this->assertNotEquals(strtolower('NOINDEX,NOFOLLOW'), $this->invokeMethod($tc, 'getRobots', array()));
  }
  public function testAuthor()
  {
    $tc = new mockSENE_Controller();
    $ts = 'Seme Framework';
    $this->invokeMethod($tc, 'setAuthor', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getAuthor', array()));
  }
  public function testIcon()
  {
    $tc = new mockSENE_Controller();
    $ts = 'favicon.ico';
    $this->invokeMethod($tc, 'setIcon', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getIcon', array()));
  }
  public function testShortcutIcon()
  {
    $tc = new mockSENE_Controller();
    $ts = 'favicon.ico';
    $this->invokeMethod($tc, 'setShortcutIcon', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getShortcutIcon', array()));
  }
}
