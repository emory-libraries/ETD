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

  public function count(array $pids) {
    $select = $this->_db->select();	// newer versions of Zend?   $select = $this->select();
    $select->from($this->_name, array('COUNT(*) as count', 'type'));	// type ?

    for ($i = 0;  $i < count($pids); $i++) {
      if ($i == 0) $select->where('pid = ?', $pids[$i]);
      else $select->orWhere('pid = ?', $pids[$i]);
    }
    $select->group("type");

    $stmt = $this->_db->query($select);
    $result = $stmt->fetchAll();

    $total = array("abstract" => 0, "file" => 0);
    foreach ($result as $row) {
      $total[$row['type']] = $row['count'];
    }
    return $total;
  }

  // Fez code is getting two counts and then merging the arrays... there must be a better way!?!
  public function countByCountry(array $pids) {
    $abs_sel = $this->selectTypeByCountry($pids, "abstract");
    $file_sel = $this->selectTypeByCountry($pids, "file");
    $sql = "SELECT a.c as country, abstract, file FROM (" . $abs_sel->__toString() . ") as a
	    LEFT OUTER JOIN (" . $file_sel->__toString() . ") AS f ON a.c = f.c
    	    ORDER BY (a.abstract + f.file) DESC;";

    //    print "<pre>" . $sql . "</pre>";
    $stmt = $this->_db->query($sql);
    $result = $stmt->fetchAll();
    //    print "<pre>"; print_r($result); print "</pre>";
    
    return $result;
    /* select a.country, a.abstract, f.file from (select count(*) as abstract, country
    from stats where type='abstract' group by country) as a
   left join (select count(*) as file, country from stats where type='file' group by country)
   as f on a.country = f.country order by (a.abstract + f.file) DESC;*/

    
  }

  private function selectTypeByCountry(array $pids, $type) {
    $select = $this->_db->select();
    $select->from($this->_name, array("COUNT(*) as $type", "country as c"));
    		// note: have to rename country because field wasn't being found on the join

    for ($i = 0;  $i < count($pids); $i++) {
      if ($i == 0) $select->where('pid = ?', $pids[$i]);
      else $select->orWhere('pid = ?', $pids[$i]);
    }

    //FIXME: need (1 or 2) and 3 NOT 1 or 2 nd 3
    $select->where('type = ?', $type);
    $select->group("country");
//    $select->order("$type DESC");

    return $select;

    $stmt = $this->_db->query($select);
    $result = $stmt->fetchAll();
    //    print "<pre>"; print_r($result); print "</pre>";
    return $result;
  }

  

  // need another count totals - by month/year (how to do?)
}


/* info to keep track of known robots */
class Bot extends Zend_Db_Table_Row {}

class Bots extends Zend_Db_Table_Rowset {}

class BotObject extends Stats_Db_Table {
  protected $_name           = 'bots';
  protected $_rowsetClass    = 'Bots';  
  protected $_rowClass       = 'Bot';  
  protected $_primary        = 'id';

}

/* info to keep track of when the script was run */
class LastRun extends Zend_Db_Table_Row {}

class LastRuns extends Zend_Db_Table_Rowset {}

class LastRunObject extends Stats_Db_Table {
  protected $_name           = 'lastrun';
  protected $_rowsetClass    = 'LastRuns';  
  protected $_rowClass       = 'LastRun';  
  protected $_primary        = 'id';
}






?>