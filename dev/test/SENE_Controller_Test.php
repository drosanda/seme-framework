<?php declare(strict_types=1);
// Path to run ./vendor/bin/phpunit --bootstrap vendor/autoload.php FileName.php
// Butuh Framework PHPUnit
use PHPUnit\Framework\TestCase;

require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_Controller.php';

class SENE_Controller_Mock extends SENE_Controller {
  public function __construct(){
    parent::__construct();
  }
  public function index(){

  }
}

/**
 * @covers SENE_Controller
 */
final class SENE_Controller_Test extends TestCase
{
  public function __construct(){
    parent::__construct();
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

  /**
   * @uses SENE_Controller_Test
   * @uses SENE_Controller_Mock
   * @covers SENE_Controller
   */
  public function testTitle()
  {
    $tc = new SENE_Controller_Mock();
    $ts = "Welcome to Seme Framework"; // 4 Kata ..
    $this->invokeMethod($tc, 'setTitle', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getTitle', array()));
    $this->assertNotEquals(strtolower($ts), $this->invokeMethod($tc, 'getTitle', array()));
  }

  /**
   * @uses SENE_Controller_Test
   * @uses SENE_Controller_Mock
   * @covers SENE_Controller
   */
  public function testDescription()
  {
    $tc = new SENE_Controller_Mock();
    $ts = "Seme Framework is lightweight PHP MVC Framework for creating small and medium web application with fast delivery"; // 4 Kata ..
    $this->invokeMethod($tc, 'setDescription', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getDescription', array()));
    $this->assertNotEquals(strtolower($ts), $this->invokeMethod($tc, 'getDescription', array()));
  }

  /**
   * @uses SENE_Controller_Test
   * @uses SENE_Controller_Mock
   * @covers SENE_Controller
   */
  public function testLang()
  {
    $tc = new SENE_Controller_Mock();
    $ts = 'id-ID';
    $this->invokeMethod($tc, 'setLang', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getLang', array()));
    $this->assertNotEquals(strtolower($ts), $this->invokeMethod($tc, 'getLang', array()));
  }

  /**
   * @uses SENE_Controller_Test
   * @uses SENE_Controller_Mock
   * @covers SENE_Controller
   */
  public function testRobots()
  {
    $tc = new SENE_Controller_Mock();
    $ts = 'INDEX,FOLLOW';
    $this->invokeMethod($tc, 'setRobots', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getRobots', array()));
    $this->assertNotEquals(strtolower($ts), $this->invokeMethod($tc, 'getRobots', array()));
    $ts = 'NOINDEX,NOFOLLOW';
    $this->invokeMethod($tc, 'setRobots', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getRobots', array()));
    $this->assertNotEquals(strtolower($ts), $this->invokeMethod($tc, 'getRobots', array()));
    $ts = 'INDEX,NOFOLLOW';
    $this->invokeMethod($tc, 'setRobots', array($ts));
    $this->assertEquals('INDEX,NOFOLLOW', $this->invokeMethod($tc, 'getRobots', array()));
    $this->assertNotEquals(strtolower($ts), $this->invokeMethod($tc, 'getRobots', array()));
    $ts = 'INDEX';
    $this->invokeMethod($tc, 'setRobots', array($ts));
    $this->assertEquals('INDEX', $this->invokeMethod($tc, 'getRobots', array()));
    $this->assertNotEquals(strtolower(''), $this->invokeMethod($tc, 'getRobots', array()));
  }

  /**
   * @uses SENE_Controller_Test
   * @uses SENE_Controller_Mock
   * @covers SENE_Controller
   */
  public function testAuthor()
  {
    $tc = new SENE_Controller_Mock();
    $ts = 'Seme Framework';
    $this->invokeMethod($tc, 'setAuthor', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getAuthor', array()));
  }

  /**
   * @uses SENE_Controller_Test
   * @uses SENE_Controller_Mock
   * @covers SENE_Controller
   */
  public function testIcon()
  {
    $tc = new SENE_Controller_Mock();
    $ts = 'favicon.ico';
    $this->invokeMethod($tc, 'setIcon', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getIcon', array()));
  }

  /**
   * @uses SENE_Controller_Test
   * @uses SENE_Controller_Mock
   * @covers SENE_Controller
   */
  public function testShortcutIcon()
  {
    $tc = new SENE_Controller_Mock();
    $ts = 'favicon.ico';
    $this->invokeMethod($tc, 'setShortcutIcon', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getShortcutIcon', array()));
  }

  /**
   * @uses SENE_Controller_Test
   * @uses SENE_Controller_Mock
   * @covers SENE_Controller
   */
  public function testKeyword()
  {
    $tc = new SENE_Controller_Mock();
    $ts = 'Seme Framework';
    $this->invokeMethod($tc, 'setKeyword', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getKeyword', array()));
  }

  /**
   * @uses SENE_Controller_Test
   * @uses SENE_Controller_Mock
   * @covers SENE_Controller
   */
  public function testSession()
  {
    $tc = new SENE_Controller_Mock();
    $ts = 'Seme Framework';
    $this->invokeMethod($tc, 'setKey', array($ts));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getKey', array()));
  }

  /**
   * @uses SENE_Controller_Test
   * @uses SENE_Controller_Mock
   * @covers SENE_Controller
   */
  public function testCookie()
  {
    $tc = new SENE_Controller_Mock();
    $ts = 'is_login';
    $td = '1';
    $this->invokeMethod($tc, 'setcookie', array($ts,$td));
    $this->assertEquals($td, $this->invokeMethod($tc, 'getcookie', array($ts)));
  }

  /**
   * @uses SENE_Controller_Test
   * @uses SENE_Controller_Mock
   * @covers SENE_Controller
   */
  public function testCanonicalEmpty()
  {
    $tc = new SENE_Controller_Mock();
    $td = '';
    $ts = base_url('');
    $this->invokeMethod($tc, 'setcanonical', array($td));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getcanonical', array()));

    $td = '/';
    $ts = base_url('');
    $this->invokeMethod($tc, 'setcanonical', array($td));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getcanonical', array()));

    $td = 'home';
    $ts = ('home/');
    $this->invokeMethod($tc, 'setcanonical', array($td));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getcanonical', array()));

    $td = 'home';
    $ts = ('home/');
    $this->invokeMethod($tc, 'setcanonical', array($td));
    $this->assertEquals($ts, $this->invokeMethod($tc, 'getcanonical', array()));
  }

  /**
   * @uses SENE_Controller_Test
   * @uses SENE_Controller_Mock
   * @covers SENE_Controller
   */
  public function testSetAdditionalCss()
  {
    $tc = new SENE_Controller_Mock();

    $td = 'test1.css';
    $this->invokeMethod($tc, 'setadditional', array($td));
    $this->assertContains($td, $tc->additional);
  }

  /**
   * @uses SENE_Controller_Test
   * @uses SENE_Controller_Mock
   * @covers SENE_Controller
   */
  public function testGetAdditionalCssPlain()
  {
    $tc = new SENE_Controller_Mock();

    $td = 'skin/front/test2.css';
    $ts = 'skin/front/test2.css';
    $this->expectOutputRegex(preg_quote(''.$ts.''),'/');
    $this->invokeMethod($tc, 'setadditional', array($td));
    $this->invokeMethod($tc, 'getcanonical', array());
  }

  /**
   * @uses SENE_Controller_Test
   * @uses SENE_Controller_Mock
   * @covers SENE_Controller
   */
  public function testGetAdditionalCssBaseUrl()
  {
    $tc = new SENE_Controller_Mock();

    $td = '{{base_url}}test3.css';
    $ts = base_url().'test3.css';
    $this->expectOutputRegex(preg_quote(''.$ts.''),'/');
    $this->invokeMethod($tc, 'setadditional', array($td));
    $this->invokeMethod($tc, 'getcanonical', array());
  }

  /**
   * @uses SENE_Controller_Test
   * @uses SENE_Controller_Mock
   * @covers SENE_Controller
   */
  public function testGetAdditionalCssBaseUrlAdmin()
  {
    $tc = new SENE_Controller_Mock();

    $td = '{{base_url_admin}}test4.css';
    $ts = base_url_admin().'test4.css';
    $this->expectOutputRegex(preg_quote(''.$ts.''),'/');
    $this->invokeMethod($tc, 'setadditional', array($td));
    $this->invokeMethod($tc, 'getcanonical', array());
  }

  /**
   * @uses SENE_Controller_Test
   * @uses SENE_Controller_Mock
   * @covers SENE_Controller
   */
  public function testGetAdditionalCssCdnUrl()
  {
    $tc = new SENE_Controller_Mock();

    $td = '{{cdn_url}}test5.css';
    $ts = $this->invokeMethod($tc, 'cdn_url', array()).'test5.css';
    $this->expectOutputRegex(preg_quote(''.$ts.''),'/');
    $this->invokeMethod($tc, 'setadditional', array($td));
    $this->invokeMethod($tc, 'getcanonical', array());
  }
}
