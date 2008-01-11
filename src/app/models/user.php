<?php

require_once("fedora/models/foxml.php");
require_once("fedora/api/fedora.php");
require_once("fedora/api/risearch.php");

//require_once("vcard.php");
require_once("mads.php");

class user extends foxml {


  public function __construct($arg = null) {
    parent::__construct($arg);

    if ($this->init_mode == "pid") {
      // initialize mads datastream...
      $dom = new DOMDocument();
      $dom->loadXML(fedora::getDatastream($arg, "MADS"));
      $this->map{"mads"} = new mads($dom);
    } else {
      $this->cmodel = "user";
    }
  }

  // configure additional datastreams here 
  protected function configure() {
    parent::configure();

    $this->datastreams[] = "mads";
    $this->addNamespace("mads", "http://www.loc.gov/mads/");
    
    // add mappings for xmlobject
    $this->xmlconfig["mads"] = array("xpath" => "//foxml:datastream[@ID='MADS']/foxml:datastreamVersion/foxml:xmlContent/mads:mads",
				     "class_name" => "mads", "dsID" => "MADS");
  }


    // handle special values
  /*  public function __set($name, $value) {
    switch ($name) {
      // store formatted version in html, plain-text version in mods
    case "name":
      $this->label = $this->dc->title = $this->vcard->fullname = $value;
      break;
    default:
      parent::__set($name, $value);
    }
    }*/


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
    if ($perm_addr->street == "" ||
	$perm_addr->city == "" ||
	$perm_addr->state == "" ||
	$perm_addr->country == "" ||
	$perm_addr->postcode == "" ||
	$this->mads->permanent->date == "") {
      $missing[] = "permanent mailing address";
    }
    
    return $missing;
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