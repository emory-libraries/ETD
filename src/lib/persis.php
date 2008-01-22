<?php

require_once("Persistent_IdentifierService.php");


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