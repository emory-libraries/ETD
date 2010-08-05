<?
require_once("../bootstrap.php"); 
/**
 * unit tests for the Config Controller
 * - display configuration xml (used in various xforms)
 */


require_once('../ControllerTestCase.php');
require_once('controllers/ConfigController.php');
      
class ConfigControllerTest extends ControllerTestCase {

  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
  }
  function tearDown() {}

  function testViewAction() {
    $configController = new ConfigControllerForTest($this->request,$this->response);

    $this->setUpGet(array('id' => 'countries'));
    $configController->viewAction();
    $layout = $configController->getHelper("layout");
    // confirm xml output settings - layout disabled, content-type set to text/xml
    $this->assertFalse($layout->enabled);
    $response = $configController->getResponse();
    $headers = $response->getHeaders();
    $this->assertEqual("Content-Type", $headers[0]["name"]);
    $this->assertEqual("text/xml", $headers[0]["value"]);
    $this->assertPattern('|<country code="AF">|', $response->getBody());

    // same functionality for all other modes; confirm that content looks right
    
    $this->setUpGet(array('id' => 'degrees'));
    $configController->viewAction();
    $response = $configController->getResponse();
    $this->assertPattern('|<level name="doctoral" genre="Dissertation">|', $response->getBody());

    $this->setUpGet(array('id' => 'languages'));
    $configController->viewAction();
    $response = $configController->getResponse();
    $this->assertPattern('|<language code="eng" display="English" pq_code="EN"/>|', $response->getBody());
  }
}

class ConfigControllerForTest extends ConfigController {
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

runtest(new ConfigControllerTest());

?>