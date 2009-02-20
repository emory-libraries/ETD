<?php
require_once("../bootstrap.php");
require_once("../ControllerTestCase.php");

class TestGetFromFedora extends ControllerTestCase {
  
  private $helper;
  private $controller;

  private $_fedora;
  private $mock_fedora;

  function setUp() {
    $_GET = array();
    $_POST = array();
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();

    $this->controller = new ControllerForTest($this->request,$this->response);
    $this->helper = $this->controller->getHelper("GetFromFedora");

    $this->_fedora = Zend_Registry::get('fedora');
    $this->mock_fedora = new MockFedoraConnection();
    Zend_Registry::set('fedora', $this->mock_fedora);
  }
  
  function tearDown() {
    $this->resetGet();
    Zend_Registry::set('fedora', $this->_fedora);
  }

  function testDirect(){
    // no value for id param
    $this->assertNull($this->helper->direct("id", "etd"));
    $messages = $this->controller->getHelper("flashMessenger")->getMessages();
    $this->assertEqual("Error: No record specified for etd", $messages[0]);

    // simulate retriving an etd object from Fedora
    $this->setUpGet(array("id" => "test:1"));
    // simulated etd object has no rels_ext; ignore the error
    $this->expectError("Object does not have configured datastream: rels_ext");
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
    
    //    $this->assertPattern("/Record not found/", $messages[0]);
    
  }

  // other cases to test:
  // FIXME: how to get a mock object to throw an exception ?
  // exceptions:
  //  - FedoraObjectNotFound 
  //  - FedoraAccessDenied
  //  - FedoraNotAuthorized
  //  - FoxmlException

}



runtest(new TestGetFromFedora());

