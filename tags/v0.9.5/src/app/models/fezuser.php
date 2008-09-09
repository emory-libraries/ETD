<?php

  /* simple database object to retrieve user information from Fez database */

class FezUser extends Zend_Db_Table_Row {

  public function __get($name) {
    // all fields in fez table are prefixed with 'usr_'
    // adding the prefix here so code can be more readable
    if (strpos($name, "usr_") !== 0) {
      $name = "usr_$name";
    }
    return parent::__get($name);
  }
  
  
}

class FezUsers extends Zend_Db_Table_Rowset {}

class FezUserObject extends Emory_Db_Table {
  protected $_name           = 'fez_user';
  protected $_rowsetClass    = 'FezUsers';  
  protected $_rowClass       = 'FezUser';  
  protected $_primary        = 'usr_id';

  
  /**
   * override the default adapter to get the Fez one
   */
  protected function _setupDatabaseAdapter() {
    if (! $this->_db) 
      $this->_db = Zend_Registry::get("fez-db");
  }
  
}