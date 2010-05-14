<?php

/**
 * @category Etd
 * @package Etd_Models
 * @subpackage authorInfo
 */


require_once("fedora/models/foxml.php");
require_once("fedora/api/risearch.php");

require_once("datastreams/mads.php");
require_once("datastreams/etd_rels.php");
require_once("datastreams/etd_dc.php");

class authorInfo extends foxml {

  /**
   * submission fields that are provided by user object (could be required or optional)
   * @var array
   */
  public $available_fields;

  public function __construct($arg = null, etd $parent = null) {
    parent::__construct($arg);

    if ($this->init_mode == "pid") {
      // no special actions required
    } elseif ($this->init_mode == "template") {
        // add relation to contentModel object
        if (Zend_Registry::isRegistered("config")) {
            $config = Zend_Registry::get("config");
            $this->rels_ext->addContentModel($config->contentModels->author);
        } else {
            trigger_error("Config is not in registry, cannot retrieve contentModel for author");
        }
    }

    // if initialized by etd, that object is passed in - store for convenience
    if (!is_null($parent)) {
      $this->related_objects["etd"] = $parent;
    }


    $this->available_fields = array("name", "email", "permanent email", "permanent address");
				       
  }

  // configure additional datastreams here 
  protected function configure() {
    parent::configure();

    $this->addNamespace("mads", "http://www.loc.gov/mads/");
    
    // add mappings for xmlobject
    $this->xmlconfig["mads"] = array("xpath" => "//foxml:datastream[@ID='MADS']/foxml:datastreamVersion/foxml:xmlContent/mads:mads",
				     "class_name" => "mads", "dsID" => "MADS");

    // Note: no per-object xacml policy is needed here; restrictions handled by a repo-wide policy

    // use customized versions of a few of the default datastreams
    $this->xmlconfig["rels_ext"]["class_name"] = "etd_rels";
    $this->xmlconfig["dc"]["class_name"] = "etd_dc";

    // relation to etd
    $this->relconfig["etd"] = array("relation" => "authorInfoFor", "class_name" => "etd");
  }

  public function __toString() {
    return $this->mads->name->first;
  }

    // handle special values
  public function __set($name, $value) {
    switch ($name) {
    case "name":
      $this->label = $this->dc->title = $value;
      break;
    case "owner":
      // since owner attribute cannot be retrieved from Fedora via APIs, 
      // store author's username in rels-ext as author
      if (isset($this->rels_ext)) {
	if (isset($this->rels_ext->author)) $this->rels_ext->author = $value;
	else $this->rels_ext->addRelation("rel:author", $value);
      }
      // set ownerId property
      parent::__set($name, $value);
      break;
    default:
      parent::__set($name, $value); 
    }
  }

  public function normalizeDates() {
    // normalize date format
    foreach (array("current", "permanent") as $address) {
      if (isset($this->mads->{$address}->date) && $this->mads->{$address}->date)
	$this->mads->{$address}->date = date("Y-m-d", strtotime($this->mads->{$address}->date, 0));
    }
  }

  /**
   * check if this portion of the record is ready to submit and all required fields are filled in
   * @param array $required list of required fields
   * @return boolean ready or not
   */
  public function readyToSubmit(array $required) {
    // if anything is missing, record is not ready to submit
    if (count($this->checkFields($required))) return false;

    // don't attempt to validate until all required fields are filled
    if (! $this->mads->isValid()) {	    // xml should be valid MODS
      // error message?
      return false;
    }      
      
    // all checks passed
    return true;
  }

  

  /**
   * check a list of fields; returns an array of incomplete fields
   * @param array $fields list of fields to check
   * @return array missing fields
   */
  public function checkFields($fields) {
    $missing = array();

    // check everything in the array of required fields
    foreach ($fields as $field) {
      if (! $this->isComplete($field)) $missing[] = $field;
    }

    return $missing;
  }

  /**
   * check all available fields
   * @see user::checkFields
   * @return array 
   */
  public function checkAllFields() {
    return $this->checkFields($this->available_fields);
  }


  
  /**
   * check if a required field is filled in completely (part of submission-ready check)
   * 
   * @param string $field name
   * @return boolean
   */
  public function isComplete($field) {
    switch($field) {
    case "name":
      return ((trim($this->mads->name->first) != "") && (trim($this->mads->name->last) != ""));
    case "email":
      return (trim($this->mads->current->email) != "");
    case "permanent email":
      return (trim($this->mads->permanent->email) != "");
    case "permanent address":
      // for permanent address to be filled in, all these fields must be complete
      return (trim($this->mads->permanent->address->street[0]) != "" &&
	      trim($this->mads->permanent->address->city) != "" &&
	      trim($this->mads->permanent->address->country) != "" &&
	      trim($this->mads->permanent->address->postcode) != "" &&
	      trim($this->mads->permanent->date) != "");

    default:
      trigger_error("Cannot determine if '$field' is complete", E_USER_NOTICE);
    }
  }

  // user's role in relation to this object
  public function getUserRole(esdPerson $user = null) {
    if (is_null($user)) return "guest";
    if (isset($this->rels_ext) && isset($this->rels_ext->author)
	&& ($user->netid == $this->rels_ext->author)) return "author";
    else return $user->role;
  }

  
  
  /**  override default foxml ingest function to use arks for object pids
   */
  public function ingest($message ) {
    $persis = new Emory_Service_Persis(Zend_Registry::get('persis-config'));

    // FIXME: use view/controller to build this url?
    $ark = $persis->generateArk("http://etd.library.emory.edu/author-info/view/pid/emory:{%PID%}", $this->label);
    $pid = $persis->pidfromArk($ark);

    $this->pid = $pid;
    // store the full ark as an additional identifier
    $this->dc->identifier->append($ark);
    
    return $this->fedora->ingest($this->saveXML(), $message);
  }



  public static function find_by_username($netid) {
    $query = 'select $user  from <#ri>
	where  $user <fedora-model:contentModel> \'user\'
        and $user <dc:title> \'' . $netid . '\'';

    // FIXME: what value whould we actually key on? fedora-model:owner is not used (arg)
    // -- possible to set a rels-ext property of owner = netid?
    // and/or additional dc:identifier of netid ?

    // this one doesn't work
    //and $user <fedora-model:owner> \'' . $netid . '\'';

    $pidlist = risearch::query($query);

    if (isset($pidlist->results->result)) {
      $pid = $pidlist->results->result[0]->user["uri"];
      $pid = str_replace("info:fedora/", "", $pid);
      return new authorInfo($pid);
    } else {
      // no matches found
      return null;	// what should the proper response be?
    }
  }
  
}


?>
