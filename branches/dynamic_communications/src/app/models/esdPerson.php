<?php
/**
 * @category Etd
 * @package Etd_Models
 * @subpackage ESD
 */

/* database modesl for user information from Emory Shared Data (ESD) view */
require_once("etd.php");

class esdPerson extends Emory_Db_Table_Row implements Zend_Acl_Role_Interface {
    /**
     *
     * @var esdAddressObject
     */
    private $_address;
    /**
     * base role for ACL
     * @var string
     */
    public $role;
    /**
     * array of unpublished etds that belong to this user
     * @var array
     */
    private $_etds;

    /**
     * hashed version of user's password for Fedora access
     * @var string
     */
    private $password;

    public function __construct(array $config = array()) {
      parent::__construct($config);
      
        // alias ESD field names to something comprehensible
        $this->column_alias = array("netid" => "LOGN8NTWR_I",
             "type" => "PRSN_C_TYPE",
             "lastname" => "PRSN_N_LAST",
             "firstname" => "PRSN_N_FRST",
             "middlename" => "PRSN_N_MIDL",
             "directory_name" => "PRSN_N_FM_DTRY",
	     "directory_title" => "PRSN_E_TITL_DTRY",
             "department_code" => "DPRT_C",
             "academic_career" => "ACCA_I",
             "academic_program" => "ACPR8PRMR_I",
             "degree" => "DEGR_I",
             "term_complete" => "TERM8CMPL_I",
             "email" => "EMAD_N",
             "id" => "PRSN_I",
             "department" => "DPRT_N",
             "academic_plan_id" => "ACPL_I",
             "academic_plan" => "ACPL_N",
             /** y/n flag for undergrad honors program **/
             "honors_student" => "STDN_F_HONR",
             /* graduate program coordinator
                - if not null, user is a program coordinator
                - field contains the program name
              */
             "program_coord" => "ACPL8GPCO_N",
             "grad_coord" => "ACPL8GPCO_N",

            // flag for current/former faculty (Y/N)
             "current_faculty" => "PRSN_F_FCLT_CRNT",
        );

        // determine the user's role in the system
       if (isset($this->type)) $this->setRole();

       $this->_etds = null;
    }


    public function setRole() {
        /*  person type codes
         * FIXME: move into an object/constants for direct use here & in queries
         PRSN_C_TYPE PRSN_E_TYPE
         A           Administrative
         B           Student/Staff  (usually student employees)
         C           Staff/Student  (usually staff taking courses)
         D           Future Hire Effective Date
         E           Staff
         F           Faculty
         H           HealthCare Only
         P           Sponsored
         R           Retired
         S           Student
         U           Unknown
         X           Pre-start
        */

        // set base role for access controls
        switch ($this->type) {
        case "B":
        case "S":
        case "C":
            $this->role = "student"; break;
        case "F":
            $this->role = "faculty"; break;
        case "E":
            $this->role = "staff"; break;
        default:
            $this->role = "guest";		// default role
        }

        // subset of standard student role - if honors flag is set
        if ($this->role == "student" && $this->honors_student == "Y") {
            $this->role = "honors student";
        }

	// detect if user is a school-specific admin - determined by school config
	$school_cfg = Zend_Registry::get("schools-config");
	$admin_type = $school_cfg->isAdmin($this);
	if ($admin_type) $this->role = $admin_type . " admin";

        /*** special roles that must be set in config file ***/
        // etd superuser, techsupport, and honors admin
        if (Zend_Registry::isRegistered('config')) {
            $config = Zend_Registry::get('config');
            if (isset($config->superusers) && ((is_object($config->superusers->user) &&
            in_array($this->netid, $config->superusers->user->toArray()))
            || $config->superusers->user == $this->netid)) {
                    $this->role = "superuser";
                    //Workaround to set  default school to solve issue when superuser submits a file
                    $this->academic_career="GSAS";
            }
            elseif (isset($config->techsupport) && ((is_object($config->techsupport->user) &&
             in_array($this->netid, $config->techsupport->user->toArray()))
            || $config->techsupport->user == $this->netid)) {
                    $this->role = "techsupport";
                //Workaround to set  default school to solve issue when superuser submits a file
                $this->academic_career="GSAS";
            }
            //detect report viewer role
            elseif (isset($config->reportviewer) &&  ((is_object($config->reportviewer->department) &&
             in_array($this->department, $config->reportviewer->department->toArray()))
            || (!empty($config->reportviewer->department) && $config->reportviewer->department == $this->department))){
                $this->role = "report viewer";
            }

        } //end Zend registry if
    }

