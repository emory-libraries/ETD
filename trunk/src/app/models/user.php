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


  public static function find_by_username($netid) {
    $query = 'select $user  from <#ri>
	where  $user <fedora-model:contentModel> \'user\'
	and $user <fedora-model:owner> \'' . $netid . '\'';
    //	and $user <dc:title> \'' . $netid . '\'';

    $pidlist = risearch::query($query);

    if (isset($pidlist->results->result)) {
      $pid = $pidlist->results->result[0]->user["uri"];
      return new user($pid);
    } else {
      // no matches found
      return null;	// what should the proper response be?
    }
  }
  
}


?>