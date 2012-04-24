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

    // set non-persistent interface for Zend_Auth so AuthController can be tested without session
    $auth = Zend_Auth::getInstance();
    $auth->setStorage(new Zend_Auth_Storage_NonPersistent());
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
    $view = $AuthController->view;
    $this->assertFalse(isset($view->current_user));

    // username but no password
    $this->setUpGet(array("login" => array("username" => "someuser", "password" => "", "url" => "index")));
    $AuthController = new AuthControllerForTest($this->request,$this->response);
    $AuthController->loginAction();
    $this->assertTrue($AuthController->redirectRan);
    $messages = $AuthController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Error: please supply username and password", $messages[0]);
    $this->assertFalse(isset($AuthController->view->current_user));
    
    // password but no username
    $this->setUpGet(array("login" => array("username" => "", "password" => "somepass", "url" => "index")));
    $AuthController = new AuthControllerForTest($this->request,$this->response);
    $AuthController->loginAction();
    $this->assertTrue($AuthController->redirectRan);
    $messages = $AuthController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Error: please supply username and password", $messages[0]);
    $this->assertFalse(isset($AuthController->view->current_user));

    // NOTE: some kind of error with the ssl certificate for the ldap
    // proxy server causes these two tests to return a slightly
    // different error message when unit tests are run on the command
    // line, but it works properly when run from the web.
    // (some kind of difference in running environment, user?)
    // Should revisit and fix if we ever get access to a test Ldap server.
    
    // bad username
    $AuthController = new AuthControllerForTest($this->request,$this->response);
    $this->resetGet();
    $this->setUpGet(array("login" => array("username" => "nonexistent", "password" => "somepass",
					   "url" => "index")));
    $AuthController->loginAction();
    $this->assertTrue($AuthController->redirectRan);
    $messages = $AuthController->getHelper('FlashMessenger')->getMessages();
    //    $this->assertEqual("Error: login failed - wrong username?", $messages[0]);
    $this->assertPattern("/Error: login failed/", $messages[0]);
    $this->assertFalse(isset($AuthController->view->current_user));

    // good username, bad password
    $AuthController = new AuthControllerForTest($this->request,$this->response);
    $this->resetGet();
    $this->setUpGet(array("login" => array("username" => "rsutton", "password" => "somepass",
					   "url" => "index")));
    $AuthController->loginAction();
    $this->assertTrue($AuthController->redirectRan);
    $messages = $AuthController->getHelper('FlashMessenger')->getMessages();
    //$this->assertEqual("Error: login failed - wrong password?", $messages[0]);
    $this->assertPattern("/Error: login failed/", $messages[0]);
    $this->assertFalse(isset($AuthController->view->current_user));

    
    // not testing successful login because that would require including an ldap login and password here
  }

  function testLogoutAction() {
    $AuthController = new AuthControllerForTest($this->request,$this->response);
    $AuthController->logoutAction();
    $this->assertTrue($AuthController->redirectRan);
    $messages = $AuthController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Logout successful", $messages[0]);
    $this->assertFalse(isset($AuthController->view->current_user));

  }

  function testDeniedAction() {
    $AuthController = new AuthControllerForTest($this->request,$this->response);
    $AuthController->deniedAction();
    $this->assertTrue(isset($AuthController->view->title));
    $this->assertEqual("Access Denied", $AuthController->view->title);
  }


  function testSetroleAction() {
    $AuthController = new AuthControllerForTest($this->request,$this->response);
    // should only run in development mode
    $this->assertFalse($AuthController->setroleAction());

    $ep = new esdPerson();
    $this->test_user = $ep->getTestPerson();
    $this->test_user->role = "guest";
    $this->test_user->netid = "test_user";
    Zend_Registry::set("current_user", $this->test_user);

    // set env to dev to test
    $real_env = Zend_Registry::get('environment');
    Zend_Registry::set('environment', "development");
    $AuthController = new AuthControllerForTest($this->request,$this->response);
    $this->setUpGet(array("role" => "grad student"));
    $AuthController->setroleAction();
    $this->assertEqual("student", $AuthController->current_user->role);
    $this->assertEqual("GSAS", $AuthController->current_user->academic_career);

    $this->setUpGet(array("role" => "honors admin"));
    $AuthController->setroleAction();
    $this->assertEqual("honors admin", $AuthController->current_user->role);

    $this->setUpGet(array("role" => "honors student"));
    $AuthController->setroleAction();
    $this->assertEqual("student", $AuthController->current_user->role);
    $this->assertEqual("UCOL", $AuthController->current_user->academic_career);

    $this->setUpGet(array("role" => "coordinator:English"));
    $AuthController->setroleAction();
    $this->assertEqual("staff", $AuthController->current_user->role);
    $this->assertEqual("English", $AuthController->current_user->program_coord);

    
    // restore real environment mode
    Zend_Registry::set('environment', $real_env);
    // clear test user
    Zend_Registry::set("current_user", null);
  }
    	
}

class AuthControllerForTest extends AuthController {
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

runtest(new AuthControllerTest());

?>