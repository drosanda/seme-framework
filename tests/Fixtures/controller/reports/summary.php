<?php
class Summary extends SENE_Controller
{
    public function index()
    {
        echo 'summary-index';
    }

    public function year($y = '0')
    {
        echo 'summary-year:'.$y;
    }
}
