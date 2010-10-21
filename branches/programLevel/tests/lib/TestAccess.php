<?php
require_once("../bootstrap.php");
require_once('models/esdPerson.php');


class TestAccess extends ControllerTestCase {
  
  private $helper;
  private $controller;
  private $esd;


  function setUp() {
    $_GET = array();
    $_POST = array();
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();

    $this->controller = new ControllerForTest($this->request,$this->response);
    $this->helper = $this->controller->getHelper("Access");

    $person = new esdPerson();
    $this->user = $person->getTestPerson();

    // store real config files to restore later
	$this->_realconfig = Zend_Registry::get('config');

    //store real current_user
    //$this->_realuser = Zend_Registry::get("current_user");

    // stub config with just the portion relevant to special user roles
	$testconfig = new Zend_Config(array("reportviewer" => array("department" => array("Reports Department")), ));
	// temporarily override config with test configuration
	Zend_Registry::set('config', $testconfig);
  }
  
  function tearDown() {
    $this->resetGet();
    Zend_Registry::set('config', $this->_realconfig);
    //Zend_Registry::set('current_user', $this->_realuser);
  }


  function testAllowedWithUser() {
     $this->user->netid = "fakeuser";
     $this->user->department = "Reports Department";
     $this->user->setRole();

     $this->assertTrue($this->helper->allowed("report", "view", $this->user));
     $this->assertFalse($this->controller->redirectRan);
  }

  function testAllowedWithoutCurrentUser() {
     $this->user->netid = "fakeuser";
     $this->user->department = "Reports Department";
     $this->user->setRole();
     Zend_Registry::set("current_user", $this->user);
     $this->controller = new ControllerForTest($this->request,$this->response);
    $this->helper = $this->controller->getHelper("Access");

     $this->assertTrue($this->helper->allowed("report", "view"));
     $this->assertFalse($this->controller->redirectRan);

     Zend_Registry::set("current_user", null);
  }
  

}



runtest(new TestAccess());

