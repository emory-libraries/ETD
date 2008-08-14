<?
require_once("../bootstrap.php"); 
/**
 * unit tests for the Feeds Controller (generates RSS feeds)
 */

require_once('../ControllerTestCase.php');
require_once('controllers/FeedsController.php');
      
class FeedsControllerTest extends ControllerTestCase {

  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
    
    $solr = &new Mock_Etd_Service_Solr();
    Zend_Registry::set('solr', $solr);
  }
  function tearDown() {
    Zend_Registry::set('solr', null);
  }

  function testRecentAction() {
    $feedsController = new FeedsControllerForTest($this->request,$this->response);

    $feedsController->recentAction();
    $feedsController->postDispatch();	// actual call to display feed xml is here
    $this->assertIsA($feedsController->feed, "Etd_Feed");
    $response = $feedsController->getResponse();
    $this->assertPattern("|<title>.*Recently published.*</title>|", $response->getBody());
  }

  function testRecentProgramAction() {
    $feedsController = new FeedsControllerForTest($this->request,$this->response);

    // filter by program
    $this->setUpGet(array('program' => 'Chemistry'));
    $feedsController->recentAction();
    $feedsController->postDispatch();
    $this->assertIsA($feedsController->feed, "Etd_Feed");
    $response = $feedsController->getResponse();
    $this->assertPattern("|<title>.*Recently published : Chemistry.*</title>|", $response->getBody());
  }
  
}

class FeedsControllerForTest extends FeedsController {
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

runtest(new FeedsControllerTest());

?>