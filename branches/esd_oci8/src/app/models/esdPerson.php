<?php

/* database modesl for user information from Emory Shared Data (ESD) view */
require_once("etd.php");

class esdPerson extends Zend_Db_Table_Row implements Zend_Acl_Role_Interface {
    /**
     * array mapping ESD db column names to usable names
     * @var array
     */
    private $alias;
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
            $this->role = "guest";		// fixme: what should the default be?
        }


        // subset of standard student role - if honors flag is set
        if ($this->role == "student" && $this->honors_student == "Y") {
            $this->role = "honors student";
        }

        // determine roles for special cases
        if ($this->department == "Graduate School Administration") {
            $this->role = "grad admin";	// graduate school administrator
        }
        if (!is_null($this->grad_coord)) {
            // role is graduate program coordinator ?
            // **** - not a separate role but a role in relation to particular ETDs
            // a user should have this role only if grad_coord matches ETD's department field...
        }

        /*** special roles that must be set in config file ***/
        // etd superuser, techsupport, and honors admin
        if (Zend_Registry::isRegistered('config')) {
            $config = Zend_Registry::get('config');
           if (in_array($this->netid, $config->techsupport->user->toArray())) {
                $this->role = "techsupport";
            }
            if (in_array($this->netid, $config->superusers->user->toArray())) {
                $this->role = "superuser";
            }
            if (in_array($this->netid, $config->honors_admin->user->toArray())) {
                $this->role = "honors admin";
            }
        }
    }

    /**
     * override magic __get function to allow accessing by aliased names
     * @param string $field
     */
    public function __get($field) {        
        if (parent::__isset($field)) return parent::__get($field);

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
            if (trim($this->directory_name) != "") {
                return $this->directory_name;
            } else {
                $name = $this->firstname;
                if ($this->middlename)  $name .= " " . $this->middlename;
                return $name;
            }
        case "netid":	// netid is stored in all caps, but we will always use it lower case
            // can't rely on netid for former faculty, using esd dbid as pseudo-netid instead
            if ($this->current_faculty == "N")
                return "esdid" . $this->id;
            return strtolower(parent::__get($this->alias["netid"]));
        default:
            if (isset($this->alias[$field]) && parent::__isset($this->alias[$field]))
                return parent::__get($this->alias[$field]);
            else
                return null;
        }
    }

    /**
     * extend default magic set function to allow setting via aliased column names
     * @param string $field
     * @param $value
     */
    public function __set($field, $value) {
        if (parent::__isset($field)) return parent::__get($field, $value);
        if (isset($this->alias[$field])) return parent::__set($this->alias[$field], $value);
    }

    /**
     * extend default magic osset function to allow checking via aliased column names
     * @param string $field
     * @param $value
     */
    public function __isset($field) {        
        if (parent::__isset($this->alias[$field])) return true;
        else return parent::__isset($field);
    }

   /**
     * customize which elements should be saved for serialization (exclude etd objects)
     * @return array
     */
    public function __sleep() {
        $this->_etds = null;	// foxml objects don't recover from serialization, so reset
        $myfields = array("alias", "_address", "role", "_etds", "password");
        return array_merge($myfields, array_values($this->alias), parent::__sleep());
    }

    /**
     * when printing object, will display firstname or netid if name is not available
     * @return string
     */
    public function __toString() {
        if ($this->firstname) return $this->firstname;
        else return $this->netid;
    }

    /**
     * determine if a user is the graduate program coordinator of the specified program
     * (if program is blank, is automatically false)
     * @param string $program program name
     * @return boolean
     */
    public function isCoordinator($program) {
        return ($program != "" && $this->grad_coord == $program);
    }

    /**
     * Certain (admin) roles have a generic agent label used for the
     * descriptive history line when they perform actions on an ETD. Get
     * the generic label here based on user's role.
     * @return string
     */
    public function getGenericAgent() {
        switch ($this->role) {
        case "honors admin":
            return "the College Honors Program";
        case "grad admin":
            return "the Graduate School";
        case "admin":
        case "superuser":
            // generic administrator (e.g., for superuser)
            // -- should only actually show up in development/testing
            return "ETD Administrator";
        default:
            trigger_error("This role (" . $this->role . ") does not have a generic agent defined",
                    E_USER_WARNING);
            return "?";
        }
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
    public function getTestPerson() {
        $data = array();
        foreach ($this->alias as $alias => $column) $data[$column] = "";
        return new esdPerson(array("data" => $data));
    }

}   // end esdPerson

class esdPeople extends Emory_Db_Table_Rowset {}

class esdPersonObject extends Emory_Db_Table {
    protected $_schema         = 'ESDV';
    protected $_name           = 'V_ETD_PRSN_TABL';
    /* NOTE: v_etd_prsn_tabl is a nightly dump of the data on proxy esd,
       rather than using the view (v_etd_prsn) which was too slow for searching */
    protected $_rowsetClass    = 'esdPeople';
    protected $_rowClass       = 'esdPerson';
    protected $_primary        = 'PRSN_I';

    protected $uppercase_fields = true;

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
	    //	    return $this->findByPRSN_I($id);
        }
	return $this->findByLogn8ntwrI(strtoupper($netid));
	//	return $this->findByLOGN8NTWR_I(strtoupper($netid));
    }

    /**
     * find a person by ESD id
     * @param int $id
     * @return esdPerson
     */
    public function findById($id) {
        return $this->findByPrsnI($id);
	//	return $this->findByPRSN_I($id);
    }

   /**
    * create a minimal esdPerson when ESD is unavailable
    * @param string $netid
    * @param return esdPerson
    */
    public function initializeWithoutEsd($netid) {
        return new esdPerson(array("data" => array(
                    "LOGN8NTWR_I" => $netid,
                    "PRSN_C_TYPE" => "U"            // unknown type - needed for role
                )
            ));
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

class esdAddressInfo extends Zend_Db_Table_Row {
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
        if (parent::__isset($field)) return parent::__get($field);
        if (isset($this->alias[$field]))
            return $this->{$this->alias[$field]};
    }

    /**
     * extend default magic set function to allow setting via aliased column names
     * @param string $field
     * @param $value
     */
    public function __set($field, $value) {
        if (parent::__isset($field)) return parent::__get($field, $value);
        if (isset($this->alias[$field])) return parent::__set($this->alias[$field], $value);
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
}

class esdAddresses extends Zend_Db_Table_Rowset {}

class esdAddressObject extends Emory_Db_Table {
    protected $_name           = 'V_ETD_PRAD';	// table name
    protected $_schema         = 'ESDV';
    protected $_rowsetClass    = 'esdAddresses';
    protected $_rowClass       = 'esdAddressInfo';
    protected $_primary        = 'PRSN_I';    	// person ID within ESD

    protected $uppercase_fields = true;

    public function find($id) {
      //        return $this->findByPRSN_I($id);
	return $this->findByPrsnI($id);
    }
}
