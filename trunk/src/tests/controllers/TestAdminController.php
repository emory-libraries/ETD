<?
require_once('ControllerTestCase.php');
require_once('controllers/AdminController.php');
      
class AdminControllerTest extends ControllerTestCase {

  // array of test foxml files & their pids 
  private $etdxml;
  
  function setUp() {
    $_GET 	= array();
    $_POST	= array();
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();

    $this->etdxml = array("etd1" => "test:etd1",
		    "etd2" => "test:etd2",
			  //"etd3" => "test:etd3",	// not working for some reason
			  );
    

    // load a test objects to repository
    // NOTE: for risearch queries to work, syncupdates must be turned on for test fedora instance
    foreach (array_keys($this->etdxml) as $etdfile) {
      $pid = fedora::ingest(file_get_contents('fixtures/' . $etdfile . '.xml'), "loading test etd");
      //      print "ingested $pid\n";
    }

  }
  
  function tearDown() {
    foreach ($this->etdxml as $file => $pid)
      fedora::purge($pid, "removing test etd");
  }
  
  function testSummaryAction() {
    $AdminController = new AdminControllerForTest($this->request,$this->response);
    
    //$this->setUpPost(array('login' => array('username' => 'user_with_pwd', 'pwd' => 'test')));
    
    $AdminController->summaryAction();
    $this->view->status_totals = etd::totals_by_status();

    $viewVars = $AdminController->view->getVars();

    $status_totals = $viewVars['status_totals'];
    // totals based on test objects loaded
    $this->assertEqual(1, $status_totals['published']);
    $this->assertEqual(0, $status_totals['approved']);
    $this->assertEqual(1, $status_totals['reviewed']);
    $this->assertEqual(0, $status_totals['submitted']);
    $this->assertEqual(0, $status_totals['draft']);
  }

  function testListAction() {
    $AdminController = new AdminControllerForTest($this->request,$this->response);
    $this->setUpGet(array("status" => "published"));

    $AdminController->listAction();
    $viewVars = $AdminController->view->getVars();

    //         $this->view->etds = etd::findbyStatus($status);
    $this->assertEqual(1, count($viewVars['etds']));
    $this->assertIsA($viewVars['etds'], "array");
    $this->assertIsA($viewVars['etds'][0], "etd");
  }

}


class AdminControllerForTest extends AdminController {
  
  public $renderRan = false;
  public $redirectRan = false;
  
  public function initView() {
    $this->view = new Zend_View();
  }
  
  public function render() {
    $this->renderRan = true;
  }
  
  public function _redirect() {
    $this->redirectRan = true;
  }
} 	


?>