    /**
     * override magic __get function for some custom / constructed fields
     * @param string $field
     */
    public function __get($field) {        
      switch ($field) {
        case "address":
	  // semi-dynamic for esd person address object
	  if (! isset($this->_address)) {	// if not yet set, retrieve address
	    try {
	      $this->_address = $this->findEsdAddressObject()->current();
	    } catch (Zend_Db_Table_Row_Exception $e) {
	      // db is not connected - should only happen when testing
	      $this->_address = null;
	    }
	  }
	  return $this->_address;
	  // convenience attributes for two versions of full name
        case "fullname":	// directory name or first and middle name followed by last name
	  return $this->name . " " . $this->lastname;
        case "lastnamefirst":	// lastname, firstname + middle OR lastname, directoryname
	  return $this->lastname . ", " . $this->name;
        case "name":	// directory name or first and middle name
            // FIXME: directory name doesn't seem to be working properly in some cases....
            if (trim($this->directory_name) != "") {
                return $this->directory_name;
            } else {
                $name = $this->firstname;
                if ($this->middlename)  $name .= " " . $this->middlename;
                return $name;
            }
        case "netid":	// netid is stored in all caps, but we will always use it lower case
	  // can't rely on netid for former faculty, using esd dbid as pseudo-netid instead
	  if (isset($this->current_faculty) && $this->current_faculty == "N")
	    return "esdid" . $this->id;
	  return strtolower(parent::__get("netid"));
        default:
	  return parent::__get($field);
      }
    }

    /**
     * extend default magic set function to handle address object
     * @param string $field
     * @param $value
     */
    public function __set($field, $value) {
      if ($field == "address") $this->_address = $value;
      else parent::__set($field, $value);
    }


   /**
     * customize which elements should be saved for serialization (exclude etd objects)
     * @return array
     */
    public function __sleep() {
        $this->_etds = null;	// foxml objects don't recover from serialization, so reset
        $myfields = array("_address", "role", "_etds", "password");
        return array_merge($myfields, parent::__sleep());
    }

    /**
     * when printing object, will display firstname or netid if name is not available
     * @return string
     */
    public function __toString() {
      if (! empty($this->firstname)) return $this->firstname;
      else return $this->netid;
    }

    /**
     * determine if a user is the graduate program coordinator of the specified program
     * (if program is blank, is automatically false)
     * @param string $program program name
     * @return boolean
     */
    public function isCoordinator($program) {
      return ($program != "" && isset($this->grad_coord) && $this->grad_coord == $program);
    }

    /**
     * Certain (admin) roles have a generic agent label used for the
     * descriptive history line when they perform actions on an ETD. Get
     * the generic label here based on user's role.
     * Updated Nov. 2009 - generic agent label now pulled from school configuration.
     * @return string
     */
    public function getGenericAgent() {
      $school_cfg = Zend_Registry::get("schools-config");
      // if role is a school-specific admin, return configured label for that school
      if ($pos = strpos($this->role, " admin")) {
	$admin_type = substr($this->role, 0, $pos);
	$school = $school_cfg->getSchoolByAclId($admin_type);
	if ($school) return $school->label;
      }

      // fall-back to more generic agent names
      switch ($this->role) {
      case "admin":
      case "superuser":
	// generic administrator label (for superuser or non-school specific admin)
	return "ETD Administrator";
      default:
	trigger_error("This role (" . $this->role . ") does not have a generic agent defined",
		      E_USER_WARNING);
	return "?";
      }
    }


    /**
     * retrieve the school config id for the school that a student belongs to
     * @return string|null school id on success, null if no match is found
     * @todo may want an equivalent getSchoolConfig function...
     */
    public function getSchoolId() {
      $school_cfg = Zend_Registry::get("schools-config");
      // if academic career is set and not empy
      if (isset($this->academic_career) && $this->academic_career) {
	return $school_cfg->getIdByDatabaseId($this->academic_career);
      }
      return null;
    }

    

    /**
     * check if this user has an unpublished etd (if not student or faculty, assumed false)
     * @return boolean
     */
    public function hasUnpublishedEtd() {
        // only student and faculty roles can have unpublished etds
        if (! preg_match("/^(student|faculty|honors student)/", $this->role)) // with or without submission
            return false;
        elseif (count($this->getEtds())) return true;
            else return false;
    }
    
