<?php

declare(strict_types=1);
// Path to run ./vendor/bin/phpunit --bootstrap vendor/autoload.php FileName.php
// Butuh Framework PHPUnit

require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_Sql.php';
require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_Sql_Select.php';
require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_MySQLi_Engine.php';
require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_Model.php';

class SENE_MySQLi_Engine_Mock extends SENE_MySQLi_Engine
{
    public $db;
    public $config;
    public $__mysqli;
    public function __construct()
    {
        parent::__construct();
    }
}

#[UsesClass('SENE_MySQLi_Engine_Mock')]
#[CoversClass('SENE_MySQLi_Engine')]
#[CoversClass('SENE_Model')]
final class SENE_MySQLi_Engine_Test extends PHPUnit\Framework\TestCase
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
     * @covers SENE_MySQLi_Engine::flushQuery
     * 
     */
    public function testFlushQuery()
    {
        $tc = new SENE_MySQLi_Engine_Mock();
        $this->invokeMethod($tc, 'flushQuery', array());
        $this->assertEquals('', $tc->in_select);
        $this->assertEquals('', $tc->in_where);
        $this->assertEquals('', $tc->in_order);
        $this->assertEquals('', $tc->in_group);
        $this->assertEquals(0, $tc->pagesize);
        $this->assertEquals(0, $tc->page);
        $this->assertEquals(0, $tc->is_limit);
        $this->assertEquals(0, $tc->limit_a);
        $this->assertEquals(0, $tc->limit_b);
        $this->assertEquals(array(), $tc->as_from);
    }

    // /**
    //  * @covers SENE_MySQLi_Engine::flushQuery
    //  * 
    //  */
    // public function testFlushQueryAfter()
    // {
    //     $tc = new SENE_MySQLi_Engine_Mock();
    //     $this->invokeMethod($tc, 'select', array('*'));
    //     $this->invokeMethod($tc, 'from', array('tabel'));
    //     $this->invokeMethod($tc, 'where', array('name','ashley'));
    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->assertEquals('', $tc->in_select);
    //     $this->assertEquals('', $tc->in_where);
    //     $this->assertEquals('', $tc->in_order);
    //     $this->assertEquals('', $tc->in_group);
    //     $this->assertEquals(0, $tc->pagesize);
    //     $this->assertEquals(0, $tc->page);
    //     $this->assertEquals(0, $tc->is_limit);
    //     $this->assertEquals(0, $tc->limit_a);
    //     $this->assertEquals(0, $tc->limit_b);
    //     $this->assertEquals(array(), $tc->as_from);
    // }

    /**
     * @covers SENE_MySQLi_Engine::flushQuery
     * 
     */
    public function testWhereIsNull()
    {
        $tc = new SENE_MySQLi_Engine_Mock();
        $this->invokeMethod($tc, 'flushQuery', array());
        $this->invokeMethod($tc, 'select', array('*'));
        $this->invokeMethod($tc, 'from', array('tabel'));
        $this->invokeMethod($tc, 'where', array('name','IS NULL'));
        $this->assertEquals('`name`  IS NULL  AND ', $tc->in_where);

        $this->invokeMethod($tc, 'flushQuery', array());
        $this->invokeMethod($tc, 'select', array('*'));
        $this->invokeMethod($tc, 'from', array('tabel'));
        $this->invokeMethod($tc, 'where', array('name','Is nUlL'));
        $this->assertEquals('`name`  IS NULL  AND ', $tc->in_where);

        $this->invokeMethod($tc, 'flushQuery', array());
        $this->invokeMethod($tc, 'select', array('*'));
        $this->invokeMethod($tc, 'from', array('tabel'));
        $this->invokeMethod($tc, 'where', array('name','is null'));
        $this->assertEquals('`name`  IS NULL  AND ', $tc->in_where);
    }

    /**
     * @covers SENE_MySQLi_Engine::select
     * @covers SENE_MySQLi_Engine::from
     * @covers SENE_MySQLi_Engine::where
     * 
     */
    public function testWhereIsNotNull()
    {
        $tc = new SENE_MySQLi_Engine_Mock();
        $this->invokeMethod($tc, 'flushQuery', array());
        $this->invokeMethod($tc, 'select', array('*'));
        $this->invokeMethod($tc, 'from', array('tabel'));
        $this->invokeMethod($tc, 'where', array('name','IS NOT NULL'));
        $this->assertEquals('`name`  IS NOT NULL  AND ', $tc->in_where);

        $this->invokeMethod($tc, 'flushQuery', array());
        $this->invokeMethod($tc, 'select', array('*'));
        $this->invokeMethod($tc, 'from', array('tabel'));
        $this->invokeMethod($tc, 'where', array('name','Is not nUlL'));
        $this->assertEquals('`name`  IS NOT NULL  AND ', $tc->in_where);

        $this->invokeMethod($tc, 'flushQuery', array());
        $this->invokeMethod($tc, 'select', array('*'));
        $this->invokeMethod($tc, 'from', array('tabel'));
        $this->invokeMethod($tc, 'where', array('name','is not null'));
        $this->assertEquals('`name`  IS NOT NULL  AND ', $tc->in_where);
    }

    /**
     * @covers SENE_MySQLi_Engine::select
     * @covers SENE_MySQLi_Engine::from
     * @covers SENE_MySQLi_Engine::where
     * 
     */
    public function testWhereAsIsNull()
    {
        $tc = new SENE_MySQLi_Engine_Mock();
        $this->invokeMethod($tc, 'flushQuery', array());
        $this->invokeMethod($tc, 'select', array('*'));
        $this->invokeMethod($tc, 'from', array('tabel'));
        $this->invokeMethod($tc, 'where_as', array('name','IS NULL'));
        $this->assertEquals('name  IS NULL  AND ', $tc->in_where);

        $this->invokeMethod($tc, 'flushQuery', array());
        $this->invokeMethod($tc, 'select', array('*'));
        $this->invokeMethod($tc, 'from', array('tabel'));
        $this->invokeMethod($tc, 'where_as', array('name','Is nUlL'));
        $this->assertEquals('name  IS NULL  AND ', $tc->in_where);

        $this->invokeMethod($tc, 'flushQuery', array());
        $this->invokeMethod($tc, 'select', array('*'));
        $this->invokeMethod($tc, 'from', array('tabel'));
        $this->invokeMethod($tc, 'where_as', array('name','is null'));
        $this->assertEquals('name  IS NULL  AND ', $tc->in_where);
    }

    /**
     * @covers SENE_MySQLi_Engine::where_as
     * @covers SENE_MySQLi_Engine::where
     * 
     */
    public function testWhereAsIsNotNull()
    {
        $tc = new SENE_MySQLi_Engine_Mock();
        $this->invokeMethod($tc, 'flushQuery', array());
        $this->invokeMethod($tc, 'select', array('*'));
        $this->invokeMethod($tc, 'from', array('tabel'));
        $this->invokeMethod($tc, 'where_as', array('name','IS NOT NULL'));
        $this->assertEquals('name  IS NOT NULL  AND ', $tc->in_where);

        $this->invokeMethod($tc, 'flushQuery', array());
        $this->invokeMethod($tc, 'select', array('*'));
        $this->invokeMethod($tc, 'from', array('tabel'));
        $this->invokeMethod($tc, 'where_as', array('name','Is not nUlL'));
        $this->assertEquals('name  IS NOT NULL  AND ', $tc->in_where);

        $this->invokeMethod($tc, 'flushQuery', array());
        $this->invokeMethod($tc, 'select', array('*'));
        $this->invokeMethod($tc, 'from', array('tabel'));
        $this->invokeMethod($tc, 'where_as', array('name','is not null'));
        $this->assertEquals('name  IS NOT NULL  AND ', $tc->in_where);
    }

    // /**
    //  * @covers SENE_MySQLi_Engine::select
    //  * @covers SENE_MySQLi_Engine::from
    //  * @covers SENE_MySQLi_Engine::where
    //  * 
    //  */
    // public function testWhere()
    // {
    //     $tc = new SENE_MySQLi_Engine_Mock();

    //     $str = 'Alice';
    //     $esc = $tc->esc($str);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str));
    //     $this->assertEquals('`name` = '.$esc.'  AND ', $tc->in_where);
    //     $this->assertNotEquals('`name` = '.$str.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str));
    //     $this->assertEquals('`name` = '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str));
    //     $this->assertEquals('`name` = '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND'));
    //     $this->assertEquals('`name` = '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'OR'));
    //     $this->assertEquals('`name` = '.$esc.'  OR ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', ''));
    //     $this->assertEquals('`name` = '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', '='));
    //     $this->assertEquals('`name` = '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', '>'));
    //     $this->assertEquals('`name` > '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', '>='));
    //     $this->assertEquals('`name` >= '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', '<'));
    //     $this->assertEquals('`name` < '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', '<='));
    //     $this->assertEquals('`name` <= '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', '!='));
    //     $this->assertEquals('`name` <> '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', '<>'));
    //     $this->assertEquals('`name` <> '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', 'like'));
    //     $this->assertEquals('`name` LIKE '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', 'like%'));
    //     $this->assertEquals('`name` LIKE "'.$str.'%"  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', '%like'));
    //     $this->assertEquals('`name` LIKE "%'.$str.'"  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', '%like%'));
    //     $this->assertEquals('`name` LIKE "%'.$str.'%"  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', 'like%%'));
    //     $this->assertEquals('`name` LIKE "%'.$str.'%"  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', 'notlike'));
    //     $this->assertEquals('`name` NOT LIKE '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', 'notlike%'));
    //     $this->assertEquals('`name` NOT LIKE "'.$str.'%"  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', '%notlike'));
    //     $this->assertEquals('`name` NOT LIKE "%'.$str.'"  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', '%notlike%'));
    //     $this->assertEquals('`name` NOT LIKE "%'.$str.'%"  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', 'notlike%%'));
    //     $this->assertEquals('`name` NOT LIKE "%'.$str.'%"  AND ', $tc->in_where);

    //     $age = 32;
    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where', array('name',$str,'AND', '=', 1, 0));
    //     $this->invokeMethod($tc, 'where', array('age',$age,'AND', '=', 0, 1));
    //     $this->assertEquals(' ( `name` = '.$esc.'  AND `age` = "'.$age.'"  )  AND ', $tc->in_where);
    // }

    // /**
    //  * @covers SENE_MySQLi_Engine::where_as
    //  * @covers SENE_MySQLi_Engine::where
    //  * 
    //  */
    // public function testWhereAs()
    // {
    //     $tc = new SENE_MySQLi_Engine_Mock();

    //     $str = 'Alice';
    //     $esc = $tc->esc($str);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$str));
    //     $this->assertEquals('name = '.$str.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$str));
    //     $this->assertEquals('name = '.$str.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$esc));
    //     $this->assertEquals('name = '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$esc,'AND'));
    //     $this->assertEquals('name = '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$esc,'OR'));
    //     $this->assertEquals('name = '.$esc.'  OR ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$esc,'AND', ''));
    //     $this->assertEquals('name = '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$esc,'AND', '='));
    //     $this->assertEquals('name = '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$esc,'AND', '>'));
    //     $this->assertEquals('name > '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$esc,'AND', '>='));
    //     $this->assertEquals('name >= '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$esc,'AND', '<'));
    //     $this->assertEquals('name < '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$esc,'AND', '<='));
    //     $this->assertEquals('name <= '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$esc,'AND', '!='));
    //     $this->assertEquals('name <> '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$esc,'AND', '<>'));
    //     $this->assertEquals('name <> '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$esc,'AND', 'like'));
    //     $this->assertEquals('name LIKE '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$str,'AND', 'like%'));
    //     $this->assertEquals("name LIKE \'$str%'  AND ", $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$str,'AND', '%like'));
    //     $this->assertEquals("name LIKE '%".$str."'  AND ", $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$str,'AND', '%like%'));
    //     $this->assertEquals("name LIKE '%".$str."%'  AND ", $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$str,'AND', 'like%%'));
    //     $this->assertEquals("name LIKE '%".$str."%'  AND ", $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$esc,'AND', 'notlike'));
    //     $this->assertEquals('name NOT LIKE '.$esc.'  AND ', $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$str,'AND', 'notlike%'));
    //     $this->assertEquals("name NOT LIKE '$str%'  AND ", $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$str,'AND', '%notlike'));
    //     $this->assertEquals("name NOT LIKE '%".$str."'  AND ", $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$str,'AND', '%notlike%'));
    //     $this->assertEquals("name NOT LIKE '%".$str."%'  AND ", $tc->in_where);

    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$str,'AND', 'notlike%%'));
    //     $this->assertEquals("name NOT LIKE '%".$str."%'  AND ", $tc->in_where);

    //     $age = 32;
    //     $this->invokeMethod($tc, 'flushQuery', array());
    //     $this->invokeMethod($tc, 'where_as', array('name',$esc,'AND', '=', 1, 0));
    //     $this->invokeMethod($tc, 'where_as', array('age',$age,'AND', '=', 0, 1));
    //     $this->assertEquals(' ( name = '.$esc.'  AND age = '.$age.'  )  AND ', $tc->in_where);
    // }
}
