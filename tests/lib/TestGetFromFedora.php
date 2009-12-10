<?php
require_once("../bootstrap.php");
require_once("../ControllerTestCase.php");
class TestGetFromFedora extends ControllerTestCase {
  
  private $helper;
  private $controller;

  private $_fedora;
  private $mock_fedora;

  function __construct() {
    $this->fedora = Zend_Registry::get("fedora");
    $fedora_cfg = Zend_Registry::get('fedora-config');
  }


  function setUp() {
    $_GET = array();
    $_POST = array();
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();

    $this->controller = new ControllerForTest($this->request,$this->response);
    $this->helper = $this->controller->getHelper("GetFromFedora");

    $this->_fedora = Zend_Registry::get('fedora');
    $this->mock_fedora = &new MockFedoraConnection();
    $this->mock_fedora->risearch = $this->_fedora->risearch; 
    Zend_Registry::set('fedora', $this->mock_fedora);
  }
  
  function tearDown() {
    $this->resetGet();
    Zend_Registry::set('fedora', $this->_fedora);
  }


  // testing with mock objects
  function testDirect_mock(){
    // no value for id param
    $this->assertNull($this->helper->direct("id", "etd"));
    $messages = $this->controller->getHelper("flashMessenger")->getMessages();
    $this->assertEqual("Error: No record specified for etd", $messages[0]);

    // simulate retriving an etd object from Fedora
    $this->setUpGet(array("id" => "test:1"));
    // simulated etd object does not have correct cmodel; ignore the error
    $this->expectException();	// foxmlbadcontentmodel
    // no results returned from risearch for simulated etd
    //    $this->expectError("No response returned from risearch; cannot determine if test:1 is an honors etd");
    $result = $this->helper->direct("id", "etd");
    $this->assertIsA($result, "etd");
    
    
    // test various exceptions
    // - not found
    $this->mock_fedora->setException("NotFound");
    $this->helper->direct("id", "etd");
    $messages = $this->controller->getHelper("flashMessenger")->getMessages();
    $this->assertPattern("/Record not found/", $messages[0]);
    // - access denied
    $this->setUpGet(array("id" => "test:1"));
    $this->mock_fedora->setException("AccessDenied");
    $this->helper->direct("id", "etd");
    $response = $this->controller->getResponse();
    $this->assertEqual("403", $response->getHttpResponseCode());
    $this->assertPattern('/Permission Denied/', $response->getBody());
    // - not authorized
    $this->setUpGet(array("id" => "test:1"));
    $this->mock_fedora->setException("NotAuthorized");
    $this->helper->direct("id", "etd");
    $response = $this->controller->getResponse();
    $this->assertEqual("403", $response->getHttpResponseCode());
    $this->assertPattern('/Permission Denied/', $response->getBody());
    // - generic foxml exception
    $this->setUpGet(array("id" => "test:1"));
    $this->mock_fedora->setException("generic");
    $this->helper->direct("id", "etd");
    $response = $this->controller->getResponse();
    $this->assertEqual("403", $response->getHttpResponseCode());
    $this->assertPattern('/Permission Denied/', $response->getBody());
  }

}



runtest(new TestGetFromFedora());