    /**
     * find any unpublished etds that belong to this user
     * @return Array of etd
     */
    public function getEtds() {
        if (is_null($this->_etds)) {	// only initialize once (will be reset after serialization)
            $etdSet = new EtdSet();
            $etdSet->findUnpublishedByOwner($this->netid);
            $this->_etds = $etdSet->etds;
        }
        return $this->_etds;
    }

    /**
     * get user's role - this function allows esdPerson to act as a Zend_Acl_Role
     * @return string
     */
    public function getRoleId(){
        if (preg_match("/student$/", $this->role) || $this->role == "faculty") {
            if ($this->hasUnpublishedEtd())
            $this->role .= " with submission";
        }
        return $this->role;
    }

    /**
     * store base64 encoded password for accessing fedora
     * (base64 encoded to avoid inadvertently outputting clear-text password)
     * @param string $password
     */
    public function setPassword($password) {
        $this->password = base64_encode($password);
    }

    /**
     * decode & retrieve stored password
     * @return string
     */
    public function getPassword() {
        return base64_decode($this->password);
    }

    /**
     * create an esdPerson with all fields present but empty - convenience function for testing
     * @return esdPerson
     */
    public function getTestPerson($data = array()) {
      foreach ($this->column_alias as $alias => $column) {
	if (! isset($data[$column])) $data[$column] = "";
      }
      $test_person = new esdPerson(array("data" => $data));
      // set table to null to avoid errors about not being connected
      $test_person->setTable();
      return $test_person;
    }

}   // end esdPerson

class esdPeople extends Emory_Db_Table_Rowset {}

class esdPersonObject extends Emory_Db_Table {
    protected $_name           = 'V_ETD_PRSN_TABL';
    /* NOTE: v_etd_prsn_tabl is a nightly dump of the data on proxy esd,
       rather than using the view (v_etd_prsn) which was too slow for searching */
    protected $_rowsetClass    = 'esdPeople';
    protected $_rowClass       = 'esdPerson';
    protected $_primary        = 'PRSN_I';

    protected $_dependentTables = array('esdAddressObject');
    
    // customize behavior of magic findBy* function
    protected $uppercase_fields = true;

    public function __construct($config = array()) {
      $esdconfig = Zend_Registry::get('esd-config');
      // setting schema based on config (test DB different than production)
      if ($esdconfig->dbSchema) $this->_schema = $esdconfig->dbSchema;
      parent::__construct($config);
    }

    /**
     * find a person by network login
     * @param string $netid
     * @return esdPerson
     */
    public function findByUsername($netid) {
        // for former faculty, using ESD id as a pseudo-netid, since netid not reliably available
        if (preg_match("/^esdid[0-9]+$/", $netid)) {
            $id = preg_replace("/^esdid/", "", $netid);
            return $this->findByPrsnI($id);
        }
	
	// student employees currently have *two* records in ESD, and only one has the information we need
	// get all records from ESD for the current user, then return the right one if more than one are found
	$results = $this->findAllByLogn8ntwrI(strtoupper($netid));
	if ($results->count() == 1) {
	  return $results->current();
	} elseif ($results->count() > 1) {
	  foreach ($results as $record) {
	    if ($record->academic_career != null) return $record;
	  }	  
	}
	// nothing returned when no match
    }

    /**
     * find a person by ESD id
     * @param int $id
     * @return esdPerson
     */
    public function findById($id) {
        return $this->findByPrsnI($id);
    }

   /**
    * create a minimal esdPerson when ESD is unavailable
    * @param string $netid
    * @param return esdPerson
    */
    public function initializeWithoutEsd($netid) {
      $person = new esdPerson();
      $user = $person->getTestPerson(array(
		  "LOGN8NTWR_I" => $netid,
		  "PRSN_C_TYPE" => "U",            // unknown type - needed for role
		  ));
      return $user;
    }

  /**
   * find a list of matching faculty based on name - used for suggestor
   * @param string $name text to match
   * @param boolean $current search for current or former faculty (default: current)
   * @return array of esdPerson objects
   */
   public function match_faculty($name, $current = true) {
        $db = $this->getAdapter();
        // NOTE: this table is a dump of the data local on proxy esd,
        // rather than using the view which was too slow for a suggestor

        $current_flag = $current ? "Y" : "N";
        return $this->_findByName($name, 25, array("current_flag" => $current_flag));       
    }

    /**
     * Search for faculty by name - current faculty only, returns only one
     * @param string $name
     * @return esdPerson
     */
    public function findFacultyByName($name) {
        // seach for persons with type code F for faculty
        return $this->_findByName($name, 1, array("person_type" => "F"));
    }
    
