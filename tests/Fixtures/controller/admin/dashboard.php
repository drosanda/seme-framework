<?php
class Dashboard extends SENE_Controller
{
    public function index()
    {
        echo 'dashboard-index';
    }

    public function stats($range = 'day')
    {
        echo 'stats:'.$range;
    }
}
