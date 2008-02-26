<?php

require_once("fedora/models/foxml.php");
require_once("fedora/api/fedora.php");
require_once("fedora/api/risearch.php");

require_once("persis.php");

require_once("mads.php");

class user extends foxml {

  public $etds;	// related etd objects
  
  public function __construct($arg = null) {
    parent::__construct($arg);

    if ($this->init_mode == "pid") {
      /*      // initialize mads datastream...
      $dom = new DOMDocument();
      $dom->loadXML($this->fedora->getDatastream($arg, "MADS"));
      $this->map{"mads"} = new mads($dom);*/
    } else {
      $this->cmodel = "user";
    }

    $this->etds = array();

        // FIXME: this only works if user has permissions on rels-ext...
    if ($this->init_mode == "pid") {
      try {
	$this->rels_ext != null;
      } catch  (FedoraAccessDenied $e) {
	// if the current user doesn't have access to RELS-EXT, they don't have full access to this object
	throw new FoxmlException("Access Denied to " . $this->pid); 
      }
      if (isset($this->rels_ext->authorInfoFor)) {
	foreach ($this->rels_ext->authorInfoFor as $etd_pid) {
	  print "pid etd_pid<br/>\n";
	  try {
	    $this->etds[] = new etd($etd_pid);
	  } catch (FedoraAccessDenied $e) {
	    print "Fedora Access Denied: " . $e->getMessage() . "<br>\n";
	    // most users will NOT have access to the author information
	    trigger_error("Access denied to etd $pid", E_USER_NOTICE);
	  }
	}
      }
    }

  }

  // configure additional datastreams here 
  protected function configure() {
    parent::configure();

    $this->addNamespace("mads", "http://www.loc.gov/mads/");
    
    // add mappings for xmlobject
    $this->xmlconfig["mads"] = array("xpath" => "//foxml:datastream[@ID='MADS']/foxml:datastreamVersion/foxml:xmlContent/mads:mads",
				     "class_name" => "mads", "dsID" => "MADS");

    // note: no per-object xacml policy is needed; restrictions handled by a repo-wide policy

    // use customized versions of a few of the default datastreams
    $this->xmlconfig["rels_ext"]["class_name"] = "etd_rels";
    $this->xmlconfig["dc"]["class_name"] = "etd_dc";


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


  public function readyToSubmit() {
    // if anything is missing, record is not ready to submit
    if (count($this->checkRequired())) return false;

    // don't attempt to validate until all required fields are filled
    // (missing research fields is invalid because of the ID attribute)
    if (! $this->mads->isValid()) {	    // xml should be valid MODS
      // error message?
      return false;
    }      
      
    // all checks passed
    return true;
  }

  

  /**
   * check required fields; returns an array with problems, missing data
   * @return array missing fields
   */
  public function checkRequired() {
    $missing = array();

    // permanent non-emory email address
    if ($this->mads->permanent->email == "") {
      $missing[] = "permanent (non-emory) email address";
    }

    // permanent mailing address
    $perm_addr = $this->mads->permanent->address;
    if ($perm_addr->street[0] == "" ||
	$perm_addr->city == "" ||
	$perm_addr->state == "" ||
	$perm_addr->country == "" ||
	$perm_addr->postcode == "" ||
	$this->mads->permanent->date == "") {
      $missing[] = "permanent mailing address";
    }
    
    return $missing;
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
    $persis = new etd_persis();

    // FIXME: use view/controller to build this url?
    $ark = $persis->generateArk("http://etd/user/view/pid/emory:{%PID%}", $this->label);
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
      return new user($pid);
    } else {
      // no matches found
      return null;	// what should the proper response be?
    }
  }
  
}


?>