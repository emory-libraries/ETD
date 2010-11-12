<?php

class etd_test_data {
  private $db;
  public $table;
  public $fields;
  public $data;

  public function __construct() {
    $this->db = Zend_Registry::get("etd-db");
    $this->table = "etd_admins";

    $this->fields = array('netid', 'schoolid', 'programid');
    
    $this->data = array(
    array('EPUSR', 'PUBH', 'RSPH-AEPI'),
    array('GHUSR', 'PUBH', 'RSPH-IH'),
    array('ROLL1', 'PUBH', 'RSPH-PS'),
    array('ROLL1', 'PUBH', 'RSPH-MS'),
);
  }

  public function loadAll() {
    // combine field names as array keys, data for values to generate format needed for db insert
    $this->db->query("truncate table {$this->table}");    
    foreach ($this->data as $row) {
      $this->db->insert($this->table, array_combine($this->fields, $row));
    }

  }

  public function cleanUp() {
    $this->db->query("truncate table {$this->table}");
  }
}

?>
