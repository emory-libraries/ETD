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
    if ($field == "address") {
      if (isset($this->_address)) { return  $this->_address;  }
      else {
	$addressObj = new esdAddressObject();
	$this->_address =  $addressObj->find($this->id);
	return $this->_address;
      }
    } else {
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
    $db = $this->getAdapter();
    $sql = "select * from ESDV.v_etd_prsn where LOGN8NTWR_I = ?";
    $stmt = $this->_db->query($sql, array(strtoupper($netid)));
    return $stmt->fetchObject("esdPerson");
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
