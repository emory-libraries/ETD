<?php

/* database modesl for user information from Emory Shared Data (ESD) view */

class esdPerson {
  /* extends Zend_Db_Table_Row 
    not extending because it causes a weird unknown error
    about __set (php can't find where it's happening)
    -- this may be because the table metadata can't be retrieved and isn't configured properly
  */

  private $alias;
  private $_address;
  
  public function __construct() {
    $this->alias = array("netid" => "LOGN8NTWR_I",
		       "type" => "PRSN_C_TYPE",
		       "lastname" => "PRSN_N_LAST",
		       "firstname" => "PRSN_N_FRST",
		       "middlename" => "PRSN_N_MIDL",
		       "directoryname" => "PRSN_N_FM_DTRY",
		       "department_code" => "DPRT_C",
		       "academic_career" => "ACCA_I",
		       "academic_program" => "ACPR8PRMR_I",
		       "degree" => "DEGR_I",
		       "term_complete" => "TERM8CMPL_I",
		       "email" => "EMAD_N",
		       "id" => "PRSN_I",
		       "department" => "DPRT_N",
		       "academic_plan_id" => "ACPL_I",
		       "academic_plan" => "ACPL_N");
  }

  public function __get($field) {
    switch ($field) {
    case "address":
      // semi-dynamic for esd person address object
      if (isset($this->_address)) { return  $this->_address;  }
      else {
	$addressObj = new esdAddressObject();
	$this->_address =  $addressObj->find($this->id);
	return $this->_address;
      }
    case "name":	// directory name or first and middle name 
      if ($this->directoryname)
	return $this->directoryname;
      else
	return $this->firstname . " " . $this->middlename;
    case "netid":
      return strtolower($this->{$this->alias["netid"]});
    default:
      return $this->{$this->alias[$field]};
    }
  }

  public function __toString() {
    return $this->firstname;
  }


}

class esdPeople extends Zend_Db_Table_Rowset {}

class esdPersonObject extends Emory_Db_Table {
  //  protected $_name           = 'ESDV.v_etd_prsn';	// table name
  protected $_name           = 'v_etd_prsn';	// table name
  protected $_schema         = 'ESDV';	
  protected $_rowsetClass    = 'esdPeople';  
  protected $_rowClass       = 'esdPerson';  
  protected $_primary        = 'PRSN_I';    	// person ID within ESD

  /* object associations */

  /*  protected $_referenceMap = array(
        'Address'         => array(
            'columns'        => 'PRSN_I',          // class Key
            'refTableClass' => 'esdAddress',    // class reference
            'refColumns'    => 'PRSN_I'    // foreign Key
	    ),  // will evaluate as refTableClass->columns = refColumns
	    );*/


  public function _setupMetadata() {
    $this->_metadata = array("LOGN8NTWR_I" => array("DATA_TYPE" => "int", "NULLABLE" => true, "DEFAULT" => null),
			     "PRSN_C_TYPE" => array("DATA_TYPE" => "char"),
			     "PRSN_N_LAST" => array("DATA_TYPE" => "char"),
			     "PRSN_N_FRST" => array("DATA_TYPE" => "char"),
			     "PRSN_N_FM_DTRY" => array("DATA_TYPE" => "char"),
			     "PRSN_N_MIDL" => array("DATA_TYPE" => "char", "NULLABLE" => true, "DEFAULT" => null),
			     "DPRT_C" => array("DATA_TYPE" => "int", "NULLABLE" => true, "DEFAULT" => null),
			     "ACCA_I" => array("DATA_TYPE" => "int", "NULLABLE" => true, "DEFAULT" => null),
			     "ACPR8PRMR_I" => array("DATA_TYPE" => "int", "NULLABLE" => true, "DEFAULT" => null),
			     "DEGR_I" => array("DATA_TYPE" => "int", "NULLABLE" => true, "DEFAULT" => null),
			     "TERM8CMPL_I" => array("DATA_TYPE" => "int", "NULLABLE" => true, "DEFAULT" => null),
			     "EMAD_N" => array("DATA_TYPE" => "char", "NULLABLE" => true, "DEFAULT" => null),
			     "PRSN_I" => array("DATA_TYPE" => "int", "NULLABLE" => true, "DEFAULT" => null),
			     "DPRT_N" => array("DATA_TYPE" => "int", "NULLABLE" => true, "DEFAULT" => null),
			     "ACPL_I" => array("DATA_TYPE" => "char", "NULLABLE" => true, "DEFAULT" => null),
			     "ACPL_N" => array("DATA_TYPE" => "char", "NULLABLE" => true, "DEFAULT" => null)
			     );
    
    $this->_cols = array_keys($this->_metadata);
  }

  public function _setupPrimaryKey() {
  }

  public function findByUsername($netid) {
    $sql = "select * from ESDV.v_etd_prsn where LOGN8NTWR_I = ?";
    $stmt = $this->_db->query($sql, array(strtoupper($netid)));
    return $stmt->fetchObject("esdPerson");
  }



