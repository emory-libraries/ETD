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
  
  function testLastYearAction() {
    $ReportController = new ReportControllerForTest($this->request,$this->response);
    $ReportController->lastyearAction();

    $this->assertTrue(isset($ReportController->view->title));
    
    $etdSet = $ReportController->view->etdSet;
    $this->assertIsA($etdSet, 'EtdSet', "etdSet is a etdSet object.");
    if($etdSet!=null && count($etdSet)>0) {
    	foreach ($etdSet->etds as $etd) {
    		$this->assertTrue($etd!=null,"ETD should not be null");
    		$this->assertTrue($etd->author()!=null,"ETD Author should not be null");
			$this->assertTrue($etd->title()!=null,"ETD Title should not be null");
    		$this->assertTrue($etd!=null,"ETD should not be null");
    		$etdAdvisors = $etd->chair(); 
    		if($etdAdvisors!=null && count($etdAdvisors)>0) {
    			foreach ($etdAdvisors as $advisor) {
    				$this->assertTrue($advisor!=NULL,"Advisor: " . $advisor . "should not be null");
    			}
    		} 
    	}
	}
  }	

  function testGetLastYearDateRange() {
    $ReportController = new ReportControllerForTest($this->request,$this->response);
    $lastYearDateRange = $ReportController->getLastYearDateRange();
    
    $this->assertPattern("/\[\d{8}\s[T][O]\s\d{8}\]/", $lastYearDateRange, "Last Year Date Range should be in format: [yyyymmdd TO yyyymmdd]. " . $lastYearDateRange );
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