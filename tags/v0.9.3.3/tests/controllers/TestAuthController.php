<?
require_once("../bootstrap.php"); 

require_once('../ControllerTestCase.php');
require_once('controllers/AuthController.php');
      
class AuthControllerTest extends ControllerTestCase {

  private $test_user;

  function setUp() {
    $_GET 	= array();
    $_POST	= array();
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
  }
  
  function tearDown() {}


  function testLoginAction() {

    // empty username/password
    $this->setUpGet(array("login" => array("username" => "", "password" => "", "url" => "index")));
    $AuthController = new AuthControllerForTest($this->request,$this->response);
    $AuthController->loginAction();
    $this->assertTrue($AuthController->redirectRan);
    $messages = $AuthController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Error: please supply username and password", $messages[0]);
    $viewVars = $AuthController->view->getVars();
    $this->assertFalse(isset($viewVars['current_user']));

    // username but no password
    $this->setUpGet(array("login" => array("username" => "someuser", "password" => "", "url" => "index")));
    $AuthController = new AuthControllerForTest($this->request,$this->response);
    $AuthController->loginAction();
    $this->assertTrue($AuthController->redirectRan);
    $messages = $AuthController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Error: please supply username and password", $messages[0]);
    $viewVars = $AuthController->view->getVars();
    $this->assertFalse(isset($viewVars['current_user']));
    
    // password but no username
    $this->setUpGet(array("login" => array("username" => "", "password" => "somepass", "url" => "index")));
    $AuthController = new AuthControllerForTest($this->request,$this->response);
    $AuthController->loginAction();
    $this->assertTrue($AuthController->redirectRan);
    $messages = $AuthController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Error: please supply username and password", $messages[0]);
    $viewVars = $AuthController->view->getVars();
    $this->assertFalse(isset($viewVars['current_user']));

    // bad username
    $AuthController = new AuthControllerForTest($this->request,$this->response);
    $this->setUpGet(array("login" => array("username" => "nonexistent", "password" => "somepass",
					   "url" => "index")));
    $AuthController->loginAction();
    $this->assertTrue($AuthController->redirectRan);
    $messages = $AuthController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Error: login failed - wrong username?", $messages[0]);
    $viewVars = $AuthController->view->getVars();
    $this->assertFalse(isset($viewVars['current_user']));

    // good username, bad password
    $AuthController = new AuthControllerForTest($this->request,$this->response);
    $this->setUpGet(array("login" => array("username" => "rsutton", "password" => "somepass",
					   "url" => "index")));
    $AuthController->loginAction();
    $this->assertTrue($AuthController->redirectRan);
    $messages = $AuthController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Error: login failed - wrong password?", $messages[0]);
    $viewVars = $AuthController->view->getVars();
    $this->assertFalse(isset($viewVars['current_user']));

    
    // not testing successful login because that would require including an ldap login and password here
  }

  function testLogoutAction() {
    $AuthController = new AuthControllerForTest($this->request,$this->response);
    $AuthController->logoutAction();
    $this->assertTrue($AuthController->redirectRan);
    $messages = $AuthController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Logout successful", $messages[0]);
    $viewVars = $AuthController->view->getVars();
    $this->assertFalse(isset($viewVars['current_user']));

  }

  function testDeniedAction() {
    $AuthController = new AuthControllerForTest($this->request,$this->response);
    $AuthController->deniedAction();
    $viewVars = $AuthController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertEqual("Access Denied", $viewVars['title']);
  }


  function testSetroleAction() {
    $AuthController = new AuthControllerForTest($this->request,$this->response);
    // should only run in development mode
    $this->assertFalse($AuthController->setroleAction());
    //  (just a tool for setting roles in development, no need to test the rest of it)
    
  }
    	
}

class AuthControllerForTest extends AuthController {
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

runtest(new AuthControllerTest());

?>