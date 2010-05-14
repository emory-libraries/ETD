<?php

/**
 * @category Etd
 * @package Etd_Models
 * @subpackage Author_Info
 */

require_once("models/foxmlDatastreamAbstract.php");

class mads extends foxmlDatastreamAbstract {
  
  const dslabel = "Agent Information";
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

	//
	"permanent" => array("xpath" => "mads:affiliation[mads:position = 'permanent resident']",
			     "class_name" => "mads_affiliation"),
	"current" =>  array("xpath" => "mads:affiliation[mads:position != 'permanent resident']",
			     "class_name" => "mads_affiliation"),
	"netid" => array("xpath" => "mads:identifier[@type='netid']"),
	);
  }



  /**
   * initialize information from Emory Shared Data, as much as possible
   */
  public function initializeFromEsd(esdPerson $person) {
    
    // set netid and name
    $this->netid = $person->netid;
    $this->name->first = $person->firstname;
    $this->name->last = $person->lastname;
    
    // academic/emory info
    $this->current->organization = $person->academic_plan;
    $this->current->email = $person->email;

    // address information is only available for current students
    if ($person->address) {
      // set current address
      $this->setAddressFromEsd($this->current, $person->address->current);
      // set to valid as of today
      $this->current->date = date("Y-m-d");
      
      // set permanent address
      $this->setAddressFromEsd($this->permanent, $person->address->permanent);
    }
  }


  /**
   * set address (current or permanent) from ESD
   */
  public function setAddressFromEsd($addr, esdAddress $esdAddress) {
    for ($i = 0; isset($esdAddress->street[$i]) && $i < 3; $i++)
      if ($i == 0)
	$addr->address->street[$i] = $esdAddress->street[$i];	// fixme: multiple?
      else 
	$addr->address->street[] = $esdAddress->street[$i];
    
    $addr->address->city = $esdAddress->city;
    $addr->address->state = $esdAddress->state;
    if ($esdAddress->country)
      $addr->address->country = $esdAddress->country;
    $addr->address->postcode = $esdAddress->zip;
    $addr->phone = $esdAddress->telephone;
  }    

  


  /*   should have already from base class
  public function isValid() {
    return $this->dom->schemaValidate($this->schema);
    }*/
  
  public static function getFedoraTemplate(){
    return foxml::xmlDatastreamTemplate("MADS", mads::dslabel,
					file_get_contents("mads.xml", FILE_USE_INCLUDE_PATH));
  }
  
  public function datastream_label() {
    return mads::dslabel;
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
    return $this->first . " " . $this->last;
  }
}

class mads_affiliation extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
	"address" => array("xpath" => "mads:address", "class_name" => "mads_address"),
	"organization" => array("xpath" => "mads:organization"),
	"position" => array("xpath" => "mads:position"),
	"email" => array("xpath" => "mads:email"),
	"phone" => array("xpath" => "mads:phone"),
	"date" => array("xpath" => "mads:dateValid")
	));
    parent::__construct($xml, $config, $xpath);
  }
}

class mads_address extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
        "street" => array("xpath" => "mads:street", "is_series" => true),
	"city" => array("xpath" => "mads:city"),
	"state" => array("xpath" => "mads:state"),
	"country" => array("xpath" => "mads:country"),
	"postcode" => array("xpath" => "mads:postcode"),
	));
    parent::__construct($xml, $config, $xpath);
  }

  function setStreet(Array $streets){
      foreach($streets as $i => $street){
        if(isset($this->street[$i])){
            $this->street[$i] = $street;
        }
        else{
            $this->street[] = $street;
        }
      }

     //Removing all street nodes under this mads address past the new values only if the total of lines increases
    $total = count($streets);

    $nodelist = $this->xpath->query("mads:street[position() > $total]", $this->domnode);
    for ($i = 0; $i < $nodelist->length; $i++) {
      $node = $nodelist->item($i);
      $node->parentNode->removeChild($node);
    }
    
    // update in-memory array so it will reflect the change
    $this->update();

  }

}
