<?php declare(strict_types=1);
// Path to run ./vendor/bin/phpunit --bootstrap vendor/autoload.php FileName.php
// Butuh Framework PHPUnit
use PHPUnit\Framework\TestCase;

require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_Input.php';

class SENE_Input_Mock extends SENE_Input {
  public function index(){

  }
}

#[UsesClass('SENE_Input_Test')]
#[UsesClass('SENE_Input_Mock')]
#[CoversClass('SENE_Input')]
final class SENE_Input_Test extends TestCase
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
   */
  public function testPost()
  {
    $tc = new SENE_Input_Mock();
    $tk = 'msg';
    $tv = 0;
    $this->assertEquals($tv, $this->invokeMethod($tc, 'post', array($tk)));

    $tk = 'msg';
    $tv = NULL;
    $this->assertEquals($tv, $this->invokeMethod($tc, 'post', array($tk,$tv)));

    $tk = 'msg';
    $tv = 'empty';
    $this->assertEquals($tv, $this->invokeMethod($tc, 'post', array($tk,$tv)));

    $tv = 'Welcome to Seme Framework';
    $this->assertNotEquals($tv, $this->invokeMethod($tc, 'post', array($tk)));
    $_POST[$tk] = $tv;
    $this->assertEquals($tv, $this->invokeMethod($tc, 'post', array($tk)));
  }

  /**
   * 
   * 
   * 
   */
  public function testGet()
  {
    $tc = new SENE_Input_Mock();
    $tk = 'msg';
    $tv = 0;
    $this->assertEquals($tv, $this->invokeMethod($tc, 'get', array($tk)));

    $tk = 'msg';
    $tv = NULL;
    $this->assertEquals($tv, $this->invokeMethod($tc, 'get', array($tk,$tv)));

    $tk = 'msg';
    $tv = 'empty';
    $this->assertEquals($tv, $this->invokeMethod($tc, 'get', array($tk,$tv)));

    $tv = 'Welcome to Seme Framework';
    $this->assertNotEquals($tv, $this->invokeMethod($tc, 'get', array($tk)));

    $_GET[$tk] = $tv;
    $this->assertEquals($tv, $this->invokeMethod($tc, 'get', array($tk)));
  }

  /**
   * 
   * 
   * 
   */
  public function testRequest()
  {
    $tc = new SENE_Input_Mock();
    $tk = 'msg';
    $tv = 0;
    $this->assertEquals($tv, $this->invokeMethod($tc, 'request', array($tk)));

    $tk = 'msg';
    $tv = NULL;
    $this->assertEquals($tv, $this->invokeMethod($tc, 'request', array($tk,$tv)));

    $tk = 'msg';
    $tv = 'empty';
    $this->assertEquals($tv, $this->invokeMethod($tc, 'request', array($tk,$tv)));

    $tv = 'Welcome to Seme Framework';
    $this->assertNotEquals($tv, $this->invokeMethod($tc, 'request', array($tk)));

    $tk = 'msg3';
    $tv = 'Welcome to Seme Framework 3';
    $_REQUEST[$tk] = $tv;
    $this->assertEquals($tv, $this->invokeMethod($tc, 'request', array($tk)));
  }

  /**
   * 
   * 
   * 
   */
  public function testDebug()
  {
    $tc = new SENE_Input_Mock();
    $tv = array("post_param"=>$_POST,"get_param"=>$_GET,"request_param"=>$_REQUEST,"file_param"=>$_FILES);
    $this->assertEquals($tv, $this->invokeMethod($tc, 'debug', array()));

    $_GET['msg1'] = 'test_get';
    $_POST['msg2'] = 'test_post';
    $_REQUEST['msg3'] = 'test_request';
    $tv = array("post_param"=>$_POST,"get_param"=>$_GET,"request_param"=>$_REQUEST,"file_param"=>$_FILES);
    $this->assertEquals($tv, $this->invokeMethod($tc, 'debug', array()));
  }
}
