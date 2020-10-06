<?php

class NotFound extends SENE_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    public function index()
    {
        header("HTTP/1.0 404 Not Found");
        echo 'Notfound';
    }
}
