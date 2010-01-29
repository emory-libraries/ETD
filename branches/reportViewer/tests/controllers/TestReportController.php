<?
require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/ReportController.php');
      
class ReportControllerTest extends ControllerTestCase {

  /**
   * test user - fields to be initialized as needed for tests
   * @var esdPerson
   */
  private $test_user;
  
  /**
   * mock solr object - set response document as needed for test
   * @var Mock_Etd_Service_Solr
   */
  private $solr;

  private $etd_pid;
  private $author_pid;

  /**
   * copy of fedora connection from registry
   * @var fedoraConnection
   */
  private $fedora;

  function __construct() {
    $this->fedora = Zend_Registry::get("fedora");
    $fedora_cfg = Zend_Registry::get('fedora-config');

    // get pids for test objects
    list($this->etd_pid, $this->author_pid) = $this->fedora->getNextPid($fedora_cfg->pidspace, 2);
  }

  
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

    
    $dom = new DOMDocument();
    // load etd & set pid 
    $dom->loadXML(file_get_contents('../fixtures/etd2.xml'));
    $foxml = new foxml($dom);
    $foxml->pid = $this->etd_pid;
    $this->fedora->ingest($foxml->saveXML(), "loading test etd object");
    
    // load author info & set pid 
    $dom->loadXML(file_get_contents('../fixtures/user.xml'));
    $foxml = new foxml($dom);
    $foxml->pid = $this->author_pid;
    $this->fedora->ingest($foxml->saveXML(), "loading test authorInfo");


    
    $this->solr = &new Mock_Etd_Service_Solr();
    Zend_Registry::set('solr', $this->solr);

    $fname = '../fixtures/etd2.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $this->etd = new etd($dom);


  }
  
  function tearDown() {
    Zend_Registry::set('solr', null);
    Zend_Registry::set('current_user', null);
    $this->fedora->purge($this->etd_pid, "removing test etd");
    $this->fedora->purge($this->author_pid, "removing test author info");

  }

  function testcommencementReviewAction() {
    $ReportController = new ReportControllerForTest($this->request,$this->response);
    $ReportController->commencementReviewAction();

    $this->assertTrue(isset($ReportController->view->title));
    $etdSet = $ReportController->view->etdSet;
    $this->assertIsA($etdSet, 'EtdSet', "etdSet is a etdSet object.");

    //test as student,  all other tests are done as admin
    $this->test_user->role = "student";
    Zend_Registry::set('current_user', $this->test_user);
    $this->assertFalse($ReportController->commencementAction() );
}

  
  function testcommencementAction() {
    $ReportController = new ReportControllerForTest($this->request,$this->response);

    //set post data
    $this->setUpPost(array('exclude' => array("test:etd2")));

    $this->solr->response->docs[] = new Emory_Service_Solr_Response_Document(array("PID" => "test:etd1",
										    "pubdate" => "20090901"));
    $this->solr->response->docs[] = new Emory_Service_Solr_Response_Document(array("PID" => "test:etd2"));

    $ReportController->commencementAction();

    $this->assertTrue(isset($ReportController->view->title));
    $this->assertTrue(isset($ReportController->view->startDate));
    $this->assertTrue(isset($ReportController->view->endDate));
    $this->assertPattern("/\d{4}\-\d{2}\-\d{2}/", $ReportController->view->startDate, "Format should be Y-m-d");
    $this->assertPattern("/\d{4}\-\d{2}\-\d{2}/", $ReportController->view->endDate, "Format should be Y-m-d");
    
    $etdSet = $ReportController->view->etdSet;
    $this->assertIsA($etdSet, 'EtdSet', "etdSet is a etdSet object.");

    //Make sure it removed 1 etd
    $this->assertEqual(1, count($etdSet->etds), "excluded etd removed from etd result");

    //Make sure remaining is correct one
    $this->assertEqual("test:etd1", $etdSet->etds[0]->pid(), "remaining etd maches non-excluded pid");
    $this->assertEqual("**", $etdSet->etds[0]->semester, "etd has semester indicator set correctly");

    
    //test as student,  all other tests are done as admin
    $this->test_user->role = "student";
    Zend_Registry::set('current_user', $this->test_user);
    $this->assertFalse($ReportController->commencementAction() );
    
}

