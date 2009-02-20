<?php
require_once("../bootstrap.php");
require_once("../ControllerTestCase.php");

class TestIngestOrError extends ControllerTestCase {
  
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
    $this->helper = $this->controller->getHelper("IngestOrError");

    $this->_fedora = Zend_Registry::get('fedora');
    $this->mock_fedora = new MockFedoraConnection();
    Zend_Registry::set('fedora', $this->mock_fedora);
  }
  
  function tearDown() {
    $this->resetGet();
    Zend_Registry::set('fedora', $this->_fedora);
  }


  function testSimulated() {
    $etd = new MockEtd();
    $etd->fedora = $this->mock_fedora;
    
    // test various exceptions
    // - not valid
    $etd->fedora->setException("NotValid");
    $this->assertFalse($this->helper->direct($etd, "saving etd", "etd record", $err));
    $this->assertEqual("FedoraObjectNotValid", $err);
    $messages = $this->controller->getHelper("flashMessenger")->getMessages();
    $this->assertPattern("/Error:.*etd record.*FedoraObjectNotValid/", $messages[0]);
    $this->assertFalse($this->controller->redirectRan);

    // - not found
    $etd->fedora->setException("NotFound");
    $this->controller->redirectRan = false;	  // reset for accurate test
    $this->assertFalse($this->helper->direct($etd, "saving etd", "etd record", $err));
    $this->assertEqual("FedoraObjectNotFound", $err);
    $messages = $this->controller->getHelper("flashMessenger")->getMessages();
    $this->assertPattern("/Error:.*etd record.*FedoraObjectNotFound/", $messages[0]);
    $this->assertFalse($this->controller->redirectRan);
    // persis service errors
    // - unavailable
    $etd->fedora->setException("PersisUnavail");
    $this->controller->redirectRan = false;
    $this->assertFalse($this->helper->direct($etd, "saving etd", "etd record", $err));
    $this->assertEqual("PersisServiceUnavailable", $err);
    $messages = $this->controller->getHelper("flashMessenger")->getMessages();
    $this->assertPattern("/Error:.*Persistent Identifier Service is not available/",
			 $messages[0]);
    $this->assertTrue($this->controller->redirectRan);
    // - not authorized
    $etd->fedora->setException("PersisUnauth");
    $this->controller->redirectRan = false;
    $this->assertFalse($this->helper->direct($etd, "saving etd", "etd record", $err));
    $this->assertEqual("PersisServiceUnauthorized", $err);
    $messages = $this->controller->getHelper("flashMessenger")->getMessages();
    $this->assertPattern("/Error:.*authorization error.*Persistent Identifier Service/",
			 $messages[0]);
    $this->assertTrue($this->controller->redirectRan);
    // - generic / unknown persis error
    $etd->fedora->setException("Persis");
    $this->controller->redirectRan = false;
    $this->assertFalse($this->helper->direct($etd, "saving etd", "etd record", $err));
    $this->assertEqual("PersisServiceException", $err);
    $messages = $this->controller->getHelper("flashMessenger")->getMessages();
    $this->assertPattern("/Error:.*error accessing Persistent Identifier Service/",
    		 $messages[0]);
    $this->assertTrue($this->controller->redirectRan);

    // test no error - returns pid, does not redirect
    $etd->fedora->setException(null);
    $this->controller->redirectRan = false;
    $err = null;
    $this->assertEqual("testpid",
		       $this->helper->direct($etd, "saving etd", "etd record", $err));
    $this->assertNull($err);	// unchanged
    $this->assertFalse($this->controller->redirectRan);
    
  }
  

}



runtest(new TestIngestOrError());

