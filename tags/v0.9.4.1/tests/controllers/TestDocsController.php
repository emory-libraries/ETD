<?
require_once("../bootstrap.php"); 
/**
 * unit tests for the Documents Controller
 * - these are basically static pages with content in the templates
 * just checking that titles are set correctly and printable is true
 * 
 */


require_once('../ControllerTestCase.php');
require_once('controllers/DocsController.php');
      
class DocsControllerTest extends ControllerTestCase {

  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
  }
  function tearDown() {}

  function testAboutAction() {
    $DocsController = new DocsControllerForTest($this->request,$this->response);
    $DocsController->aboutAction();
    $this->assertTrue(isset($DocsController->view->title));
    $this->assertEqual("About Emory's ETD Repository", $DocsController->view->title);
    $this->assertTrue($DocsController->view->printable);
  }
  function testFaqAction() {
    $DocsController = new DocsControllerForTest($this->request,$this->response);
    $DocsController->faqAction();
    $this->assertTrue(isset($DocsController->view->title));
    $this->assertEqual("Frequently Asked Questions", $DocsController->view->title);
    $this->assertTrue($DocsController->view->printable);
  }
  function testIpAction() {
    $DocsController = new DocsControllerForTest($this->request,$this->response);
    $DocsController->ipAction();
    $this->assertTrue(isset($DocsController->view->title));
    $this->assertEqual("Intellectual Property", $DocsController->view->title);
    $this->assertTrue($DocsController->view->printable);
  }
  function testPoliciesAction() {
    $DocsController = new DocsControllerForTest($this->request,$this->response);
    $DocsController->policiesAction();
    $this->assertTrue(isset($DocsController->view->title));
    $this->assertEqual("Policies & Procedures", $DocsController->view->title);
    $this->assertTrue($DocsController->view->printable);
  }
  function testBoundcopiesAction() {
    $DocsController = new DocsControllerForTest($this->request,$this->response);
    $DocsController->boundcopiesAction();
    $this->assertTrue(isset($DocsController->view->title));
    $this->assertEqual("Bound Copies", $DocsController->view->title);
    $this->assertTrue($DocsController->view->printable);
  }
  function testInstructionsAction() {
    $DocsController = new DocsControllerForTest($this->request,$this->response);
    $DocsController->instructionsAction();
    $this->assertTrue(isset($DocsController->view->title));
    $this->assertEqual("Submission Instructions", $DocsController->view->title);
    $this->assertTrue($DocsController->view->printable);
  }
}

class DocsControllerForTest extends DocsController {
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

runtest(new DocsControllerTest());

?>