<?php

//NOTE: this has to be run thru the suite
require_once("bootstrap.php");
require_once('ControllerTestCase.php');
require_once('modules/services/controllers/IndexdataController.php');


// to confirm the pid url is working 
// erroring when it should (e.g., bogus url, non-etd pid, etc.), 
// tests return valid json content.

class IndexdataControllerTest extends ControllerTestCase {

  private $testpid;
  private $fedora;
  private $fedora_cfg;
  private $etdContentModel = "info:fedora/emory-control:ETD-1.0";

  function __construct() {
    global $_SERVER;
    $this->fedora_cfg = Zend_Registry::get('fedora-config');
    $this->fedora = Zend_Registry::get("fedora");
  }
  
  
  function setUp() {
    
    // generate one new pid in the configured fedora test pidspace
    // will be used for test object (loaded & purged) throughout this test
    $this->testpid = $this->fedora->getNextPid($this->fedora_cfg->pidspace);
        
    // override remote address to make requests look like they come from configured fedora instance
    // (all other hosts will get access denied)
    $_SERVER["REMOTE_ADDR"] = gethostbyname($this->fedora_cfg->server);

    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
    $this->resetGet();

    $this->orig_fedora_cfg = Zend_Registry::get('fedora-config');
    
    $this->etdContentModel = "info:fedora/emory-control:ETD-1.0";    

    // use fixture but set pid to something more like regular etds
    $dom = new DOMDocument();
    $dom->loadXML(file_get_contents('../fixtures/etd2.xml'));
    $foxml = new foxml($dom);
    $foxml->pid = $this->testpid;
    $pid = $foxml->ingest("loading test etd");
  }
  
  function tearDown() {
    try { $this->fedora->purge($this->testpid, "removing test etd");  } catch (Exception $e) {}    
    Zend_Registry::set('fedora-config', $this->orig_fedora_cfg);
  }

  function testAbout() {
    
    $IndexdataController = new IndexdataControllerForTest($this->request,$this->response);
    $IndexdataController->aboutAction();
    $response = $IndexdataController->getResponse();    
    $this->assertEqual(200, $response->getHttpResponseCode(),
		       "about page failed to respond with 200"); 
  }
  
  function testIndexdata() {

    $IndexdataController = new IndexdataControllerForTest($this->request,$this->response);    
    $IndexdataController->indexAction();    
    $response = $IndexdataController->getResponse();
    
    $this->assertEqual(200, $response->getHttpResponseCode(),
		       "indexdata page failed to respond with 200"); 
		       
    $headers = $response->getHeaders();
    // create numeric indexes by header name into header array 
    $index = array();
    for ($i = 0; $i < count($headers); $i++) {
      $index[$headers[$i]["name"]] = $i;
    }
    $this->assertEqual("application/json", $headers[$index["Content-Type"]]["value"],
		       "content-type should be application/json, got '" .
		       $headers[$index["Content-Type"]]["value"] . "'");
		       
    $result = Zend_Json::decode($response->getBody(), true);
    $key = 'SOLR_URL';
    $value = $result[$key];
    $pattern = "|https://[^:]+:\d\d\d\d/solr/etd|";
    $this->assertPattern($pattern, $value, "json $key should contain pattern [$pattern] in $value"); 
    $key = 'CONTENT_MODELS';
    $pattern = "info:fedora/";
    $value = $result[$key][0][0];
    $this->assertEqual($pattern, $value, "json $key should contain pattern [$pattern]");          
  } 
  
  function testIndexdataPid() {

    $IndexdataController = new IndexdataControllerForTest($this->request,$this->response);
    $IndexdataController->pid = $this->testpid;
    $IndexdataController->etdContentModel = $this->etdContentModel;    
    $IndexdataController->indexPid();    
    $response = $IndexdataController->getResponse();
    $this->assertEqual(200, $response->getHttpResponseCode(),
		       "indexdata/pid page failed to respond with 200"); 
		       
    $headers = $response->getHeaders();
    // create numeric indexes by header name into header array 
    $index = array();
    for ($i = 0; $i < count($headers); $i++) {
      $index[$headers[$i]["name"]] = $i;
    }
    $this->assertEqual("application/json", $headers[$index["Content-Type"]]["value"],
		       "content-type should be application/json, got '" .
		       $headers[$index["Content-Type"]]["value"] . "'");
		       
    $result = Zend_Json::decode($response->getBody(), true);
    
    $msg = "indexData should contain value for key=";    
    $this->assertTrue($result, "getIndexData returned data");
    $this->assertEqual("PID", array_search($this->testpid, $result), $msg . "[PID]");
    $this->assertEqual("collection", array_search("emory-control:ETD-GradSchool-collection", $result), $msg . "[collection]");
    $this->assertEqual("contentModel", array_search($this->etdContentModel, $result), $msg . "[contentModel]");		       
  }
  
  function testIndexdataBadPid() {

    $IndexdataController = new IndexdataControllerForTest($this->request,$this->response);
    $IndexdataController->pid = 'boguspid';
    $IndexdataController->etdContentModel = $this->etdContentModel;    
    $IndexdataController->indexPid();    
    $response = $IndexdataController->getResponse();
    $this->assertEqual(404, $response->getHttpResponseCode());    
  }   
}


class IndexdataControllerForTest extends Services_IndexdataController {
  
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

runtest(new IndexdataControllerTest());



?>

