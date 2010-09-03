<?php

require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/AuthorinfoController.php');

class AuthorInfoControllerTest extends ControllerTestCase {

  private $test_user;
  private $mock_authorInfo;
    
  function setUp() {
    $ep = new esdPerson();
    $this->test_user = $ep->getTestPerson();
    Zend_Registry::set('current_user', $this->test_user);
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
    $this->resetGet();


    // use mock user object to simplify permissions/roles/etc
    $this->mock_authorInfo = &new MockAuthorInfo();
    $this->mock_authorInfo->pid = "testuser:1";
    $this->mock_authorInfo->setReturnValue("getUserRole", "author");	// set role to author

    // set getFromFedora helper to return our mock object
    $authorInfoController = new AuthorinfoControllerForTest($this->request,$this->response);
    $gff = $authorInfoController->getHelper("GetFromFedora");
    $gff->setReturnObject($this->mock_authorInfo);

  }
  
  function tearDown() {
    Zend_Registry::set('current_user', null);

    $authorInfoController = new AuthorinfoControllerForTest($this->request,$this->response);
    $gff = $authorInfoController->getHelper("GetFromFedora");
    $gff->clearReturnObject();
  }


  function testViewAction() {
    $authorInfoController = new AuthorinfoControllerForTest($this->request,$this->response);
    $authorInfoController->viewAction();
    $view = $authorInfoController->view;
    $this->assertTrue(isset($view->title));
    $this->assertIsA($view->authorInfo, "authorInfo");

    // access denied
    $this->mock_authorInfo->setReturnValue("getUserRole", "guest");
    $this->assertFalse($authorInfoController->viewAction());
  }

  function testValidateContactInfo(){
     $authorInfoController = new AuthorinfoControllerForTest($this->request,$this->response);

     //No Errors
     $emails["cur-email"] = "email@email.com";
     $emails["perm-email"] = "email@email.com";
     $results = $authorInfoController->validateContactInfo($emails);
     $this->assertEqual(count($results), 0);

     //Error on  current email
     $emails["cur-email"] = "emailemail.com";
     $emails["perm-email"] = "email@email.com";
     $results = $authorInfoController->validateContactInfo($emails);
     $this->assertEqual(count($results), 1);
     $this->assertEqual($results[0], "Error: email emailemail.com is invalid");

     //Error on  current and non-emory email
     $emails["cur-email"] = "emailemail.com";
     $emails["perm-email"] = "email@email";
     $results = $authorInfoController->validateContactInfo($emails);
     $this->assertEqual(count($results), 2);
     $this->assertEqual($results[0], "Error: email emailemail.com is invalid");
     $this->assertEqual($results[1], "Error: non-emory email email@email is invalid");

     //Error on  non-emory email
     $emails["cur-email"] = "email@email.com";
     $emails["perm-email"] = "email@emory.edu";
     $results = $authorInfoController->validateContactInfo($emails);
     $this->assertEqual(count($results), 1);
     $this->assertEqual($results[0], "Error: non-emory email must be an non-emory or alumni address");
     

  }

  function testNewAction() {
    $authorInfoController = new AuthorinfoControllerForTest($this->request,$this->response);
    // guest not allowed to create user object
    $this->test_user->role = "guest";
    $this->assertFalse($authorInfoController->newAction());

    // FIXME: how to test other case? can't currently simulating forwarding to other actions
  }

  // not testing findAction  -- probably not still needed or in use anywhere

  function testEditAction() {
    $authorInfoController = new AuthorinfoControllerForTest($this->request,$this->response);
    // guest not allowed to create/edit user object
    $this->test_user->role = "guest";
    $this->setUpGet(array('etd' => 'etd:1'));
    $this->assertFalse($authorInfoController->editAction());	// no pid = create new record
    // edit (still guest - not allowed)
    $this->setUpGet(array('pid' => 'bogus:1', 'etd' => 'etd:1'));
    $this->assertFalse($authorInfoController->editAction());	// edit existing record

    // allow to create new record 
    $this->test_user->role = "student with submission";
    $this->resetGet();
    $this->setUpGet(array('etd' => 'etd:1'));
    $authorInfoController->editAction();	// no pid = create new record
    $view = $authorInfoController->view;
    $this->assertTrue(isset($view->title));
    $this->assertIsA($view->authorInfo, "authorInfo");
    // user should be a new, blank object
    $this->assertEqual("", $view->authorInfo->label);
    $this->assertEqual("", $view->authorInfo->pid);
    

    // allow to edit record
    $this->test_user->role = "author";
    $this->setUpGet(array('pid' => 'bogus:1', 'etd' => 'etd:1'));
    $authorInfoController->editAction();	// edit existing record
    $view = $authorInfoController->view;
    // pre-existing record
    $this->assertEqual("testuser:1", $view->authorInfo->pid);
  }

  function testEditAction_honors() {
    $this->test_user->role = "honors student with submission";
    $authorInfoController = new AuthorinfoControllerForTest($this->request,$this->response);
    // guest not allowed to create/edit user object
    $this->setUpGet(array('etd' => 'etd:1'));
    $this->assertFalse($authorInfoController->editAction());	// no pid = create new record
    $this->setUpGet(array('etd' => 'etd:1'));
    $authorInfoController->editAction();	// no pid = create new record
    $view = $authorInfoController->view;
    $this->assertTrue(isset($view->title));
    $this->assertIsA($view->authorInfo, "authorInfo");
    // user should be a new, blank object
    $this->assertEqual("", $view->authorInfo->label);
    $this->assertEqual("", $view->authorInfo->pid);
  }



  // FIXME: how to test saveAction with all the fedora/persis interaction?

  function testMadsAction() {
    $authorInfoController = new AuthorinfoControllerForTest($this->request,$this->response);
    // new mads template
    $this->test_user->role = "student with submission";
    $this->test_user->netid = "jsmith";
    $this->test_user->address = null;
    $authorInfoController->madsAction();	// no pid = create new record
    $view = $authorInfoController->view;
    $this->assertIsA($view->authorInfo, "authorInfo");
    // user should be a new, blank object
    $this->assertEqual("", $view->authorInfo->pid);
    // mads should have preliminary information from esdPerson object
    $this->assertEqual($view->authorInfo->mads->netid, "jsmith");

    $layout = $authorInfoController->getHelper("layout");
    // confirm xml output settings - layout disabled, content-type set to text/xml
    $this->assertFalse($layout->enabled);
    $response = $authorInfoController->getResponse();
    $headers = $response->getHeaders();
    $this->assertEqual("Content-Type", $headers[0]["name"]);
    $this->assertEqual("text/xml", $headers[0]["value"]);
    $this->assertPattern('|<mads:identifier type="netid">jsmith</mads:identifier>|', $response->getBody());

    // access denied 
    $this->test_user->role = "guest";
    $this->assertFalse($authorInfoController->madsAction());
    
    // not testing editing existing version-- basically the same, but
    // would require mocking user object down to mads level
  }

}


class AuthorinfoControllerForTest extends AuthorinfoController {
  
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


runtest(new AuthorInfoControllerTest());



?>

