<?php

  /** set up  mock objects  **/
require_once('simpletest/mock_objects.php');

/* solr response objects */

Mock::generate('Etd_Service_Solr', "Basic_Mock_Etd_Service_Solr");
Mock::generate('Emory_Service_Solr_Response', "Basic_Mock_Emory_Service_Solr_Response");

class Mock_Emory_Service_Solr_Response extends Basic_Mock_Emory_Service_Solr_Response {
  public $numFound;
  public function __construct() {
    $this->docs = array();
    $this->facets = array();
  }
}

class Mock_Etd_Service_Solr extends Basic_Mock_Etd_Service_Solr {
  public $response;
  public $queries;
  public function __construct() {
    $this->Basic_Mock_Etd_Service_Solr();	// initialize
    $this->response = &new Mock_Emory_Service_Solr_Response();
    $this->queries = array();

    $this->setReturnReference('query', $this->response);
    $this->setReturnReference('suggest', $this->response);
    $this->setReturnReference('browse', $this->response);
    $this->setReturnReference('_browse', $this->response);
  }
  public function query($query_string) { $this->queries[] = $query_string; return $this->response; }
  // mimic fluent interface in actual Solr object
  public function clearFacets() { return $this; }
  public function addFacets() { return $this; }
  public function setFacetLimit() { return $this; }
  public function setFacetMinCount() { return $this; }
}


/* etd objects */

require_once('models/etd.php');
require_once('models/etdfile.php');
require_once('models/etd_dc.php');
Mock::generate('etd', 'BasicMock_Etd');
Mock::generate('etd_file', "BasicMock_EtdFile");
Mock::generate('etd_dc', "BasicMock_etd_dc");
Mock::generate('etd_html', "Mock_etd_html");
Mock::generate('etd_mods', "BasicMock_etd_mods");
Mock::generate('premis', "Mock_premis");
Mock::generate('user',  'BasicMock_User');

class MockEtd extends BasicMock_Etd {
  public $pid;
  public $PID;		// not ideal... using for solr results
  public $label;
  public $dc;
  public $mods;
  public $html;
  public $premis;

  public $status;
  public $user_role;

  public $fedora;
  
  public function __construct() {
    $this->BasicMock_Etd();
    $this->dc = &new Mocketd_dc();

    $xml = new DOMDocument();
    $xml->load("../fixtures/mods.xml");
    $this->mods = new etd_mods($xml);

    $xml->load("../fixtures/premis.xml");
    $this->premis = new premis($xml);
    $this->html = &new Mock_etd_html();

    $this->setReturnValue("chair", array());
    $this->setReturnValue("committee", array());
  }

  public function getResourceId() {
    return (($this->status == "") ? "" : $this->status . " ") . "etd";
  }
  public function getUserRole() {
    return ($this->user_role == "") ? "guest" : $this->user_role;
  }

  public function save($message) {
    if (isset($this->fedora)) {
      $this->fedora->ingest($message);
    }
    return "saved";
  }

  public function getMods() {
      return "<mods:clean_mods/>";
  }
   

}


class MockEtd_dc extends BasicMock_etd_dc {
  public $mimetype;
  public $title;
}
class Mocketd_mods extends BasicMock_etd_mods {
  public $chair;
  public $committee;
  public function __construct() {
    $this->chair = array();
    $this->committee = array();
  }
}


class MockEtdFile extends BasicMock_EtdFile {
  public $dc;
  public $pid;
  public $etd;
  public $type;
  public function __construct() {
    $this->BasicMock_EtdFile();
    $this->dc = &new Mocketd_dc();
    //    $this->etd = &new MockEtd();
  }
}

class MockUser extends BasicMock_User {
  public $pid;
}

require_once('fedora/api/FedoraConnection.php');
require_once('fedora/api/risearch.php');
require_once('Emory/Service/Persis.php');	// for persis exceptions
Mock::generate('FedoraConnection', 'BasicMockFedoraConnection');
Mock::generate('risearch', 'MockRisearch');

class MockFedoraConnection extends BasicMockFedoraConnection {
  private $exception;
  public $risearch;
  
  public function __construct(){
    $this->BasicMockFedoraConnection();
    $this->risearch = &new MockRisearch();
  }
  
  public function setException($name) {
    $this->exception = $name;
  }

  private function throw_exception() {
    switch($this->exception) {
    case "NotFound":
      throw new FedoraObjectNotFound();
    case "AccessDenied":
      throw new FedoraAccessDenied();
    case "NotAuthorized":
      throw new FedoraNotAuthorized();
    case "NotValid":
      throw new FedoraObjectNotValid();
          case "NotValid":
      throw new FedoraObjectNotValid();

      // persis errors may be triggered on fedora ingest
    case "PersisUnavail":
      throw new PersisServiceUnavailable();
    case "PersisUnauth":
      throw new PersisServiceUnauthorized();
    case "Persis":
      throw new PersisServiceException();
    case "generic":
      throw new FoxmlException();
    }
  }
  
  public function getObjectProfile() {
    $this->throw_exception();
    
    $response = new getObjectProfileResponse();
    $response->objectProfile->objContentModel = "etd";
    $response->objectProfile->objLabel = "title";
    $response->objectProfile->objLastModDate = "today";
    return $response->objectProfile;
    
  }

  public function ingest($msg) {
    $this->throw_exception();
  }
  
}


