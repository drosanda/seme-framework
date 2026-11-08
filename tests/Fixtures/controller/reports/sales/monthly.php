<?php
class Monthly extends SENE_Controller
{
    public function index()
    {
        echo 'monthly-index';
    }

    public function show($month = 'jan')
    {
        echo 'monthly-show:'.$month;
    }
}
