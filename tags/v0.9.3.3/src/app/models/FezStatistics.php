<?php

/* simple database object to retrieve statistics information from Fez database */

class FezStatistic extends Zend_Db_Table_Row {

  public function __get($name) {
    // all fields in fez table are prefixed with 'stl_'
    // adding the prefix here so code can be more readable
    if (strpos($name, "stl_") !== 0) {
      $name = "stl_$name";
    }
    return parent::__get($name);
  }
  
}

class FezStatistics extends Zend_Db_Table_Rowset {}

class FezStatisticsObject extends Emory_Db_Table {
  protected $_name           = 'fez_statistics_all';
  protected $_rowsetClass    = 'FezStatistics';  
  protected $_rowClass       = 'FezStatistic';  
  protected $_primary        = 'stl_id';

  
  /**
   * override the default adapter to get the Fez one
   */
  protected function _setupDatabaseAdapter() {
    if (! $this->_db) 
      $this->_db = Zend_Registry::get("fez-db");
  }

  // find statistics by pid
  // optional param to find only records after a specified date
  public function findAllByPid($pid, $date = null) {
    $db = $this->getAdapter();
    $where = $db->quoteInto($db->quoteIdentifier("stl_pid") . ' = ?', $pid);
    if (!is_null($date))
      $where .= $db->quoteInto(' and ' . $db->quoteIdentifier("stl_request_date") . ' > ?', $date);

    return $this->fetchAll($where);
  }
  
}