<?php

  /** set up  mock objects  **/
require_once('simpletest/mock_objects.php');

/* solr response objects */

Mock::generate('Etd_Service_Solr', "Basic_Mock_Etd_Service_Solr");
Mock::generate('Emory_Service_Solr_Response', "Basic_Mock_Emory_Service_Solr_Response");

class Mock_Emory_Service_Solr_Response extends Basic_Mock_Emory_Service_Solr_Response {
  public function __construct() {
    $this->docs = array();
    $this->facets = array();
  }
}

class Mock_Etd_Service_Solr extends Basic_Mock_Etd_Service_Solr {
  public $response;
  public function __construct() {
    $this->Basic_Mock_Etd_Service_Solr();	// initialize
    $this->response = &new Mock_Emory_Service_Solr_Response();

    $this->setReturnReference('query', $this->response);
    $this->setReturnReference('suggest', $this->response);
  }
}


/* etd objects */

require_once('models/etd.php');
require_once('models/etdfile.php');
require_once('models/etd_dc.php');
Mock::generate('etd', 'BasicMock_Etd');
Mock::generate('etd_file', "BasicMock_EtdFile");
Mock::generate('etd_dc', "BasicMock_etd_dc");
Mock::generate('user',  'BasicMock_User');

class MockEtd extends BasicMock_Etd {
  public $pid;
  public $label;
  public $dc;
  
  public function __construct() {
    $this->BasicMock_Etd();
    $this->dc = &new Mocketd_dc();
  }

}


class MockEtd_dc extends BasicMock_etd_dc {
  public $mimetype;
}

class MockEtdFile extends BasicMock_EtdFile {
  public $dc;
  public $pid;
  public function __construct() {
    $this->BasicMock_EtdFile();
    $this->dc = &new Mocketd_dc();
  }
}

class MockUser extends BasicMock_User {
  public $pid;
}


