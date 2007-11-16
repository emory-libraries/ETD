<?php

require_once("fedora/models/foxml.php");
require_once("fedora/api/fedora.php");

require_once("vcard.php");

class person extends foxml {
  //  public $vcard;	// xmlobject with vcard data
  //  public $pid;		// fedora pid


  public function __construct() {
    parent::__construct();
    
    $this->cmodel = "person";
    // set owner to user who created object

    // testing
    //    $this->rels_ext->about = "user:me";
    //    $this->rels_ext->memberOf = "dept:someone";
    //    $this->rels_ext->addRelationToResource("rel:memberOf", "dept:someone");

  }


  // configure additional datastreams here 
  protected function configure() {
    parent::configure();

    // add to template for person foxml
    $this->datastreams[] = "vcard";
    $this->addNamespace("v", vcard::namespace);
    
    // add mappings for xmlobject
    $this->xmlconfig["vcard"] = array("xpath" => "//foxml:xmlContent/v:VCARD",
				     "class_name" => "vcard");
  }


    // handle special values
  public function __set($name, $value) {
    switch ($name) {
      // store formatted version in html, plain-text version in mods
    case "name":
      $this->label = $this->dc->title = $this->vcard->fullname = $value;
      break;
    default:
      parent::__set($name, $value);
    }
  }


  /* old person stuff... (not based on foxml object)

  public function __construct($xml = "", $pid = null) {
    if ($xml == "") {
      $this->vcard = new vcard($this->template);
    } else {
      $this->vcard = new vcard($xml);
    }
    if ($pid) $this->pid = $pid;
  }

  // create/write to fedora
  public function save() {	// fixme: allow optional message here?
    if ($this->pid) {
      // update existing object
      $lastmodified = Fedora::modifyXMLDatastream($this->pid, "vCard", "User Information",
 				  $this->vcard->saveXML(), "record updated");
      return $lastmodified;
    } else {     // create empty object
      $foxml = new foxml();
      $foxml->label = $this->vcard->name;
      $foxml->cmodel = "person";
      $foxml->addXMLDatastream($this->vcard->getDOM(), "vCard", "User Information");
      //      print "DEBUG: foxml is now <pre>" . htmlentities($foxml->saveXML()) . "</pre>";
      $this->pid = Fedora::ingest($foxml->saveXML(), "creating new person record");
      //      print "successfully saved: created record " . $this->pid . "<br>\n";
    }
   
  } // end save


  
  public static function find($pid) {
    // FIXME: probably needs some error checking etc
    return new person(Fedora::getDatastream($pid, "vCard"), $pid);
  }
  */
  
}


?>