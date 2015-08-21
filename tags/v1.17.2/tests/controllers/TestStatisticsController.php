<?
require_once("../bootstrap.php"); 
/**
 * unit tests for the Statistics Controller
 */


require_once('../ControllerTestCase.php');
require_once('controllers/StatisticsController.php');
      
class StatisticsControllerTest extends ControllerTestCase {

  // fedoraConnection
  private $fedora;

  private $etdpid;

  function __construct() {
    $this->fedora = Zend_Registry::get("fedora");
  }

  
  function setUp() {

    // get test pid
    $fedora_cfg = Zend_Registry::get('fedora-config');    
    $this->etdpid = $this->fedora->getNextPid($fedora_cfg->pidspace);
        
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();

    // insert some test data into temporary test db
    $statDB = new StatObject();
    $data = array("id" => null, "ip" => "10.0.0.1", "date" => "2008-01-03 12:10:02",
		  "pid" => $this->etdpid, "type" => "abstract", "country" => "United States");
    $statDB->insert($data);
    
  }
  function tearDown() {
    // remove stats
    $db = Zend_Registry::get("stat-db");
    //    $db->delete("stats");
    
    try { $this->fedora->purge($this->etdpid, "removing test etd");  } catch (Exception $e) {}    
        
  }

  function testCountryAction() {
    $statsController = new StatisticsControllerForTest($this->request,$this->response);
    $statsController->countryAction();
    $this->assertTrue(isset($statsController->view->title));
    $this->assertIsA($statsController->view->countries, "CountryNames");
    $this->assertTrue(isset($statsController->view->country));
    $this->assertEqual("United States", $statsController->view->country[0]["country"]);
    $this->assertEqual(1, $statsController->view->country[0]["abstract"]);
    $this->assertEqual(0, $statsController->view->country[0]["file"]);
  }

  function testMonthlyAction() {
    $statsController = new StatisticsControllerForTest($this->request,$this->response);
    $statsController->monthlyAction();
    $this->assertTrue(isset($statsController->view->title));
    $this->assertTrue(isset($statsController->view->month));
    $this->assertEqual("2008-01", $statsController->view->month[0]["month"]);
    $this->assertEqual(2, $statsController->view->month[0]["abstract"]);
    $this->assertEqual(0, $statsController->view->month[0]["file"]);
  }

  function testRecordAction() {
    // load a test objects to repository (etd status is published)
    // (fedora object is only needed for this particular test, not every test in this script)
    $dom = new DOMDocument();
    // load etd & set pid & author relation
    $dom->loadXML(file_get_contents('../fixtures/etd1.xml'));
    $foxml = new foxml($dom);
    $foxml->pid = $this->etdpid;
    $foxml->ingest("loading test etd object");

    
    
    $statsController = new StatisticsControllerForTest($this->request,$this->response);
    $this->setUpGet(array('pid' => $this->etdpid));
    $statsController->recordAction();
    $this->assertTrue(isset($statsController->view->title));
    $this->assertIsA($statsController->view->etd, "etd");
    $this->assertTrue(isset($statsController->view->total));
    $this->assertTrue(isset($statsController->view->country));
    $this->assertTrue(isset($statsController->view->month));
    $this->assertIsA($statsController->view->countries, "CountryNames");

    // FIXME: error handling, user not allowed to see etd?
    $etd = new etd($this->etdpid);
    $etd->setStatus("draft");	   // unpublished e
    $etd->save('setting status to draft to test statistics view');

    $this->setUpGet(array('pid' => $this->etdpid));
    // re-initializing controller so view variables are reset
    $statsController = new StatisticsControllerForTest($this->request,$this->response);
    $this->assertFalse($statsController->recordAction());
    $this->assertFalse(isset($statsController->view->title));
    $this->assertFalse(isset($statsController->view->etd));
    $this->assertFalse(isset($statsController->view->total));
    $this->assertFalse(isset($statsController->view->country));
    $this->assertFalse(isset($statsController->view->month));
    $this->assertFalse(isset($statsController->view->countries));
  }
  

}

class StatisticsControllerForTest extends StatisticsController {
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

runtest(new StatisticsControllerTest());

?>
