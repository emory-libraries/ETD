<?
require_once("../bootstrap.php"); 

require_once('../ControllerTestCase.php');
require_once('controllers/AuthController.php');
      
class BaseControllerTest extends ControllerTestCase {

  private $test_user;

  function setUp() {
    $_GET 	= array();
    $_POST	= array();
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
  }
  
  function tearDown() {}

  function testInit() {
    $baseController = new BaseControllerForTest($this->request,$this->response);

    $this->setUpGet(array("controller" => "index", "action" => "index"));
    $baseController->init();
    // init sets up several variables used by all the other controllers
    $this->assertNotNull($baseController->getVariable("debug"));
    $this->assertNotNull($baseController->view->debug);
    $this->assertIsA($baseController->getVariable("logger"), "Zend_Log");
    $this->assertNotNull($baseController->getVariable("env"));
    $this->assertIsA($baseController->getVariable("acl"), "Zend_Acl");
    $this->assertIsA($baseController->view->supported_browsers, "Array");
    $this->assertNotNull($baseController->view->controller);
    $this->assertNotNull($baseController->view->action);
  }

  // site-wide param for setting printable layout
  function testPrintable() {
    $this->setUpGet(array("layout" => "printable"));
    $baseController = new BaseControllerForTest($this->request,$this->response);
    $baseController->init();
    $layout = $baseController->getHelper("layout");
    // confirm xml output settings - layout disabled, content-type set to text/xml
    $this->assertEqual($layout->name, "printable");
  }

  // FIXME: can't test this properly because fedora is set in the registry
  // and apparently can't be unset for testing purposes
  function DONTtestRequiresFedora() {
    $baseController = new BaseControllerForTest($this->request,$this->response);
    $baseController->requiresFedora();
    $baseController->init();
    $this->assertTrue($baseController->redirectRan);
  }


  function testPostDispatch() {
    $baseController = new BaseControllerForTest($this->request,$this->response);
    $baseController->postDispatch();
    $this->assertIsA($baseController->view->messages, "Array");
  }

  function testGetFilterOptions() {
    $baseController = new BaseControllerForTest($this->request,$this->response);
    $this->setUpGet(array("status" => "published", "committee" => "faculty guy", "year" => "2008",
			  "subject" => "Phyto-Chemistry", "author" => "student person", "keyword" => "nonsense"));

    $opts = $baseController->getFilterOptions();
    $this->assertIsA($baseController->view->filters, "Array");
    $this->assertNotNull($baseController->view->filters["status"]);
    $this->assertNotNull($baseController->view->filters["committee"]);
    $this->assertNotNull($baseController->view->filters["year"]);
    $this->assertNotNull($baseController->view->filters["subject"]);
    $this->assertNotNull($baseController->view->filters["author"]);
    $this->assertNotNull($baseController->view->filters["keyword"]);
    $this->assertIsA($baseController->view->url_params, "Array");
  }


  	
}

class BaseControllerForTest extends Etd_Controller_Action {
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
  // function to access protected class variables
  public function getVariable($name) {
    if (isset($this->$name)) return $this->$name;
    else trigger_error("$name is not a class variable", E_USER_NOTICE);
  }

  public function requiresFedora() {$this->requires_fedora = true; }

} 	

runtest(new BaseControllerTest());

?>