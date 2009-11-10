<?php
require_once("../bootstrap.php");
require_once("../ControllerTestCase.php");
class TestGetFromFedora extends ControllerTestCase {
  
  private $helper;
  private $controller;

  private $_fedora;
  private $mock_fedora;

  private $etdpid;
  private $hons_etdpid;

  function __construct() {
    $this->fedora = Zend_Registry::get("fedora");
    $fedora_cfg = Zend_Registry::get('fedora-config');
    
    // get test pids for fedora fixtures
    list($this->etdpid, $this->hons_etdpid) = $this->fedora->getNextPid($fedora_cfg->pidspace, 2);
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


    // ingest etd and honors etd to test factory init
    $etd = new etd();
    $etd->pid = $this->etdpid;
    $etd->title = "test obj";
    $this->_fedora->ingest($etd->saveXML(), "ingesting test object");
    
    $hons_etd = new honors_etd();
    $hons_etd->pid = $this->hons_etdpid;
    $hons_etd->title = "test honors obj";
    $this->_fedora->ingest($hons_etd->saveXML(), "ingesting test object");
  }
  
  function tearDown() {
    $this->resetGet();
    Zend_Registry::set('fedora', $this->_fedora);

    $this->_fedora->purge($this->etdpid, "removing test obj");
    $this->_fedora->purge($this->hons_etdpid, "removing test obj");
  }


  // testing with mock objects
  function testDirect_mock(){
    // no value for id param
    $this->assertNull($this->helper->direct("id", "etd"));
    $messages = $this->controller->getHelper("flashMessenger")->getMessages();
    $this->assertEqual("Error: No record specified for etd", $messages[0]);

    // simulate retriving an etd object from Fedora
    $this->setUpGet(array("id" => "test:1"));
    // simulated etd object has no rels_ext; ignore the error
    $this->expectError("Object does not have configured datastream: rels_ext");
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

 function testFactoryInit() {
    // use real fedora connection for honors etd init
    Zend_Registry::set('fedora', $this->_fedora);
   

    $this->setUpGet(array("id" => $this->etdpid));
    $etd = $this->helper->direct("id", "etd");
    $this->assertIsA($etd, "etd");
    $this->assertNotA($etd, "honors_etd");

    $this->setUpGet(array("id" => $this->hons_etdpid));
    $etd2 = $this->helper->direct("id", "etd");
    $this->assertIsA($etd2, "etd");
    $this->assertIsA($etd2, "honors_etd");
 
  }
 
  
}



runtest(new TestGetFromFedora());

