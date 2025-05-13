<?php
class Blog extends \SENE_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->setTheme('front');
    }
    public function index()
    {
        $data = array();
        $this->setTitle("List of Blog Articles");
        $this->putThemeContent("blog/home", $data);
        $this->loadLayout('single_column', $data);
        $this->render();
    }
    public function detail($id="")
    {
        $data = array();
        $data['id'] = (int) $id;
        $this->setTitle("A Blog Detail with specified ID");
        $this->putThemeContent("blog/detail", $data);
        $this->loadLayout('single_column', $data);
        $this->render();
    }
}