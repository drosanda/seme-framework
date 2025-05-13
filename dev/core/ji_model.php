<?php
class JI_Model extends SENE_Model
{

  public function __construct()
  {
    parent::__construct();
  }
  public function trans_start()
  {
    $r = $this->db->autocommit(0);
    if ($r) {
      return $this->db->begin();
    }
    return false;
  }

  public function trans_commit()
  {
    return $this->db->commit();
  }

  public function trans_rollback()
  {
    return $this->db->rollback();
  }

  public function trans_end()
  {
    return $this->db->autocommit(1);
  }

  public function set($di=array())
  {
    $this->db->flushQuery();
    return $this->db->insert($this->tbl, $di, 0, 0);
  }

  public function update($id, $du)
  {
    if (!is_array($du)) {
      return 0;
    }
    $this->db->where("id", $id);
    return $this->db->update($this->tbl, $du, 0);
  }
  public function del($id)
  {
    $this->db->where("id", $id);
    return $this->db->delete($this->tbl);
  }
}
