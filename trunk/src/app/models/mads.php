<?php

require_once("models/foxmlDatastreamAbstract.php");

class mads extends foxmlDatastreamAbstract {
  
  protected $schema = "http://www.loc.gov/mads/mads.xsd";
  protected $namespace = "http://www.loc.gov/mads/";

  protected $xmlconfig;
  
  public function __construct($dom, $xpath = null) {
    $this->addNamespace("mads", $this->namespace);

    $this->configure();
    $config = $this->config($this->xmlconfig);

    parent::__construct($dom, $config, $xpath);
  }

  // define xml mappings (separate so it can be extended)
  protected function configure() {

    $this->xmlconfig =  array(
	"name" => array("xpath" => "mads:authority/mads:name", "class_name" => "mads_name"),

	// repeat affiliation for multiple address - will need to separate out affiliation
	"address" => array("xpath" => "mads:affiliation/mads:address", "class_name" => "mads_address"),
	"email" => array("xpath" => "mads:affiliation/mads:email", "is_series" => true),
	"phone" => array("xpath" => "mads:affiliation/mads:phone"),
	"date" => array("xpath" => "mads:affiliation/mads:dateValid"),
	"netid" => array("xpath" => "mads:identifier[@type='netid']"),
	);
  }

  public function isValid() {
    return $this->dom->schemaValidate($this->schema);
  }
  
  public static function getFedoraTemplate(){
    return foxml::xmlDatastreamTemplate("MADS", "Agent Information",
					file_get_contents("mads.xml", FILE_USE_INCLUDE_PATH));
  }


}


// very similar to mods_name
class mads_name extends XmlObject {
  public function __construct($xml, $xpath) {
      $config = $this->config(array(
     "type" => array("xpath" => "@type"),
     // generic namePart
     "namePart" => array("xpath" => "mads:namePart"),
     "first" => array("xpath" => "mads:namePart[@type='given']"),
     "last" => array("xpath" => "mads:namePart[@type='family']"),
     "date" => array("xpath" => "mads:namePart[@type='date']"),
     ));
      parent::__construct($xml, $config, $xpath);
    }

  // full name is default display content
  public function __toString() {
    return $this->full;
  }
}

class mads_address extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
	"street" => array("xpath" => "mads:street"),
	"city" => array("xpath" => "mads:city"),
	"state" => array("xpath" => "mads:state"),
	"country" => array("xpath" => "mads:country"),
	"postcode" => array("xpath" => "mads:postcode"),
	));
    parent::__construct($xml, $config, $xpath);
  }
}
