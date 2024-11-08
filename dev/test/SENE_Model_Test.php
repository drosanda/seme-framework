<?php declare(strict_types=1);
// Path to run ./vendor/bin/phpunit --bootstrap vendor/autoload.php FileName.php
// Butuh Framework PHPUnit
use PHPUnit\Framework\TestCase;

require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_MySQLi_Engine.php';
require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_Model.php';

class SENE_Model_Mock extends SENE_Model {
  public $db;
  public $config;
  public $__mysqli;
  public function __construct(){
    parent::__construct();
  }
  public function index(){

  }
}

#[UsesClass('SENE_Model_Test')]
#[UsesClass('SENE_Model_Mock')]
#[CoversClass('SENE_MySQLi_Engine')]
#[CoversClass('SENE_Model')]
final class SENE_Model_Test extends TestCase
{
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

  /**
   * 
   * 
   * 
   * 
   */
  public function testDBConnection()
  {
    $tc = new SENE_Model_Mock();
    $this->assertEquals(0,$tc->db->__mysqli->connect_errno);
  }

  /**
   * 
   * 
   * 
   * 
   */
  public function testDBError()
  {
    $tc = new SENE_Model_Mock();
    $this->assertEquals(0,$tc->db->__mysqli->errno);
  }

  /**
   * 
   * 
   * 
   * 
   */
  // public function testEncrypt()
  // {
  //   $tc = new SENE_Model_Mock();
  //   $v  = 'test';
  //   $ek = $tc->config->database->enckey;
  //   $ev = $tc->db->esc($v);
  //   $ex = 'AES_ENCRYPT('.$ev.',"'.$ek.'")';
  //   $this->assertEquals($ex, $this->invokeMethod($tc, '__encrypt', array($v)));
  // }

  /**
   * 
   * 
   * 
   * 
   */
  public function testDecrypt()
  {
    $tc = new SENE_Model_Mock();
    $v  = 'test';
    $ek = $tc->config->database->enckey;
    $ev = $v;
    $ex = 'AES_DECRYPT('.$ev.',"'.$ek.'")';
    $this->assertEquals($ex, $this->invokeMethod($tc, '__decrypt', array($v)));
  }
}
