<?
require_once("../bootstrap.php"); 
/**
 * unit tests for the Documents Controller
 * - these are basically static pages with content in the templates
 * just checking that titles are set correctly and printable is true
 * 
 */


require_once('../ControllerTestCase.php');
require_once('controllers/HelpController.php');
      
class HelpControllerTest extends ControllerTestCase {

  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
  }
  
  function tearDown() {
  	/**/	
  }

  function testIndexAction() {
    $HelpController = new HelpControllerForTest($this->request,$this->response);
    $HelpController->indexAction();
    $this->assertNotNull($HelpController->view->url(array("action" => "submit")));
/* ? */$this->assertFalse($HelpController->view->printable);
  }

  function testSubmitAction() {
    $HelpController = new HelpControllerForTest($this->request,$this->response);

	$config = Zend_Registry::get('config');
	$list_addr = $config->email->etd->address;
	$this->assertNotNull($list_addr);
	
	$address_to = "rwrober@emory.edu";
	$address_from = "unit.test@emory.edu" ;
	$name_from = "Test User";
	$subject = "Test message";
    $message = "some message";
    
	// assert perfect
    $this->setUpGet(array("list_addr"=>$address_to, "email" => $address_from, "username" => $name_from ,"subject"=>$subject, "message" => $message));
    // for test mode, the action will return the email notify object
    $notify = $HelpController->submitAction();
    $this->assertEqual($address_from, $notify->getFrom(), "From address is not the same.");
	$this->assertEqual("ETD Help: " . $subject, $notify->getSubject(), "Subject is not the same.[" . $subject . "," . $notify->getSubject() . "]");
        
    $this->resetGet();
    $this->setUpGet(array("email" => "", "username" => "", "message" => ""));
    $HelpController->submitAction();
    $messages = $HelpController->getHelper('FlashMessenger')->getMessages();
    $this->assertNotNull($messages, $messages . " is not supposed to be null");
  }
}

class HelpControllerForTest extends HelpController {
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

runtest(new HelpControllerTest());

?>