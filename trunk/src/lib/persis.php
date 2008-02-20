<?php

require_once("Persistent_IdentifierService.php");

// FIXME: where should this (etd_persis) go?

// FIXME: need better error handling here...  maybe a generic "service unavailable" error?

// customized version of persis service  - load etd config directly
class etd_persis extends persis {
  public function __construct() {
    $env_config = Zend_Registry::get('env-config');
    $config = new Zend_Config_Xml("../config/persis.xml", $env_config->mode);
    return parent::__construct($config->url, $config->username,
			       $config->password, $config->domain);
  }
}


class persis {
  private $username;
  private $password;
  private $domain_id;

  private $service;

    
  public function __construct($url, $username, $password, $domain_id) {
    $this->username = $username;
    $this->password = $password;
    $this->domain_id = $domain_id;

    $this->service = new Persistent_IdentifierService($url . "persis_api/service.wsdl",
					   array("trace" => true));
  }

  public function generateArk($url, $title) {
    try {
      $ark = $this->service->GenerateArk($this->username, $this->password,
					 $url, $title, "", $this->domain_id, "", "", "");
    } catch (SoapFault $e) {
      print "Error accessing persistent id server: " . $e->faultstring . "\n";
      print "response: \n" . $persis->__getLastResponse();
      return null;
    }
    return $ark;
  }

  public function pidfromArk($ark) {
    return preg_replace("|.*/([^/]+)$|", "emory:$1", $ark);
  }
  

}