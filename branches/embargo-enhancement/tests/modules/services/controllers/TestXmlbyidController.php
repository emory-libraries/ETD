<?php

require_once("bootstrap.php");
require_once('ControllerTestCase.php');
require_once('modules/services/controllers/XmlbyidController.php');


class XmlbyidControllerTest extends ControllerTestCase {

  private $testpid;
  private $fedora;

  function __construct() {
    global $_SERVER;
    $fedora_cfg = Zend_Registry::get('fedora-config');

    // override remote address to make requests look like they come from configured fedora instance
    // (all other hosts will get access denied)
    $_SERVER["REMOTE_ADDR"] = gethostbyname($fedora_cfg->server);
    
    $this->fedora = Zend_Registry::get("fedora");
    // generate one new pid in the configured fedora test pidspace
    // will be used for test object (loaded & purged) throughout this test
    $this->testpid = $this->fedora->getNextPid($fedora_cfg->pidspace);
  }
  
  
  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
    $this->resetGet();

    $XmlbyidController = new XmlbyidControllerForTest($this->request,$this->response);

    // use fixture but set pid to something more like regular etds
    $dom = new DOMDocument();
    $dom->loadXML(file_get_contents('../fixtures/etd1.xml'));
    $foxml = new foxml($dom);
    $foxml->pid = $this->testpid;
    $this->fedora->ingest($foxml->saveXML(), "loading test etd");
  }
  
  function tearDown() {
    $this->fedora->purge($this->testpid, "removing test etd");
  }

  function testAbout() {
    $XmlbyidController = new XmlbyidControllerForTest($this->request,$this->response);
    $XmlbyidController->aboutAction();
  }

  function testTitle() {
    $this->setUpGet(array('url' => $this->fedora->datastreamUrl($this->testpid, "XHTML"),
			  'id' => 'title'));
    $XmlbyidController = new XmlbyidControllerForTest($this->request,$this->response);
    $XmlbyidController->viewAction();

    $response = $XmlbyidController->getResponse();
    $headers = $response->getHeaders();
    // create numeric indexes by header name into header array 
    $index = array();
    for ($i = 0; $i < count($headers); $i++) {
      $index[$headers[$i]["name"]] = $i;
    }
    $this->assertEqual("text/xml", $headers[$index["Content-Type"]]["value"],
		       "content-type should be text/xml, got '" .
		       $headers[$index["Content-Type"]]["value"] . "'");

    $xml = $response->getBody();
    $this->assertEqual('<div id="title">Why <i>I</i> like cheese</div>', $xml);    
  }

  function testAbstract() {
    $this->setUpGet(array('url' => $this->fedora->datastreamUrl($this->testpid, "XHTML"),
			  'id' => 'abstract'));
    $XmlbyidController = new XmlbyidControllerForTest($this->request,$this->response);
    $XmlbyidController->viewAction();

    $response = $XmlbyidController->getResponse();
    $xml = $response->getBody();
    $this->assertEqual('<div id="abstract"><b>gouda</b> or <i>cheddar</i>?</div>', $xml);    
  }

  function testBadId() {
    $this->setUpGet(array('url' => $this->fedora->datastreamUrl($this->testpid, "XHTML"),
			  'id' => 'bogus'));
    $XmlbyidController = new XmlbyidControllerForTest($this->request,$this->response);
    $XmlbyidController->viewAction();

    $response = $XmlbyidController->getResponse();
    $this->assertEqual(400, $response->getHttpResponseCode(),
		       "bad xml id results in HTTP error code 400");
    $this->assertPattern("/id 'bogus' not found/", $response->getBody(),
			 "bogus id should not be found");
  }


  function testBadUrl() {
    $this->setUpGet(array('url' => "http://www.google.com/",
			  'id' => 'abstract'));
    $XmlbyidController = new XmlbyidControllerForTest($this->request,$this->response);
    $XmlbyidController->viewAction();

    $response = $XmlbyidController->getResponse();
    $this->assertEqual(400, $response->getHttpResponseCode(),
		       "non-datastream url returns HTTP error code 400");
    $this->assertPattern("/Could not parse datastream url/", $response->getBody(),
			 "non-datastream url results in error");
  }

  function testWrongFedoraInstance() {
    $this->setUpGet(array('url' => "http://some.other.fedora:8080/fedora/get/demo:1/DC",
			  'id' => 'title'));
    $XmlbyidController = new XmlbyidControllerForTest($this->request,$this->response);
    $XmlbyidController->viewAction();

    $response = $XmlbyidController->getResponse();
    $this->assertEqual(400, $response->getHttpResponseCode(),
		       "datastream url for wrong fedora instance returns HTTP error code 400");
    $this->assertPattern("/Not configured to access/", $response->getBody(),
			 "not configured to access wrong fedora instance");
  }

  function testNoData() {
    $this->setUpGet(array('url' => $this->fedora->datastreamUrl($this->testpid, "BOGUS"),
			  'id' => 'title'));
    $XmlbyidController = new XmlbyidControllerForTest($this->request,$this->response);
    $XmlbyidController->viewAction();

    $response = $XmlbyidController->getResponse();
    $this->assertEqual(400, $response->getHttpResponseCode(),
		       "bad datastream url results in HTTP error code 400");
    $this->assertPattern("/no datastream/", $response->getBody(),
			 "bad datastream url - no content retrieved from fedora");
  }

  function testNonXml() {
    // add non-xml datastream just for testing
    $upload_id = $this->fedora->upload("../fixtures/tinker_sample.pdf");
    $this->fedora->addDatastream($this->testpid, "PDF", "pdf", true, "application/pdf",
				 null, $upload_id,
				 FedoraConnection::MANAGED_DATASTREAM,
				 FedoraConnection::STATE_ACTIVE,
				 "DISABLED", "none", "adding binary datastream for testing");


    $this->setUpGet(array('url' => $this->fedora->datastreamUrl($this->testpid, "PDF"),
			  'id' => 'title'));
    $XmlbyidController = new XmlbyidControllerForTest($this->request,$this->response);

    // suppress warning from DOM attempting to load non-xml (should not fail if warnings are turned off)
    $errlevel = error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
    $XmlbyidController->viewAction();
    error_reporting($errlevel);	    // restore prior error reporting

    $response = $XmlbyidController->getResponse();
    $this->assertEqual(400, $response->getHttpResponseCode(),
		       "non-xml datastream results in HTTP error code 400");
    $this->assertPattern("/Could not load content as xml/", $response->getBody(),
			 "non-xml datastream url - can not be loaded");
    

  }


  function testNonfedoraRequest() {
    // if requesting host is anything other than configured fedora, access should be denied
    $_SERVER["REMOTE_ADDR"] = "127.0.0.1";

    $this->setUpGet(array('url' => $this->fedora->datastreamUrl($this->testpid, "XHTML"),
			  'id' => 'title'));
    $XmlbyidController = new XmlbyidControllerForTest($this->request,$this->response);
    $XmlbyidController->viewAction();

    $response = $XmlbyidController->getResponse();
    $this->assertEqual(403, $response->getHttpResponseCode(),
		       "request from client other than Fedora server results in HTTP error code 403");
    $this->assertEqual("", $response->getBody(),
			 "no data returned when request comes from non-Fedora server");
    
    
  }

  

}


class XmlbyidControllerForTest extends Services_XmlbyidController {
  
  public $renderRan = false;
  public $redirectRan = false;
  
  public function init() {
    $this->initView();
  }
  
  public function initView() {
    $this->view = new Zend_View();
    Zend_Controller_Action_HelperBroker::addPath('Emory/Controller/Action/Helper',
						 'Emory_Controller_Action_Helper');
    Zend_Controller_Action_HelperBroker::addPrefix('Test_Controller_Action_Helper');
  }
  
  public function render() {
    $this->renderRan = true;
  }
  
  public function _redirect() {
    $this->redirectRan = true;
  }
} 	

runtest(new XmlbyidControllerTest());



?>

