<?php

class Home extends SENE_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load("a_apikey_model", "a");
    }
    public function index()
    {
        print_r($this->a->get());
    }
}
