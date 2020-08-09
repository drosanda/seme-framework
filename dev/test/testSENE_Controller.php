<?php
// Path to run ./vendor/bin/phpunit --bootstrap vendor/autoload.php FileName.php
// Butuh Framework PHPUnit
use PHPUnit\Framework\TestCase;

//constants
define('ADMIN_URL','localhost');
define('SENEVIEW','../../app/view');

// Class yang mau di TEST.
require_once "../../kero/sine/SENE_Controller.php";
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
    public function testTitle()
    {
        // Kita pakai class yang mau kita test.
        $tc = new mockSENE_Controller();

        // Kita masukan parameter 4 kata, yang harusnya dapat output 4.
        $ts = "My name is Joko"; // 4 Kata ..
        $tc->setTitle($ts);
        $tc->getTitle();

        // Kita assert equal, ekspektasi nya harus 4, jika benar berarti Wordcount berfungsi dengan baik.
        $this->assertEquals($ts, $tc->getTitle());
    }
}
