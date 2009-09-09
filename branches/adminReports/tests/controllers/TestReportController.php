<?
require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/ReportController.php');
      
class ReportControllerTest extends ControllerTestCase {

  // array of test foxml files & their pids 
  private $etdxml;
  private $test_user;
  private $solr;
  
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

    $fedora=Zend_Registry::get("fedora");
    $this->pid = $fedora->ingest(file_get_contents('../fixtures/etd2.xml'), "loading test etd");

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
    $fedora=Zend_Registry::get("fedora");
    $fedora->purge($this->pid, "removing test etd");

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
    $etd->PID = $this->pid;
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
       
    //print "<pre>";
    //print_r($ReportController->view->data);
    //print_r($headers);
    //print "</pre>";


    //test as student,  all other tests are done as admin
    $this->test_user->role = "student";
    Zend_Registry::set('current_user', $this->test_user);
    $this->assertFalse($ReportController->gradDataCsvAction() );
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

      $line=$ReportController->addCSVFields($this->etd, "chair", array("id", "full"), 3);

      $this->assertIsA($line, "array");
      $this->assertEqual(count($line), 6);
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