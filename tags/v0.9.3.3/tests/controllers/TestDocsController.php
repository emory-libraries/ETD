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
    $viewVars = $DocsController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertEqual("About Emory's ETD Repository", $viewVars['title']);
    $this->assertTrue($viewVars['printable']);
  }
  function testFaqAction() {
    $DocsController = new DocsControllerForTest($this->request,$this->response);
    $DocsController->faqAction();
    $viewVars = $DocsController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertEqual("Frequently Asked Questions", $viewVars['title']);
    $this->assertTrue($viewVars['printable']);
  }
  function testIpAction() {
    $DocsController = new DocsControllerForTest($this->request,$this->response);
    $DocsController->ipAction();
    $viewVars = $DocsController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertEqual("Intellectual Property", $viewVars['title']);
    $this->assertTrue($viewVars['printable']);
  }
  function testPoliciesAction() {
    $DocsController = new DocsControllerForTest($this->request,$this->response);
    $DocsController->policiesAction();
    $viewVars = $DocsController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertEqual("Policies & Procedures", $viewVars['title']);
    $this->assertTrue($viewVars['printable']);
  }
  function testBoundcopiesAction() {
    $DocsController = new DocsControllerForTest($this->request,$this->response);
    $DocsController->boundcopiesAction();
    $viewVars = $DocsController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertEqual("Bound Copies", $viewVars['title']);
    $this->assertTrue($viewVars['printable']);
  }
  function testInstructionsAction() {
    $DocsController = new DocsControllerForTest($this->request,$this->response);
    $DocsController->instructionsAction();
    $viewVars = $DocsController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertEqual("Submission Instructions", $viewVars['title']);
    $this->assertTrue($viewVars['printable']);
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