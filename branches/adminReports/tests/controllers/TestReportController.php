<?
require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/ReportController.php');
      
class ReportControllerTest extends ControllerTestCase {

  // array of test foxml files & their pids 
  private $etdxml;
  private $test_user;
  
  function setUp() {
    $ep = new esdPerson();
    $this->test_user = $ep->getTestPerson();
    $this->test_user->role = "admin";
    $this->test_user->netid = "test_user";
    Zend_Registry::set('current_user', $this->test_user);

    
    $_GET 	= array();
    $_POST	= array();
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();

//    $this->etdxml = array("etd1" => "test:etd1",
//			  "etd2" => "test:etd2",
//			  "user" => "test:user1",
//			  //"etd3" => "test:etd3",	// not working for some reason
//			  );
//    
//
//    // load a test objects to repository
//    // NOTE: for risearch queries to work, syncupdates must be turned on for test fedora instance
//    foreach (array_keys($this->etdxml) as $etdfile) {
//      $pid = fedora::ingest(file_get_contents('../fixtures/' . $etdfile . '.xml'), "loading test etd");
//    }
//
    $solr = &new Mock_Etd_Service_Solr();
    Zend_Registry::set('solr', $solr);
  }
  
  function tearDown() {
//    foreach ($this->etdxml as $file => $pid)
//      fedora::purge($pid, "removing test etd");
//
    Zend_Registry::set('solr', null);
    Zend_Registry::set('current_user', null);
  }
  
  function testcommencementAction() {
    $ReportController = new ReportControllerForTest($this->request,$this->response);
    $ReportController->commencementAction();

    $this->assertTrue(isset($ReportController->view->title));
    $this->assertTrue(isset($ReportController->view->startDate));
    $this->assertTrue(isset($ReportController->view->endDate));
    $this->assertPattern("/\d{4}\-\d{2}\-\d{2}/", $ReportController->view->startDate, "Format should be Y-m-d");
    $this->assertPattern("/\d{4}\-\d{2}\-\d{2}/", $ReportController->view->endDate, "Format should be Y-m-d");
    
    $etdSet = $ReportController->view->etdSet;
    $this->assertIsA($etdSet, 'EtdSet', "etdSet is a etdSet object.");
    
    //test as student,  all other tests are done as admin
    $this->test_user->role = "student";
    Zend_Registry::set('current_user', $this->test_user);
    $this->assertFalse($ReportController->commencementAction() );
    
}

  function testGetCommencementDateRange() {
    $ReportController = new ReportControllerForTest($this->request,$this->response);
    list($startDate, $endDate) = $ReportController->getCommencementDateRange();

    $this->assertEqual("06-01",  (date("m-d", $startDate)));
    $this->assertEqual("05-31",  (date("m-d", $endDate)));
    $this->assertEqual(1,  date("Y", $endDate) - date("Y", $startDate));
    
        
  }	

}


class ReportControllerForTest extends ReportController {
  
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

runtest(new ReportControllerTest());

?>