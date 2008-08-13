<?php

require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/UserController.php');
Mock::generate('user',  'BasicMock_User');

class UserControllerTest extends ControllerTestCase {

  private $test_user;
  private $mock_user;
    
  function setUp() {
    $this->test_user = new esdPerson();
    Zend_Registry::set('current_user', $this->test_user);
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
    $this->resetGet();


    $userController = new UserControllerForTest($this->request,$this->response);
    // use mock user object to simplify permissions/roles/etc
    $this->mock_user = &new MockUser();
    $this->mock_user->pid = "testuser:1";
    $this->mock_user->setReturnValue("getUserRole", "author");	// set role to author
    $gff = $userController->getHelper("GetFromFedora");
    $gff->setReturnObject($this->mock_user);

  }
  
  function tearDown() {
  }


  function testViewAction() {
    $userController = new UserControllerForTest($this->request,$this->response);
    $userController->viewAction();
    $view = $userController->view;
    $this->assertTrue(isset($view->title));
    $this->assertIsA($view->user, "user");

    // access denied
    $this->mock_user->setReturnValue("getUserRole", "guest");
    $this->assertFalse($userController->viewAction());
  }

  function testNewAction() {
    $userController = new UserControllerForTest($this->request,$this->response);
    // guest not allowed to create user object
    $this->test_user->role = "guest";
    $this->assertFalse($userController->newAction());

    // FIXME: how to test other case? can't currently simulating forwarding to other actions
  }

  // not testing findAction  -- probably not still needed or in use anywhere

  function testEditAction() {
    $userController = new UserControllerForTest($this->request,$this->response);
    // guest not allowed to create/edit user object
    $this->test_user->role = "guest";
    $this->setUpGet(array('etd' => 'etd:1'));
    $this->assertFalse($userController->editAction());	// no pid = create new record
    // edit (still guest - not allowed)
    $this->setUpGet(array('pid' => 'bogus:1', 'etd' => 'etd:1'));
    $this->assertFalse($userController->editAction());	// edit existing record

    // allow to create new record 
    $this->test_user->role = "student with submission";
    $this->resetGet();
    $this->setUpGet(array('etd' => 'etd:1'));
    $userController->editAction();	// no pid = create new record
    $view = $userController->view;
    $this->assertTrue(isset($view->title));
    $this->assertTrue($view->xforms);
    $this->assertIsA($view->user, "user");
    // user should be a new, blank object
    $this->assertEqual("", $view->user->label);
    $this->assertEqual("", $view->user->pid);
    $this->assertTrue(isset($view->xforms_bind_script));
    $this->assertIsA($view->namespaces, "Array");
    $this->assertTrue(isset($view->xforms_model_uri));

    // allow to edit record
    $this->test_user->role = "author";
    $this->setUpGet(array('pid' => 'bogus:1', 'etd' => 'etd:1'));
    $userController->editAction();	// edit existing record
    $view = $userController->view;
    // pre-existing record
    $this->assertEqual("testuser:1", $view->user->pid);
  }

  // FIXME: how to test saveAction with all the fedora/persis interaction?

  function testMadsAction() {
    $userController = new UserControllerForTest($this->request,$this->response);
    // new mads template
    $this->test_user->role = "student with submission";
    $this->test_user->netid = "jsmith";
    $userController->madsAction();	// no pid = create new record
    $view = $userController->view;
    $this->assertIsA($view->user, "user");
    // user should be a new, blank object
    $this->assertEqual("", $view->user->pid);
    // mads should have preliminary information from esdPerson object
    $this->assertEqual($view->user->mads->netid, "jsmith");

    $layout = $userController->getHelper("layout");
    // confirm xml output settings - layout disabled, content-type set to text/xml
    $this->assertFalse($layout->enabled);
    $response = $userController->getResponse();
    $headers = $response->getHeaders();
    $this->assertEqual("Content-Type", $headers[0]["name"]);
    $this->assertEqual("text/xml", $headers[0]["value"]);
    $this->assertPattern('|<mads:identifier type="netid">jsmith</mads:identifier>|', $response->getBody());

    // not testing editing existing version-- basically the same, but
    // would require mocking user object down to mads level
  }

}


class UserControllerForTest extends UserController {
  
  public $renderRan = false;
  public $redirectRan = false;
  
  public function initView() {
    $this->view = new Zend_View();
    Zend_Controller_Action_HelperBroker::addPrefix('TestEtd_Controller_Action_Helper');
  }
  
  public function render() {
    $this->renderRan = true;
  }
  
  public function _redirect() {
    $this->redirectRan = true;
  }
} 	

class MockUser extends BasicMock_User {
  public $pid;
}

runtest(new UserControllerTest());



?>

