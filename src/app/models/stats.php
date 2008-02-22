<?php
/* simple database object for access statistics */


class Stats_Db_Table extends Emory_Db_Table {
  /**
   * override the default adapter to get the stats db
   */
  protected function _setupDatabaseAdapter() {
    if (! $this->_db) $this->_db = Zend_Registry::get("stat-db");
  }
}  

class Stat extends Zend_Db_Table_Row {
}

class Stats extends Zend_Db_Table_Rowset {}

class StatObject extends Stats_Db_Table {
  protected $_name           = 'stats';
  protected $_rowsetClass    = 'Stats';  
  protected $_rowClass       = 'Stat';  
  protected $_primary        = 'id';

  /**
   * override the default adapter to get the stats db
   */
  protected function _setupDatabaseAdapter() {
    if (! $this->_db) 
      $this->_db = Zend_Registry::get("stat-db");
  }
}

class Bot extends Zend_Db_Table_Row {}

class Bots extends Zend_Db_Table_Rowset {}

class BotObject extends Stats_Db_Table {
  protected $_name           = 'bots';
  protected $_rowsetClass    = 'Bots';  
  protected $_rowClass       = 'Bot';  
  protected $_primary        = 'id';

}

class LastRun extends Zend_Db_Table_Row {}

class LastRuns extends Zend_Db_Table_Rowset {}

class LastRunObject extends Stats_Db_Table {
  protected $_name           = 'lastrun';
  protected $_rowsetClass    = 'LastRuns';  
  protected $_rowClass       = 'LastRun';  
  protected $_primary        = 'id';
}






?>