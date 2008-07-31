<?php

require_once("xml-utilities/XmlObject.class.php");
require_once("fedora/api/fedora.php");
require_once("vcard.php");
require_once("etd_mods.php");
require_once("premis.php");

// compound-model etd (records created with Fez)
class FezEtd extends foxml {
  public $streams;
  public $files;

  public function __construct($pid) {
    parent::__construct($pid);
    
    //    $this->streams  = Fedora::listDatastreams($pid);
    $this->files = array();
    foreach ($this->fedora_streams as $stream) {
      if ($stream->MIMEType != "text/xml"){
	array_push($this->files, $stream);
      }
    }
  }

    // add datastreams here 
  protected function configure() {
    parent::configure();
    // not adding to datastream[] because we should not be creating new fezetds from template

    $this->addNamespace("mods", "http://www.loc.gov/mods/v3");
    $this->xmlconfig["mods"] = array("xpath" => "//foxml:xmlContent/mods:mods",
				     "class_name" => "fez_etd_mods", "dsID" => "MODS");
    $this->addNamespace("v", vcard::namespace);
    $this->xmlconfig["vcard"] = array("xpath" => "//foxml:xmlContent/v:VCARD",
				      "class_name" => "vcard", "dsID" => "vCard");

    $this->xmlconfig["fezmd"] = array("xpath" => "//foxml:xmlContent/FezMD",
				      "class_name" => "FezMD", "dsID" => "FezMD");

    $this->addNamespace("premis", "http://www.loc.gov/standards/premis/v1");
    $this->xmlconfig["premis"] = array("xpath" => "//foxml:xmlContent/premis:premis",
				       "class_name" => "premis", "dsID" => "PremisEvent");


  }

  public function getFile($dsID) {
    return $this->fedora->getDatastream($this->pid, $dsID);
  }



  public function __get($name) {
    switch ($name) {
    case "status":
      return $this->translateStatus($this->fezmd->status);
    default:
      return parent::__get($name);
    }
  }

  // translate numeric fez status to text equivalent
  private function translateStatus($id) {
    // based on defines in fez config/globals
    switch ($id) {
    case 1: return "unpublished";
    case 2: return "published";
    case 3: return "draft"; 
    case 4: return "submitted";
    case 5: return "approved"; 
    case 6: return "reviewed"; 
    case 7: return "embargoed"; 
    }
  }
  
}


class FezMD extends XmlObject {
  
  public function __construct($dom, $xpath = null) {

    $config = $this->config(array(
       // fez id (numeric) for user who created this record
      "depositor" => array("xpath" => "depositor"),	
      "updated"   => array("xpath" => "updated_date"),
      "created"   => array("xpath" => "created_date"),
      "copyright" => array("xpath" => "copyright"),	 // ? corresponds to permission checkbox?
      "ret_id" 	  => array("xpath" => "ret_id"),	// what is this?
      "status" 	  => array("xpath" => "sta_id"),
      ));
    parent::__construct($dom, $config, $xpath);
  }

}

class fez_etd_mods extends etd_mods {

  // add mappings that have changed from fez to new etd
  protected function configure() {
    parent::configure();
    $this->xmlconfig["pages"] = array("xpath" => "mods:part/mods:extent[@unit='pages']/mods:total");
    $this->xmlconfig["embargo_note"] = array("xpath" => "mods:note[@type='admin'][starts-with(., 'Embargoed for')]");
  }
}

?>