function testgradDataAction() {
    $field = "dateIssued";
    $this->solr->response->facets = new Emory_Service_Solr_Response_Facets(array("dateIssued" =>
										 array("20070604" => "1",
										      "20070919" => "2",
                                              "20071231" => "3",
                                              "20080531" => "4",
                                              "20080831" => "5",
                                              "20081231" =>  "6",
                                              "20090531" => "7",
                                              "20090831" => "8" )));

    $ReportController = new ReportControllerForTest($this->request,$this->response);
    $ReportController->gradDataAction();

    $this->assertTrue(isset($ReportController->view->options));
    $this->assertTrue(isset($ReportController->view->curStart));
    $this->assertTrue(isset($ReportController->view->curEnd));

    //test as student,  all other tests are done as admin
    $this->test_user->role = "student";
    Zend_Registry::set('current_user', $this->test_user);
    $this->assertFalse($ReportController->gradDataAction() );

}

function testgradDataCsvAction() {

    $ReportController = new ReportControllerForTest($this->request,$this->response);
    //set post data
    $this->setUpPost(array('academicYear' => "20081231:20090831"));
    $etd = &new MockEtd();
    $etd->PID = $this->etd_pid;
    //print "PID: $this->pid";
    $this->solr->response->docs[] = $etd;

    $ReportController->gradDataCsvAction();

    $this->assertTrue(isset($ReportController->view->data));
    
    //Test to make sure data is an array of arrays
    $this->assertisA($ReportController->view->data, "array");

    foreach($ReportController->view->data as $line){
        $this->assertisA($line, "array");
    }

    $this->assertEqual($ReportController->view->data[1][1], "Duck, Daffy");
    $this->assertEqual($ReportController->view->data[1][22], "Dissertation");

    $response = $ReportController->getResponse();
    $headers = $response->getHeaders();
    $this->assertEqual("Content-Type", $headers[0]["name"]);
    $this->assertEqual("text/csv", $headers[0]["value"]);
    $this->assertEqual("Content-Disposition", $headers[1]["name"]);
    $this->assertPattern("/attachment; filename=\"GradReport-\d{8}-\d{8}\.csv\"/", $headers[1]["value"]);
       
    //test as student,  all other tests are done as admin
    $this->test_user->role = "student";
    Zend_Registry::set('current_user', $this->test_user);
    $this->assertFalse($ReportController->gradDataCsvAction() );
}

 function testembargoCsvAction() {

    $ReportController = new ReportControllerForTest($this->request,$this->response);

    $etd = &new MockEtd();
    $etd->PID = $this->etd_pid;
    $this->solr->response->docs[] = $etd;

    $ReportController->embargoCsvAction();

    $this->assertTrue(isset($ReportController->view->data));

    //Test to make sure data is an array of arrays
    $this->assertisA($ReportController->view->data, "array");

    foreach($ReportController->view->data as $line){
        $this->assertisA($line, "array");
    }

    $response = $ReportController->getResponse();
    $headers = $response->getHeaders();
    $this->assertEqual("Content-Type", $headers[0]["name"]);
    $this->assertEqual("text/csv", $headers[0]["value"]);
    $this->assertEqual("Content-Disposition", $headers[1]["name"]);
    $this->assertEqual("attachment; filename=\"EmbargoReport.csv\"", $headers[1]["value"]);

    //test as student,  all other tests are done as admin
    $this->test_user->role = "student";
    Zend_Registry::set('current_user', $this->test_user);
    $this->assertFalse($ReportController->embargoCsvAction() );
}


  function testGetCommencementDateRange() {
    $ReportController = new ReportControllerForTest($this->request,$this->response);
    list($startDate, $endDate) = $ReportController->getCommencementDateRange();

    $this->assertEqual("06-01",  (date("m-d", $startDate)));
    $this->assertEqual("05-31",  (date("m-d", $endDate)));
    $this->assertEqual(1,  date("Y", $endDate) - date("Y", $startDate));
    
        
  }

  function testAddCSVFields(){
      $ReportController = new ReportControllerForTest($this->request,$this->response);

      $line = $ReportController->addCSVFields($this->etd, "chair", array("id", "full"), 3);
      $this->assertIsA($line, "array");
      $this->assertEqual(count($line), 6, "6 entries - id & fullname, 3 times");
      $this->assertEqual("nobody", $line[0], "first entry should be advisor id 'nobody', got '"
			 . $line[0] . "'");
      $this->assertEqual("Person, Advisor", $line[1], "first entry should be advisor fullname 'Person, Advisor', got '" . $line[1] . "'");
      $this->assertEqual("", $line[2], "id for second advisor should be blank (no second advisor)");
      $this->assertEqual("", $line[3], "full name for second advisor should be blank (no second advisor)");

      // ignore php errors - "indirect modification of overloaded property
      $errlevel = error_reporting(E_ALL ^ E_NOTICE);

      // weird netids should not be included in output
      $this->etd->mods->chair[0]->id = "weird_id";
      $line = $ReportController->addCSVFields($this->etd, "chair", array("id", "full"), 1);
      $this->assertNotEqual("weird_id", $line[0]);

      error_reporting($errlevel);	    // restore prior error reporting
  }
  
  function testGetSemesterDecorator() {
    $ReportController = new ReportControllerForTest($this->request,$this->response);
    
    $result = $ReportController->getSemesterDecorator("20090101");
    $this->assertEqual($result, "");

    $result = $ReportController->getSemesterDecorator("20090301");
    $this->assertEqual($result, "");

    $result = $ReportController->getSemesterDecorator("20090501");
    $this->assertEqual($result, "");

    $result = $ReportController->getSemesterDecorator("20090601");
    $this->assertEqual($result, "*");

    $result = $ReportController->getSemesterDecorator("20090701");
    $this->assertEqual($result, "*");

    $result = $ReportController->getSemesterDecorator("20090801");
    $this->assertEqual($result, "*");

    $result = $ReportController->getSemesterDecorator("20090901");
    $this->assertEqual($result, "**");

        $result = $ReportController->getSemesterDecorator("20091001");
    $this->assertEqual($result, "**");

        $result = $ReportController->getSemesterDecorator("20091201");
    $this->assertEqual($result, "**");
    
  }

  function testPreDispatch() {
    $this->test_user->role = "admin";
    $ReportController = new ReportControllerForTest($this->request,$this->response);
    $ReportController->preDispatch();
    $tmp_fedoraConnection = Zend_Registry::get("fedora");
    $this->assertEqual($this->fedora, $ReportController->getUserFedoraConnection(),
		       "main fedora connection stored in controller");   
    $this->assertEqual($this->fedora, $tmp_fedoraConnection,
		       "fedora connection in registry is same as main fedora connection for admin user");

    $this->test_user->role = "report viewer";
    $ReportController->preDispatch();
    $tmp_fedoraConnection = Zend_Registry::get("fedora");
    $this->assertEqual($this->fedora, $ReportController->getUserFedoraConnection(),
		       "main fedora connection stored in controller");   
    $this->assertNotEqual($this->fedora, $tmp_fedoraConnection,
			  "fedora connection in registry is NOT same as main fedora connection for a report viewer");

    // undo change - restore real fedora connection
    Zend_Registry::set("fedora", $this->fedora);
  }

  function testPostDispatch(){
    $this->test_user->role = "report viewer";
    $ReportController = new ReportControllerForTest($this->request,$this->response);
    // run preDispatch to override fedoraconnection
    $ReportController->preDispatch();

    $ReportController->postDispatch();
    $fedoraConnection = Zend_Registry::get("fedora");
    $this->assertEqual($this->fedora, $fedoraConnection,
		       "fedora connection in registry is same as main fedora connection - restored by postDispatch");
    
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

  // provide acccess to fedora connection for testing pre/post dispatch
  public function getUserFedoraConnection() {
    return $this->_fedoraConnection;
  }
} 	

runtest(new ReportControllerTest());

?>