<?php
class Blog extends SENE_Controller
{
    public function __construct()
    {
        parent::__construct();
        $GLOBALS['__fixture_ctor_count'] = ($GLOBALS['__fixture_ctor_count'] ?? 0) + 1;
    }

    public function index()
    {
        echo 'blog-index';
    }

    public function show($id = 5)
    {
        echo 'show:'.var_export($id, true);
    }

    public function pair($a, $b)
    {
        echo 'pair:'.var_export($a, true).','.var_export($b, true);
    }

    public function dash_method()
    {
        echo 'dash-ok';
    }

    public function __hidden()
    {
        echo 'hidden-called';
    }

    protected function guarded()
    {
        echo 'guarded-called';
    }
}
