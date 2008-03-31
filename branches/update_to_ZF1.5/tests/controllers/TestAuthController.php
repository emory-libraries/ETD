<?
require_once('ControllerTestCase.php');
require_once('controllers/AuthController.php');
      
class authControllerTest extends ControllerTestCase {

  
  function setUp()  {
    $_GET 	= array();
    $_POST	= array();
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
  }
    	
  function tearDown()	{
  }

  /* FIXME: can loginAction be tested? don't want to put a real ldap password here */
  function NOtestLoginAction() {
    $authController = new AuthControllerForTest($this->request, $this->response);
    $this->setUpPost(array('login' => array('username' => 'user', 'password' => 'test')));
    $authController->loginAction();
    

  }

  function NOtestSetroleAction() {
    $authController = new AuthControllerForTest($this->request, $this->response);
    $this->setUpPost(array('role' => 'test'));
    $authController->setroleAction();
    $view = $authController->view->getVars();
    $flashmessenger = $authController->getHelper("FlashMessenger");
    //    $view = $authController->_helperview->getVars();
    print "<pre>"; print_r($flashmessenger->getMessages()); print "</pre>";
    
  }


}




    class AuthControllerForTest extends AuthController {
      public $renderRan = false;
      public $redirectRan = false;
      
      public function initView() {
	$this->view = new Zend_View();
      }
      
      public function render() {
	$this->renderRan = true;
      }
      
      public function _redirect() {
	$this->redirectRan = true;
      }
    } 	


?>