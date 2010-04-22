<?php
require_once("../bootstrap.php");
require_once("../ControllerTestCase.php");
class TestGetFromFedora extends ControllerTestCase {
  
  private $helper;
  private $controller;

  private $_fedora;
  private $mock_fedora;

  private $etdpid;
  private $filepid;
  private $userpid;

  function __construct() {
    $this->fedora = Zend_Registry::get("fedora");
    $fedora_cfg = Zend_Registry::get('fedora-config');

    // get test pids for fedora objects
    list($this->etdpid, $this->filepid,
	   $this->userpid) = $this->fedora->getNextPid($fedora_cfg->pidspace, 3);
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

    // ingest fixtures into fedora
    $dom = new DOMDocument();
    $dom->load('../fixtures/etd1.xml');
    $etd = new etd($dom);
    $etd->pid = $this->etdpid;
    $this->_fedora->ingest($etd->saveXML(), "test etd for retrieving with getfromfedora helper");

    $dom = new DOMDocument();
    $dom->load('../fixtures/etdfile.xml');
    $etdfile = new etd_file($dom);
    $etdfile->pid = $this->filepid;
    $this->_fedora->ingest($etdfile->saveXML(), "test etd_file for retrieving with getfromfedora helper");

    $dom = new DOMDocument();
    $dom->load('../fixtures/user.xml');
    $authinfo = new user($dom);
    $authinfo->pid = $this->userpid;
    $this->_fedora->ingest($authinfo->saveXML(), "test authorInfo for retrieving with getfromfedora helper");
  }

  function tearDown() {
    $this->resetGet();
    $this->_fedora->purge($this->etdpid, "removing test etd");
    $this->_fedora->purge($this->filepid, "removing test etd_file");
    $this->_fedora->purge($this->userpid, "removing test etd authorInfo");
    // restore real fedora in registry (in case mock fedora was used)
    Zend_Registry::set('fedora', $this->_fedora);
  }

  // replace fedoraconnection in registry with real mock fedora - not used by all tests, so not in setup
  function setMockFedora() {
    Zend_Registry::set('fedora', $this->mock_fedora);
  }


  // testing with mock objects
  function testDirect_norecord(){
    $this->setMockFedora();
    // no value for id param
    $this->assertNull($this->helper->direct("id", "etd"));
    $messages = $this->controller->getHelper("flashMessenger")->getMessages();
    $this->assertEqual("Error: No record specified for etd", $messages[0]);
  }

  /* test various exceptions */
  
  function test_exceptions_notfound(){
    $this->setMockFedora();
    // simulate retriving an etd object from Fedora
    $this->setUpGet(array("id" => $this->etdpid));

    // not found
    $this->mock_fedora->setException("NotFound");
    $result = $this->helper->direct("id", "etd");
    $this->assertNull($result);
    $messages = $this->controller->getHelper("flashMessenger")->getMessages();
    $this->assertPattern("/Record not found/", $messages[0]);
  }

  function test_exceptions_accessdenied() {
    $this->setMockFedora();
    // - access denied
    $this->setUpGet(array("id" => "test:1"));
    $this->mock_fedora->setException("AccessDenied");
    $this->helper->direct("id", "etd");
    $response = $this->controller->getResponse();
    $this->assertEqual("403", $response->getHttpResponseCode());
    $this->assertPattern('/Permission Denied/', $response->getBody());
  }

  function test_exceptions_notauthorized() {
    $this->setMockFedora();
    // - not authorized
    $this->setUpGet(array("id" => "test:1"));
    $this->mock_fedora->setException("NotAuthorized");
    $this->helper->direct("id", "etd");
    $response = $this->controller->getResponse();
    $this->assertEqual("403", $response->getHttpResponseCode());
    $this->assertPattern('/Permission Denied/', $response->getBody());
  }
  
  function test_exception() {
    $this->setMockFedora();
    // - generic foxml exception
    $this->setUpGet(array("id" => "test:1"));
    $this->mock_fedora->setException("generic");
    $this->helper->direct("id", "etd");
    $response = $this->controller->getResponse();
    $this->assertEqual("403", $response->getHttpResponseCode());
    $this->assertPattern('/Permission Denied/', $response->getBody());
  }

  function testDirect_etd() {
    // retrieve an etd object from Fedora
    $this->setUpGet(array("id" => $this->etdpid));
    $result = $this->helper->direct("id", "etd");
    $this->assertIsA($result, "etd");
    $this->assertEqual($this->etdpid,  $result->pid);
  }

  function testDirect_etdfile() {
    // retrieve an etd_file object from Fedora
    $this->setUpGet(array("id" => $this->filepid));
    $result = $this->helper->direct("id", "etd_file");
    $this->assertIsA($result, "etd_file");
    $this->assertEqual($this->filepid,  $result->pid);
  }
  
  function testDirect_user() {
    // retrieve a user object from Fedora
    $this->setUpGet(array("id" => $this->userpid));
    $result = $this->helper->direct("id", "user");
    $this->assertIsA($result, "user");
    $this->assertEqual($this->userpid,  $result->pid);
  }

}



runtest(new TestGetFromFedora());

