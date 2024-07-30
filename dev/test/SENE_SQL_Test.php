<?php

declare(strict_types=1);
// Path to run ./vendor/bin/phpunit --bootstrap vendor/autoload.php FileName.php
// Butuh Framework PHPUnit
use PHPUnit\Framework\TestCase;

require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_Sql.php';
require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_Sql_Select.php';
require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_MySQLi_Engine.php';
require_once $GLOBALS['SEMEDIR']->kero_sine.'SENE_Model.php';

class SENE_SQL_Mock extends SENE_Sql
{
    public $query_string;
    
    public function __construct()
    {
        parent::__construct();
    }
}

#[UsesClass('SENE_SQL_Mock')]
#[CoversClass('SENE_Sql')]
final class SENE_Sql_Test extends TestCase
{
    
    public function testQueryStringNeedReturnString()
    {
        $sql = new SENE_Sql_Mock();
        $this->assertEquals('', $sql->query_string());
    }
    
    public function testResetQueryStringNeedReturnString()
    {
        $sql = new SENE_Sql_Mock();
        $sql->reset();
        $this->assertEquals('', $sql->query_string());
    }
}