  public function match_faculty($name) {
    $sql = "SELECT * FROM ESDV.v_etd_prsn WHERE PRSN_C_TYPE='F' ";

    $name = strtolower($name);	// convert to lower case for case-insensitive comparison
    // FIXME: handle multiple names - split on spaces and search for both names
    $names = split(' ', $name);

    foreach ($names as $n) {
      if (trim($n) == "") continue;	// skip blanks (multiple spaces)

      $uname = "%$n%";       
      $where_sql = " AND (LOWER(PRSN_N_LAST) LIKE ? OR LOWER(PRSN_N_FRST) LIKE ? OR
			LOWER(PRSN_N_MIDL) LIKE ? OR LOWER(PRSN_N_FM_DTRY) LIKE ?) ";
      $sql .= $this->getAdapter()->quoteInto($where_sql, $uname);
    }

    $sql .= " ORDER BY PRSN_N_LAST";	// sort by last name
    $sql = "SELECT * FROM ( $sql ) WHERE ROWNUM <= 25";	// limit results to first 25
    // (oracle notation for limiting results)

    $sql = $this->getAdapter()->quoteInto($sql, $uname);
    // NOTE: Zend OCI quote class doesn't quote apostrophes correctly - fix them here
    $sql = str_replace("\'", "''", $sql);
    $stmt = $this->_db->query($sql);

    // build an array of esdPersons (better way to do this?)
    $result = array();
    while ($obj =  $stmt->fetchObject("esdPerson")) {
      $result[] = $obj;
    }
    
    return $result;
  }
  

  
  /*  public function findByUsername($netid) {
    $db = $this->getAdapter();
    $sql = "select * from ESDV.v_etd_prsn where LOGN8NTWR_I = '?'";
    $stmt = $db->query($sql, array(strtoupper($netid)));

    $where = $db->quoteInto($db->quoteIdentifier("LOGN8NTWR_I").' = ?', $netid);    
    return $this->fetchAll($where)->current();
    }*/

  
}


/* fixme: need to split out addresses into manageable groups */

class esdAddress {
  private $alias;
  
  public function __construct() {
    $this->alias = array("person_id" => "PRSN_I",
			 "local_line1" => "PRAD_A_LOCL_LIN1",
			 "local_line2" => "PRAD_A_LOCL_LIN2",
			 "local_line3" => "PRAD_A_LOCL_LIN3",
			 "local_city" => "PRAD_A_LOCL_CITY",
			 /*			     "PRAD_A_LOCL_STAT"
			     "PRAD_A_LOCL_ZIP" 
			     "PRAD_A_LOCL_CNTRY,
			     "PRAD_A_LOCL_TLPH",
			     
			     "PRAD_A_STDN_LIN1"
			     "PRAD_A_STDN_LIN2"
			     "PRAD_A_STDN_LIN3"
			     "PRAD_A_STDN_CITY"
			     "PRAD_A_STDN_STAT"
			     "PRAD_A_STDN_ZIP" 
			     "PRAD_A_STDN_CNTRY",
			     "PRAD_A_STDN_TLPH"*/


			 );
  }

  public function __get($field) {
    return $this->{$this->alias[$field]};
  }
}

class esdAddresses extends Zend_Db_Table_Rowset {}

class esdAddressObject extends Emory_Db_Table {
  protected $_name           = 'v_etd_prad';	// table name
  protected $_schema         = 'ESDV';
  protected $_rowsetClass    = 'esdAddresses';  
  protected $_rowClass       = 'esdAddress';  
  protected $_primary        = 'PRSN_I';    	// person ID within ESD


  public function _setupMetadata() {
    $this->_metadata = array("PRSN_I" => array("DATA_TYPE" => "int"),
			     "PRAD_A_LOCL_LIN1" => array("DATA_TYPE" => "char"),
			     "PRAD_A_LOCL_LIN2" => array("DATA_TYPE" => "char"),
			     "PRAD_A_LOCL_LIN3" => array("DATA_TYPE" => "char"),
			     "PRAD_A_LOCL_CITY" => array("DATA_TYPE" => "char"),
			     "PRAD_A_LOCL_STAT" => array("DATA_TYPE" => "char"),
			     "PRAD_A_LOCL_ZIP" => array("DATA_TYPE" => "char"),
			     "PRAD_A_LOCL_CNTRY" => array("DATA_TYPE" => "char"),
			     "PRAD_A_LOCL_TLPH" => array("DATA_TYPE" => "char"),
			     
			     "PRAD_A_STDN_LIN1" => array("DATA_TYPE" => "char"),
			     "PRAD_A_STDN_LIN2" => array("DATA_TYPE" => "char"),
			     "PRAD_A_STDN_LIN3" => array("DATA_TYPE" => "char"),
			     "PRAD_A_STDN_CITY" => array("DATA_TYPE" => "char"),
			     "PRAD_A_STDN_STAT" => array("DATA_TYPE" => "char"),
			     "PRAD_A_STDN_ZIP" => array("DATA_TYPE" => "char"),
			     "PRAD_A_STDN_CNTRY" => array("DATA_TYPE" => "char"),
			     "PRAD_A_STDN_TLPH" => array("DATA_TYPE" => "char"),
			     );
    
    $this->_cols = array_keys($this->_metadata);
  }

  public function _setupPrimaryKey() {
  }

  public function find($id) {
    $db = $this->getAdapter();
    $sql = "select * from ESDV.v_etd_prad where PRSN_I = ?";
    $stmt = $this->_db->query($sql, array($id));
    return $stmt->fetchObject("esdAddress");
  }


}
