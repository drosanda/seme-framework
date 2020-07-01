<?php
class A_ApiKey_Model extends SENE_Model
{
    public $tbl = 'a_apikey';
    public $tbl_as = 'ak';
  
    public function __construct()
    {
        parent::__construct();
        $this->db->from($this->tbl, $this->tbl_as);
    }
    public function get()
    {
        return $this->db->get();
    }
}
