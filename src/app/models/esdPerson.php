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
			 "directory_name" => "PRSN_N_FM_DTRY",
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
    $this->alias["directory_name"] = "PRSN_N_FM_DTRY";
    switch ($field) {
      
    case "address":
      // semi-dynamic for esd person address object
      if (isset($this->_address)) { return  $this->_address;  }
      else {
	$addressObj = new esdAddressObject();
	$this->_address =  $addressObj->find($this->id);
	return $this->_address;
      }

      // convenience attributes for two versions of full name
    case "fullname":	// directory name or first and middle name followed by last name
	return $this->name . " " . $this->lastname;
    case "lastnamefirst":	// lastname, firstname + middle OR lastname, directoryname
      return $this->lastname . ", " . $this->name;
      
    case "name":	// directory name or first and middle name
      // fixme: directory name doesn't seem to be working properly in some cases....
      if ($this->directory_name) {
	return $this->directory_name;
      } else {
	$name = $this->firstname;
	if ($this->middlename)  $name .= " " . $this->middlename;
	return $name;
      }
    case "netid":	// netid is stored in all caps, but we will always use it lower case
      return strtolower($this->{$this->alias["netid"]});
    default:
      if (isset($this->{$this->alias[$field]}))
	return $this->{$this->alias[$field]};
      else
	return null;
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
			     "PRSN_N_FM_DTRY" => array("DATA_TYPE" => "char", "NULLABLE" => true),
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


  /**
   * find a list of matching faculty based on name - used for suggestor
   */
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



  
  public function findFacultyByName($name) {
    $sql = "SELECT * FROM ESDV.v_etd_prsn WHERE PRSN_C_TYPE='F' ";

    $name = str_replace(".", "", $name);
    // split on spaces and search for both all names
    $names = split(' ', $name);

    foreach ($names as $n) {
      if (trim($n) == "") continue;	// skip blanks (multiple spaces)

      $uname = "%$n%";       
      $where_sql = " AND (PRSN_N_LAST LIKE ? OR PRSN_N_FRST LIKE ? OR
			PRSN_N_MIDL LIKE ? OR PRSN_N_FM_DTRY LIKE ?) ";
      $sql .= $this->getAdapter()->quoteInto($where_sql, $uname);
    }

    // shouldn't need to sort or limit - we only want one match

    $sql = $this->getAdapter()->quoteInto($sql, $uname);
    // NOTE: Zend OCI quote class doesn't quote apostrophes correctly - fix them here
    $sql = str_replace("\'", "''", $sql);
    $stmt = $this->_db->query($sql);
    // rowCount doesn't seem to be accurate - how to determine if there is more than one?

    // only return the first match 
    $result = $stmt->fetchObject("esdPerson");
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

class esdAddressInfo {
  private $alias;

  public $current;
  public $permanent;
  
  public function __construct() {
    $this->alias = array("person_id" => "PRSN_I",
			 "local_line1" => "PRAD_A_LOCL_LIN1",
			 "local_line2" => "PRAD_A_LOCL_LIN2",
			 "local_line3" => "PRAD_A_LOCL_LIN3",
			 "local_city" => "PRAD_A_LOCL_CITY",
			 "local_state" => "PRAD_A_LOCL_STAT",
			 "local_zip" => "PRAD_A_LOCL_ZIP",
			 "local_country" => "PRAD_A_LOCL_CNTRY",
			 "local_telephone" => "PRAD_A_LOCL_TLPH",
			 "perm_line1" => "PRAD_A_STDN_LIN1",
			 "perm_line2" => "PRAD_A_STDN_LIN2",
			 "perm_line3" => "PRAD_A_STDN_LIN3",
			 "perm_city"  => "PRAD_A_STDN_CITY",
			 "perm_state" => "PRAD_A_STDN_STAT",
			 "perm_zip"   => "PRAD_A_STDN_ZIP" ,
			 "perm_country" => "PRAD_A_STDN_CNTRY",
			 "perm_telephone" => "PRAD_A_STDN_TLPH",
			 );

    $this->current = new esdAddress();
    $this->permanent = new esdAddress();

    $this->setAddress("current");
    $this->setAddress("permanent");
  }

  public function setAddress($type) {
    switch ($type) {
    case "current":
      $prefix = "local";
      $address = $this->current;
      break;
    case "permanent":
      $prefix = "perm";
      $address = $this->permanent;
      break;
    default:
      return;	// do nothing
    }
    
    $address->street = array();
    foreach (array(1,2,3) as $i) {
      if ($this->{$prefix . "_line" . $i})
	$address->street[] = $this->{$prefix . "_line" . $i};
    }
    $address->city = $this->{$prefix . "_city"};
    $address->state = $this->{$prefix . "_state"};
    $address->zip = $this->{$prefix . "_zip"};
    $address->country = $this->{$prefix . "_country"};
    $address->telephone = $this->{$prefix . "_telephone"};
  }    
  

  public function __get($field) {
    if (isset($this->alias[$field]))
      return $this->{$this->alias[$field]};
    elseif (isset($this->{$field}))
      return  $this->{$field};
    else
      return null;
  }
}


/* convenience class to access the permanent & current addresses in a more usable form */
class esdAddress {
  public $street;
  public $city;
  public $state;
  public $zip;
  public $country;
  public $telephone;

  public function __construct() {
    
  }
}

class esdAddresses extends Zend_Db_Table_Rowset {}

class esdAddressObject extends Emory_Db_Table {
  protected $_name           = 'v_etd_prad';	// table name
  protected $_schema         = 'ESDV';
  protected $_rowsetClass    = 'esdAddresses';  
  protected $_rowClass       = 'esdAddressInfo';  
  protected $_primary        = 'PRSN_I';    	// person ID within ESD


  public function _setupMetadata() {
    $this->_metadata = array("PRSN_I" => array("DATA_TYPE" => "int"),
			     "PRAD_A_LOCL_LIN1" => array("DATA_TYPE" => "char"),
			     "PRAD_A_LOCL_LIN2" => array("DATA_TYPE" => "char"),
			     "PRAD_A_LOCL_LIN3" => array("DATA_TYPE" => "char"),
			     "PRAD_A_LOCL_CITY" => array("DATA_TYPE" => "char"),
			     "PRAD_A_LOCL_STAT" => array("DATA_TYPE" => "char"),
			     "PRAD_A_LOCL_ZIP" => array("DATA_TYPE" => "char"),
			     "PRAD_A_LOCL_CNTRY" => array("DATA_TYPE" => "char", "NULLABLE" => true),
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
    return $stmt->fetchObject("esdAddressInfo");
  }


}