    //$current_flag = null, $person_type = null
    private function _findByName($name, $limit, $opts = array()) {
        $db = $this->getAdapter();        
        $select = new Zend_Db_Table_Select($this);

        $where = "";
        // sanitize name for searching - ignore commas, do case-insensitive comparison
        $name = strtolower(str_replace(",", "", $name));
        // handle multiple names - split on spaces and search for all names
        $names = preg_split('|\s|', $name, null, PREG_SPLIT_NO_EMPTY);

        for ($i = 0; $i < count($names); $i++) {
            if ($i != 0) $where .= " AND ";
            $where .= $db->quoteInto(" (LOWER(PRSN_N_LAST) LIKE ? OR LOWER(PRSN_N_FRST) LIKE ? OR
                LOWER(PRSN_N_MIDL) LIKE ? OR LOWER(PRSN_N_FM_DTRY) LIKE ?) ", "%" . $names[$i] . "%");
        }
        // optional filters if specified        
        // if set, filter on current faculty using flag passed in
        if (isset($filters["current_flag"])) {
            $where .= $db->quoteInto(" AND PRSN_F_FCLT_CRNT = ?", $filters["current_flag"]);
        }
        // if set, filter on person type
        if (isset($filters["person_type"])) {
            $where .= $db->quoteInto(" AND PRSN_C_TYPE= ?",  $filters["person_type"]);
        }        
        
        $select->where($where);
        $select->order("PRSN_N_LAST");  // sort by last name
        if ($limit == 1) {
            return $this->fetchAll($select)->current();
        } else {
            $select->limit($limit);           // limit results to number specified
            return $this->fetchAll($select);
        }
    }

}

class esdAddressInfo extends Emory_Db_Table_Row {

    public $current;
    public $permanent;

    public function __construct($config = array()) {
      parent::__construct($config);
      $this->column_alias = array("person_id" => "PRSN_I",
            "local_line1" => "PRAD_A_LOCL_LIN1",
            "local_line2" => "PRAD_A_LOCL_LIN2",
             "local_line3" => "PRAD_A_LOCL_LIN3",
             "local_city" => "PRAD_A_LOCL_CITY",
             "local_state" => "PRAD_A_LOCL_STAT",
             "local_zip" => "PRAD_A_LOCL_ZIP",
             "local_country" => "PRAD_A_LOCL_CNTR",
             "local_telephone" => "PRAD_A_LOCL_TLPH",
             "perm_line1" => "PRAD_A_STDN_LIN1",
             "perm_line2" => "PRAD_A_STDN_LIN2",
             "perm_line3" => "PRAD_A_STDN_LIN3",
             "perm_city"  => "PRAD_A_STDN_CITY",
             "perm_state" => "PRAD_A_STDN_STAT",
             "perm_zip"   => "PRAD_A_STDN_ZIP" ,
             "perm_country" => "PRAD_A_STDN_CNTR",
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
        if (isset($this->{$prefix . "_city"})) $address->city = $this->{$prefix . "_city"};
	if (isset($this->{$prefix . "_state"})) $address->state = $this->{$prefix . "_state"};
        if (isset($this->{$prefix . "_zip"})) $address->zip = $this->{$prefix . "_zip"};
        if (isset($this->{$prefix . "_country"})) $address->country = $this->{$prefix . "_country"};
        if (isset($this->{$prefix . "_telephone"})) $address->telephone = $this->{$prefix . "_telephone"};
    }
}

/* convenience class to access the permanent & current addresses in a more usable form */
class esdAddress {
  public $street;	// array
    public $city;
    public $state;
    public $zip;
    public $country;
    public $telephone;
}

class esdAddresses extends Zend_Db_Table_Rowset {}

class esdAddressObject extends Emory_Db_Table {
    protected $_name           = 'V_ETD_PRAD';	// table name
    protected $_rowsetClass    = 'esdAddresses';
    protected $_rowClass       = 'esdAddressInfo';
    protected $_primary        = 'PRSN_I';    	// person ID within ESD

    /** define relation between person and address **/
    protected $_referenceMap = array('person' => array(
						       'columns' => array('PRSN_I'),
						       'refTableClass' => 'esdPersonObject')
				     );
    
    protected $uppercase_fields = true;

    public function __construct($config = array()) {
      $esdconfig = Zend_Registry::get('esd-config');
      // setting schema based on config (test DB different than production)
      if ($esdconfig->dbSchema) $this->_schema = $esdconfig->dbSchema;
      parent::__construct($config);
    }

    public function find($id) {
	return $this->findByPrsnI($id);
    }
}
