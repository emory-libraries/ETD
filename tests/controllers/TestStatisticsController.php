<?
require_once("../bootstrap.php"); 
/**
 * unit tests for the Config Controller
 * - display configuration xml (used in various xforms)
 */


require_once('../ControllerTestCase.php');
require_once('controllers/StatisticsController.php');
      
class StatisticsControllerTest extends ControllerTestCase {

  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();

    // insert some test data into temporary test db
    $statDB = new StatObject();
    $data = array("id" => null, "ip" => "10.0.0.1", "date" => "2008-01-03 12:10:02",
		  "pid" => "test:etd1", "type" => "abstract", "country" => "United States");
    $statDB->insert($data);
    
  }
  function tearDown() {
    // remove stats
    $db = Zend_Registry::get("stat-db");
    $db->delete("stats");
  }

  function testCountryAction() {
    $statsController = new StatisticsControllerForTest($this->request,$this->response);
    $statsController->countryAction();
    $viewVars = $statsController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertIsA($viewVars['countries'], "CountryNames");
    $this->assertTrue(isset($viewVars['country']));
    $this->assertEqual("United States", $viewVars['country'][0]["country"]);
    $this->assertEqual(1, $viewVars['country'][0]["abstract"]);
    $this->assertEqual(0, $viewVars['country'][0]["file"]);
  }

  function testMonthlyAction() {
    $statsController = new StatisticsControllerForTest($this->request,$this->response);
    $statsController->monthlyAction();
    $viewVars = $statsController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertTrue(isset($viewVars['month']));
    $this->assertEqual("2008-01", $viewVars['month'][0]["month"]);
    $this->assertEqual(1, $viewVars['month'][0]["abstract"]);
    $this->assertEqual(0, $viewVars['month'][0]["file"]);
  }

  function testRecordAction() {
    // load a test objects to repository (etd status is published)
    // (fedora object is only needed for this particular test, not every test in this script)
    $pid = fedora::ingest(file_get_contents('../fixtures/etd1.xml'), "loading test etd");
    
    $statsController = new StatisticsControllerForTest($this->request,$this->response);
    $this->setUpGet(array('pid' => $pid));
    $statsController->recordAction();
    $viewVars = $statsController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertIsA($viewVars['etd'], "etd");
    $this->assertTrue(isset($viewVars['total']));
    $this->assertTrue(isset($viewVars['country']));
    $this->assertTrue(isset($viewVars['month']));
    $this->assertIsA($viewVars['countries'], "CountryNames");

    // FIXME: error handling, user not allowed to see etd?
    $etd = new etd($pid);
    $etd->setStatus("draft");	   // unpublished etd
    $etd->save('setting status to draft to test statistics view');

    $this->setUpGet(array('pid' => $pid));
    // re-initializing controller so view variables are reset
    $statsController = new StatisticsControllerForTest($this->request,$this->response);
    $this->assertFalse($statsController->recordAction());
    $viewVars = $statsController->view->getVars();
    $this->assertFalse(isset($viewVars['title']));
    $this->assertFalse(isset($viewVars['etd']));
    $this->assertFalse(isset($viewVars['total']));
    $this->assertFalse(isset($viewVars['country']));
    $this->assertFalse(isset($viewVars['month']));
    $this->assertFalse(isset($viewVars['countries']));
    
    
    fedora::purge($pid, "removing test etd");
  }
  

}

class StatisticsControllerForTest extends StatisticsController {
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

runtest(new StatisticsControllerTest());

?>