<?php

require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/ViewController.php');


class ViewControllerTest extends ControllerTestCase {

  private $test_user;
  private $mock_etd;
    
  function setUp() {
    $this->test_user = new esdPerson();
    $this->test_user->role = "guest";
    Zend_Registry::set('current_user', $this->test_user);
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
    $this->resetGet();


    $ViewController = new ViewControllerForTest($this->request,$this->response);
    // use mock etd object to simplify permissions/roles/etc
    $this->mock_etd = &new MockEtd();
    $this->mock_etd->label = "Test Etd";
    $this->mock_etd->setReturnValue("title", "Test Etd");
    $this->mock_etd->pid = "testetd:1";
    $gff = $ViewController->getHelper("GetFromFedora");
    $gff->setReturnObject($this->mock_etd);

  }
  
  function tearDown() {
    unset($this->mock_etd);
    $ViewController = new ViewControllerForTest($this->request,$this->response);
    $gff = $ViewController->getHelper("GetFromFedora");
    $gff->clearReturnObject();
    Zend_Registry::set('current_user', null);
  }

  function testRecordAction_guest() {
    $this->mock_etd->setReturnValue("getUserRole", "guest");
    $this->mock_etd->setReturnValue("getResourceId", "draft etd");
    $ViewController = new ViewControllerForTest($this->request,$this->response);
    // guest on draft etd - not allowed
    $this->assertFalse($ViewController->recordAction());
  }
  
  function testRecordAction_author() {
    $ViewController = new ViewControllerForTest($this->request,$this->response);
    $this->mock_etd->setReturnValue("getUserRole", "author");
    $this->mock_etd->setReturnValue("getResourceId", "published etd");
    
    $ViewController->recordAction();
    $this->assertTrue(isset($ViewController->view->title));
    $this->assertIsA($ViewController->view->etd, "etd");
    $this->assertTrue($ViewController->view->printable);
  }


  // can't testing mods and dc actions directly; testing xmlAction that they forward to

  function testXmlAction() {
    $ViewController = new ViewControllerForTest($this->request,$this->response);
    $this->mock_etd->setReturnValue("getUserRole", "author");
    $this->mock_etd->setReturnValue("getResourceId", "published etd");
    $this->mock_etd->dc->setReturnValue("saveXML", "<oai_dc:dc/>");

    $this->setUpGet(array('datastream' => 'dc'));
    $ViewController->xmlAction();
    // confirm xml output settings - layout disabled, content-type set to text/xml
    $layout = $ViewController->getHelper("layout");
    //$this->assertFalse($layout->enabled);
    $this->assertFalse($ViewController->renderRan);	// ??
    $response = $ViewController->getResponse();
    $headers = $response->getHeaders();
    $this->assertEqual("Content-Type", $headers[0]["name"]);
    $this->assertEqual("text/xml", $headers[0]["value"]);
    $this->assertEqual('<oai_dc:dc/>', $response->getBody());

    // invalid datastream 
    $this->setUpGet(array('datastream' => 'bogus'));
    $this->expectException(new Exception("'bogus' is not a valid datastream for testetd:1"));
    $ViewController->xmlAction();
  }

}


class ViewControllerForTest extends ViewController {
  
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

runtest(new ViewControllerTest());



?>

