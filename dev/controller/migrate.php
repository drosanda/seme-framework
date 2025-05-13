<?php
class Migrate extends JI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    public function index()
    {
        print_r($this->__getMigrateFiles());
    }
}
