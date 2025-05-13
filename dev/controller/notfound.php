<?php
class Notfound extends JI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    public function index()
    {
        echo 'command not found';
    }
}
