<?php

require_once("xml-utilities/XmlObject.class.php");
require_once("fedora/api/fedora.php");
require_once("person.php");
require_once("etd_mods.php");

// compound-model etd (created with Fez)
class FezEtd {
  public $pid;
  public $mods;
  public $vcard;
  public $streams;
  public $files;


  // fixme: make a static find that returns a new emoryetd; new should make an empty one
  public function __construct($pid) {
    $this->pid = $pid;
    $dom = new DOMDocument();
    $dom->loadXML(Fedora::getDatastream($pid, "MODS"));
    $this->mods = new etd_mods($dom);
    $vdom = new DOMDocument();
    $vdom->loadXML(Fedora::getDatastream($pid, "vCard"));
    $this->vcard = new vcard($vdom);
    $this->streams  = Fedora::listDatastreams($pid);
    $this->files = array();
    foreach ($this->streams as $stream) {
      if ($stream->MIMEType != "text/xml"){
	array_push($this->files, $stream);
      }
    }
  }
  
}

/*
class etd_mods extends XmlObject {
  public function __construct($xmlString) {

    $dom = new DOMDocument();
    $dom->loadXML($xmlString);

    
    $this->addNamespace("mods", "http://www.loc.gov/mods/v3");
    $config = $this->config(array(
     "title" => array("xpath" => "//mods:titleInfo/mods:title"),
     "author" => array("xpath" => "//mods:name[mods:role/mods:roleTerm = 'author']",
		       "class_name" => "mods_name"),
     "program" => array("xpath" => "//mods:name[mods:role/mods:roleTerm = 'author']/mods:affiliation"),
     "advisor" => array("xpath" => "//mods:name[mods:role/mods:roleTerm = 'Thesis Advisor']",
			"class_name" => "mods_name"),
     // slight hack : ignore empty committee members added by Fez
     "committee" => array("xpath" => "//mods:name[mods:role/mods:roleTerm = 'Committee Member' and mods:displayForm != '']/mods:displayForm",
			  "class_name" => "mods_name",
			  "is_series" => true),     // FIXME: need to handle non-Emory committee (subclass?)
     "type" => array("xpath" => "//mods:genre"),
     "abstract" => array("xpath" => "//mods:abstract"),
     "toc" => array("xpath" => "//mods:tableOfContents"),
     "researchfields" => array("xpath" => "//mods:subject[@authority='proquestresearchfield']/mods:topic",
			      "is_series" => true),
     "keywords" => array("xpath" => "//mods:subject[@authority='keyword']/mods:topic",
			      "is_series" => true),
     "pages" => array("xpath" => "//mods:extent[@unit='pages']/mods:total")
      ));
    parent::__construct($dom, $config);
  }
  }*/

/*class mods_name extends XmlObject {
    public function __construct($xmlString) {
      $this->addNamespace("mods", "http://www.loc.gov/mods/v3");
      $config = $this->config(array(
     "full" => array("xpath" => "mods:displayForm"),
     "first" => array("xpath" => "mods:namePart[@type='given']"),
     "last" => array("xpath" => "mods:namePart[@type='family']"),
     "affiliation" => array("xpath" => "mods:affiliation"),
     "role" => array("xpath" => "mods:role/mods:roleTerm"),
     "role_authority" => array("xpath" => "mods:role/@authority"),
     ));
    parent::__construct($xmlString, $config);
    }
}
*/
?>