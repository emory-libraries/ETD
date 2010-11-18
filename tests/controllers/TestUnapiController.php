<?php

require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/UnapiController.php');


class UnapiControllerTest extends ControllerTestCase {

  private $testpid;

  // fedoraConnection
  private $fedora;
  
  function __construct() {
    $this->fedora = Zend_Registry::get("fedora");
    $fedora_cfg = Zend_Registry::get('fedora-config');
    
    // get test pid
    $this->testpid = $this->fedora->getNextPid($fedora_cfg->pidspace);
  }


  
  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
    $this->resetGet();

    $UnapiController = new UnapiControllerForTest($this->request,$this->response);

    $fedora = Zend_Registry::get("fedora");
    // use fixture but set pid to something more like regular etds
    $dom = new DOMDocument();
    $dom->loadXML(file_get_contents('../fixtures/etd1.xml'));
    $foxml = new foxml($dom);
    $foxml->pid = $this->testpid;
    $foxml("loading test etd");
    // construct fake ark to use for unAPI id
    $this->testark = 'http://pid/ark:/123/' . preg_replace("/^.*:/", "", $this->testpid);
  }
  
  function tearDown() {
    $fedora = Zend_Registry::get("fedora");
    $fedora->purge($this->testpid, "removing test etd");
  }

  function test_noparams() {
    $UnapiController = new UnapiControllerForTest($this->request,$this->response);
    $UnapiController->indexAction();
    // confirm xml output settings - layout disabled, content-type set to text/xml
    $this->assertFalse($UnapiController->renderRan);	
    $response = $UnapiController->getResponse();   
    $this->assertEqual(300, $response->getHttpResponseCode()); // multiple choices
    $headers = $response->getHeaders();
    $this->assertEqual("Content-Type", $headers[0]["name"]);
    $this->assertEqual("application/xml", $headers[0]["value"]);
    $xml = $response->getBody();
    $this->assertPattern('|^<formats>.*</formats>$|', $xml);
    $this->assertPattern('|<format name=".*" type="*"|', $xml);	// at least one format
    $this->assertNoPattern('|id="*"|', $xml);	// no id specified
  }

  function test_id() {
    // construct a fake ark to use for identifier
    $UnapiController = new UnapiControllerForTest($this->request,$this->response);
    $this->setUpGet(array('id' => $this->testark));
    $UnapiController->indexAction();
    // confirm xml output settings - layout disabled, content-type set to text/xml
    $this->assertFalse($UnapiController->renderRan);	
    $response = $UnapiController->getResponse();
    $this->assertEqual(300, $response->getHttpResponseCode()); // multiple choices
    $headers = $response->getHeaders();
    $this->assertEqual("Content-Type", $headers[0]["name"]);
    $this->assertEqual("application/xml", $headers[0]["value"]);
    $xml = $response->getBody();
    $this->assertPattern('|^<formats.*>.*</formats>$|', $xml);
    $this->assertPattern('|<format name=".*" type="*"|', $xml);	// at least one format
    $this->assertPattern('|id="' . $this->testark . '"|', $xml); 
  }

  function test_invalid_id() {
    $UnapiController = new UnapiControllerForTest($this->request,$this->response);
    $this->setUpGet(array('id' => 'bogusid'));
    $UnapiController->indexAction();
    $response = $UnapiController->getResponse();
    $this->assertEqual(404, $response->getHttpResponseCode());
  }

  function IGNORE__test_id_format() {
    $UnapiController = new UnapiControllerForTest($this->request,$this->response);
    $this->setUpGet(array('id' => $this->testark, 'format' => 'oai_dc'));
    $UnapiController->indexAction();

    // FIXME: still no way to test when _forward is used ...
  }

  function test_invalid_format() {
    $UnapiController = new UnapiControllerForTest($this->request,$this->response);
    $this->setUpGet(array('id' => $this->testark, 'format' => 'bogus'));
    $UnapiController->indexAction();
    $response = $UnapiController->getResponse();
    $this->assertEqual(406, $response->getHttpResponseCode());
  }
}


class UnapiControllerForTest extends UnapiController {
  
  public $renderRan = false;
  public $redirectRan = false;
  
  public function initView() {
    $this->view = new Zend_View();
    Zend_Controller_Action_HelperBroker::addPrefix('Test_Controller_Action_Helper');
  }
  
  public function render() {
    $this->renderRan = true;
  }
  
  public function _redirect() {
    $this->redirectRan = true;
  }
} 	

runtest(new UnapiControllerTest());



?>

