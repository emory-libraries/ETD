<?php

//NOTE: this has to be run thru the suite
require_once("bootstrap.php");
require_once('ControllerTestCase.php');
require_once('modules/services/controllers/XmlbyidController.php');


class XmlbyidControllerTest extends ControllerTestCase {

  private $testpid;
  private $fedora;
  private $fedora_cfg;

  function __construct() {
    global $_SERVER;
    $this->fedora_cfg = Zend_Registry::get('fedora-config');

    
    $this->fedora = Zend_Registry::get("fedora");
    // generate one new pid in the configured fedora test pidspace
    // will be used for test object (loaded & purged) throughout this test
    $this->testpid = $this->fedora->getNextPid($this->fedora_cfg->pidspace);
  }
  
  
  function setUp() {
    // override remote address to make requests look like they come from configured fedora instance
    // (all other hosts will get access denied)
    $_SERVER["REMOTE_ADDR"] = gethostbyname($this->fedora_cfg->server);

    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
    $this->resetGet();

    $this->orig_fedora_cfg = Zend_Registry::get('fedora-config');

    $XmlbyidController = new XmlbyidControllerForTest($this->request,$this->response);

    // use fixture but set pid to something more like regular etds
    $dom = new DOMDocument();
    $dom->loadXML(file_get_contents('../fixtures/etd1.xml'));
    $foxml = new foxml($dom);
    $foxml->pid = $this->testpid;
    $pid = $foxml->ingest("loading test etd");
  }
  
  function tearDown() {
    $this->fedora->purge($this->testpid, "removing test etd");

    Zend_Registry::set('fedora-config', $this->orig_fedora_cfg);
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
		       "bad xml id results in HTTP error code 403");
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
    $this->setUpGet(array('url' => "http://some.other.fedora:8080/fedora/objects/demo:1/datastreams/DC/content",
			  'id' => 'title'));
    $XmlbyidController = new XmlbyidControllerForTest($this->request,$this->response);
    $XmlbyidController->viewAction();

    $response = $XmlbyidController->getResponse();
    $this->assertEqual(403, $response->getHttpResponseCode(),
		       "datastream url for wrong fedora instance returns HTTP error code 403");
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

    $config_opts = array('server' => 'dev11.library.emory.edu');
    $test_fedora_cfg = new Zend_Config($config_opts);
    // temporarily override fedora config in with test configuration
    Zend_Registry::set('fedora-config', $test_fedora_cfg);



    $this->setUpGet(array('url' => $this->fedora->datastreamUrl($this->testpid, "XHTML"),
			  'id' => 'title'));
    $XmlbyidController = new XmlbyidControllerForTest($this->request,$this->response);
    $XmlbyidController->viewAction();

    $response = $XmlbyidController->getResponse();
    $this->assertEqual(403, $response->getHttpResponseCode(),
		       "request from client other than Fedora server results in HTTP error code 403");
          $this->assertPattern("/Not configured to access/", $response->getBody(),
			 "exception returned when request comes from non-Fedora server");

  }

  function testAlternateFedoraHostname() {
    $config_opts = $this->fedora_cfg->toArray();
    $config_opts ['alternate_hosts'] = array('server' => 'dev11.library.emory.edu');
    //$config_opts = array('alternate_hosts' => array('server' => array('etd.library.emory.edu', 'dev11.library.emory.edu')), 'alternate_ports' => array('port' => array('8643')));
    $test_fedora_cfg = new Zend_Config($config_opts);
    // temporarily override fedora config in with test configuration
    Zend_Registry::set('fedora-config', $test_fedora_cfg);

    $_SERVER["REMOTE_ADDR"] = gethostbyname("etd.library.emory.edu");
    
    $this->setUpGet(array('url' => $this->fedora->datastreamUrl($this->testpid, "XHTML"),
			  'id' => 'title'));
    $XmlbyidController = new XmlbyidControllerForTest($this->request,$this->response);
    $XmlbyidController->viewAction();

    $response = $XmlbyidController->getResponse();
    $this->assertNotEqual(403, $response->getHttpResponseCode(),
		       "request from configured alternate Fedora hostname should NOT result in HTTP error code 403");
    $this->assertEqual('<div id="title">Why <i>I</i> like cheese</div>', $response->getBody(),
		       "no data returned when request comes from non-Fedora server");

    // restore real fedora config in registry
    Zend_Registry::set('fedora-config', $this->fedora_cfg);
  }

  
  
  function testUrlEncoded() {
    $this->setUpGet(array('url' => urlencode($this->fedora->datastreamUrl($this->testpid, "XHTML")),
			  'id' => 'abstract'));
    $XmlbyidController = new XmlbyidControllerForTest($this->request,$this->response);
    $XmlbyidController->viewAction();

    $response = $XmlbyidController->getResponse();
    $this->assertNoPattern("/^40[0-9]/", $response->getHttpResponseCode(),
			  "url-encoded url should NOT result in HTTP error code 400/bad request (got " .
			   $response->getHttpResponseCode() . ")");
    $this->assertEqual('<div id="abstract"><b>gouda</b> or <i>cheddar</i>?</div>', $response->getBody(),
		       "response body should be abstract text, got " . $response->getBody());    
  }

  function testAuthorized(){

      $config_opts = array(
          'server' => 'dev11.library.emory.edu', 'port' => '8643', 'nonssl_port' => '123',
          'alternate_hosts' => array('server' => array('dev10.library.emory.edu', 'dev2.library.emory.edu')), 'alternate_ports' => array('port' => '8280')
      );
      $test_fedora_cfg = new Zend_Config($config_opts);
      Zend_Registry::set('fedora-config', $test_fedora_cfg);

      $XmlbyidController = new XmlbyidControllerForTest($this->request,$this->response);

      $result = $XmlbyidController->authorized("dev11.library.emory.edu", "8643");
      $this->assertTrue($result, "Using standard config fields");

      
      $result = $XmlbyidController->authorized("dev10.library.emory.edu", "8280");
      $this->assertTrue($result, "using alt config fields");

      $result = $XmlbyidController->authorized("dev2.library.emory.edu", "123");
      $this->assertTrue($result, "using alt config and nonssl port");

      $result = $XmlbyidController->authorized("fake.com", "123");
      $this->assertFalse($result, "This shoould not work");

      $result = $XmlbyidController->authorized("127.0.0.1", "123");
      $this->assertFalse($result, "This shoould not work");
      
      $result = $XmlbyidController->authorized("170.140.223.118", "123");
      $this->assertTrue($result, "using acual IP of dev2");

      //make sure it works without alt host and alt port
      $config_opts = array(
          'server' => 'dev11.library.emory.edu', 'port' => '8643');
      $test_fedora_cfg = new Zend_Config($config_opts);
      Zend_Registry::set('fedora-config', $test_fedora_cfg);

      $result = $XmlbyidController->authorized("dev11.library.emory.edu", "8643");
      $this->assertTrue($result, "no alt values configured");


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

