<?php

class Home extends SENE_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    public function index()
    {
        echo 'Thank you for using Seme Framework';
    }
}
