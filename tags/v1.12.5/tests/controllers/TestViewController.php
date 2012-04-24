<?php

require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/ViewController.php');


class ViewControllerTest extends ControllerTestCase {

  private $test_user;
  private $mock_etd;
    
  function setUp() {
    $ep = new esdPerson();
    $this->test_user = $ep->getTestPerson();
    $this->test_user->role = "guest";
    Zend_Registry::set('current_user', $this->test_user);
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
    $this->resetGet();


    $ViewController = new ViewControllerForTest($this->request,$this->response);
    // use mock etd object to simplify permissions/roles/etc
    $this->mock_etd = &new MockEtd();
    $this->mock_etd->label = "Test Etd";
    $this->mock_etd->dc->title = "Test Etd";
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

  function NOtestRecordAction_guest_draft() {
    // simulate having no user logged in - public, guest view
    Zend_Registry::set('current_user', null);
    $this->mock_etd->user_role = "guest";
    $this->mock_etd->status = "draft";
    $this->mock_etd->last_modified = "2011-09-09T19:57:55.905Z";
    $ViewController = new ViewControllerForTest($this->request,$this->response);
    // guest on draft etd - not allowed
    $this->assertFalse($ViewController->recordAction());
  }

  function testRecordAction_guest_published() {
    // simulate having no user logged in - public, guest view
    Zend_Registry::set('current_user', null);
    $this->mock_etd->user_role = "guest";
    $this->mock_etd->status = "published";
    $this->mock_etd->last_modified = '2011-09-09T19:57:55.905Z';
    $ViewController = new ViewControllerForTest($this->request,$this->response);

    // guest on published etd - should have last-modified header
    $ViewController->recordAction();
    $headers = $ViewController->getResponse()->getHeaders();
    // last-modified header from object last_modified
    $this->assertTrue(in_array(array("name" => "Last-Modified",
                                     "value" => date(DATE_RFC1123,
                                                     strtotime($this->mock_etd->last_modified)),
                                     "replace" => true), $headers),
          'object last modified should be set as Last-Modified header');
    $this->assertTrue(in_array(array("name" => "Cache-Control",
                                     "value" => "public",
                                     "replace" => true), $headers),
          'cache-control should be set to public when no user is logged in');
  }

  function testRecordAction_guest_published_notmodified() {
    // simulate having no user logged in - public, guest view
    Zend_Registry::set('current_user', null);
    $this->mock_etd->user_role = "guest";
    $this->mock_etd->status = "published";
    $this->mock_etd->last_modified = "2011-09-09T19:57:55.905Z";
        
    // not-modified response
    global $_SERVER;
    $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $this->mock_etd->last_modified;
      
    $ViewController = new ViewControllerForTest($this->request,$this->response);
    $ViewController->recordAction();
    $this->assertEqual(304, $ViewController->getResponse()->getHttpResponseCode());
  }


  
  function NOtestRecordAction_author() {
    $ViewController = new ViewControllerForTest($this->request,$this->response);
    $this->mock_etd->user_role = "author";
    $this->mock_etd->status = "published";
    
    $ViewController->recordAction();
    $this->assertTrue(isset($ViewController->view->title));
    $this->assertIsA($ViewController->view->etd, "etd");
    $this->assertTrue($ViewController->view->printable);
    $headers = $ViewController->getResponse()->getHeaders();
    $this->assertFalse(in_array(array("name" => "Cache-Control",
                                     "value" => "public",
                                     "replace" => true), $headers),
          'cache-control should not be set to public when a user is logged in');

  }


  // can't testing mods and dc actions directly; testing xmlAction that they forward to

  function testXmlAction() {
    $ViewController = new ViewControllerForTest($this->request,$this->response);
    $this->mock_etd->user_role = "author";
    $this->mock_etd->status = "published";
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

  function testPublicModsAction() {
    $ViewController = new ViewControllerForTest($this->request,$this->response);
    $this->mock_etd->user_role = "author";
    $this->mock_etd->status = "published";

    $ViewController->publicModsAction();
    $response = $ViewController->getResponse();
    $headers = $response->getHeaders();
    $this->assertEqual("Content-Type", $headers[0]["name"]);
    $this->assertEqual("text/xml", $headers[0]["value"]);
    $this->assertEqual('<mods:clean_mods/>', $response->getBody());

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

