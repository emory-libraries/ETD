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
      if (isset($row['type']))
        $total[$row['type']] = $row['count'];
    }
    return $total;
  }

  // Fez code is getting two counts and then merging the arrays... there must be a better way!?!
  public function countByCountry(array $pids = null) {
    $abs_sel = $this->selectTypeByCountry($pids, "abstract");
    $file_sel = $this->selectTypeByCountry($pids, "file");
    $sql = "SELECT a.c as country, abstract, file FROM (" . $abs_sel->__toString() . ") as a
	    LEFT OUTER JOIN (" . $file_sel->__toString() . ") AS f ON a.c = f.c
    	    ORDER BY a.abstract DESC;";

    //        print "<pre>" . $sql . "</pre>";
    $stmt = $this->_db->query($sql);
    $result = $stmt->fetchAll();

    // convert nulls to zeroes to simplify display logic
    for ($i = 0; $i < count($result); $i++){
      if (is_null($result[$i]['file'])) $result[$i]['file'] = 0;
      if (is_null($result[$i]['abstract'])) $result[$i]['abstract'] = 0;
    }
    return $result;
    /* select a.country, a.abstract, f.file from (select count(*) as abstract, country
    from stats where type='abstract' group by country) as a
   left join (select count(*) as file, country from stats where type='file' group by country)
   as f on a.country = f.country order by (a.abstract + f.file) DESC;*/

    
  }

  // build a select statement to be used as a subselect
  private function selectTypeByCountry(array $pids = null, $type) {
    $select = $this->_db->select();
    $select->from($this->_name, array("COUNT(*) as $type", "country as c"));
    		// note: have to rename country because field wasn't being found on the join

    // FIXME: pull into sub function - duplicate logic in select type by month
    if (!is_null($pids)) {
      $pid_filter = array();
      //          $sql .= $this->getAdapter()->quoteInto($where_sql, $uname);
      for ($i = 0;  $i < count($pids); $i++) {
	$pid_filter[] = $this->getAdapter()->quoteInto("pid = ?", $pids[$i]);
      }
      
      // need to group (pid1 or pid2) and type, NOT pid1 or pid2 and type
      $where = "(" . implode(" OR ", $pid_filter) . ") AND type = '$type' ";
    } else {
      $where = " type = '$type'";
    }
    
    $select->where($where);
    $select->group("country");

    return $select;
  }


  public function countByMonthYear(array $pids = null) {
    $abs_sel = $this->selectTypeByMonthYear($pids, "abstract");
    $file_sel = $this->selectTypeByMonthYear($pids, "file");
    $sql = "SELECT a.month as month, abstract, file FROM (" . $abs_sel->__toString() . ") as a
	    LEFT OUTER JOIN (" . $file_sel->__toString() . ") AS f ON a.month = f.month
    	    ORDER BY a.month;";

    //        print "<pre>" . $sql . "</pre>";
    $stmt = $this->_db->query($sql);
    $result = $stmt->fetchAll();
    //    print "<pre>" . print_r($result, true) . "</pre>";

    // convert nulls to zeroes to simplify display logic
    for ($i = 0; $i < count($result); $i++){
      if (is_null($result[$i]['file'])) $result[$i]['file'] = 0;
      if (is_null($result[$i]['abstract'])) $result[$i]['abstract'] = 0;
    }
    //    print "<pre>"; print_r($result); print "</pre>";
    return $result;
  }

    // build a select statement to be used as a subselect
  private function selectTypeByMonthYear(array $pids = null, $type) {
    $select = $this->_db->select();
    $select->from($this->_name, array("COUNT(*) as $type", "strftime('%m-%Y', date) as month"));
    // in mysql: date_format(date, '%b %Y')
    		// note: have to rename country because field wasn't being found on the join

    if (!is_null($pids)) {
      $pid_filter = array();
      //          $sql .= $this->getAdapter()->quoteInto($where_sql, $uname);
      for ($i = 0;  $i < count($pids); $i++) {
	$pid_filter[] = $this->getAdapter()->quoteInto("pid = ?", $pids[$i]);
      }
      
      // need to group (pid1 or pid2) and type, NOT pid1 or pid2 and type
      $where = "(" . implode(" OR ", $pid_filter) . ") AND type = '$type' ";
    } else {
      $where = " type = '$type'";
    }
    
    $select->where($where);
    $select->group("month");

    return $select;
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
