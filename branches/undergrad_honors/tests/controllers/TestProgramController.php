<?
require_once("../bootstrap.php"); 
/**
 * unit tests for the Config Controller
 * - display configuration xml (used in various xforms)
 */


require_once('../ControllerTestCase.php');
require_once('controllers/ProgramController.php');
      
class ProgramControllerTest extends ControllerTestCase {

  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
  }
  function tearDown() {}

  function testXmlAction() {
   // $programController = new ProgramControllerForTest($this->request,$this->response);
   $programController = new ProgramControllerForTest();


//    $this->setUpGet(array('id' => 'programs'));
    $programController->xmlAction();
    $response = $programController->getResponse();
    $this->assertPattern('|<skos:Collection rdf:about="#programs">|', $response->getBody());

  }
}

class ProgramControllerForTest extends ProgramController {
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

runtest(new ProgramControllerTest());

?>