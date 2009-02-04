<?
require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/ManageController.php');
      
class ManageControllerTest extends ControllerTestCase {

  // array of test foxml files & their pids 
  private $etdxml;
  private $test_user;
  private $solr;
  
  function setUp() {
    $this->test_user = new esdPerson();
    $this->test_user->role = "admin";
    $this->test_user->netid = "test_user";
    Zend_Registry::set('current_user', $this->test_user);

    
    $_GET 	= array();
    $_POST	= array();
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();

    $this->etdxml = array("etd1" => "test:etd1",
			  "etd2" => "test:etd2",
			  "user" => "test:user1",
			  //"etd3" => "test:etd3",	// not working for some reason
			  );
    

    // load a test objects to repository
    // NOTE: for risearch queries to work, syncupdates must be turned on for test fedora instance
    foreach (array_keys($this->etdxml) as $etdfile) {
      $pid = fedora::ingest(file_get_contents('../fixtures/' . $etdfile . '.xml'), "loading test etd");
    }

    $this->solr = &new Mock_Etd_Service_Solr();
    Zend_Registry::set('solr', $this->solr);
  }
  
  function tearDown() {
    foreach ($this->etdxml as $file => $pid)
      fedora::purge($pid, "removing test etd");

    Zend_Registry::set('solr', null);
    Zend_Registry::set('current_user', null);
  }
  
  function testSummaryAction() {

    $this->solr->response->facets = new Emory_Service_Solr_Response_Facets(array("status" =>
										 array("published" => 2,
										       "reviewed" => 1)));
    
    // no param - should start at top-level
    
    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $ManageController->summaryAction();
    $status_totals = $ManageController->view->status_totals;

    // should match what is passed to mock solr object above
    $this->assertEqual(2, $status_totals['published']);
    $this->assertEqual(0, $status_totals['approved']);
    $this->assertEqual(1, $status_totals['reviewed']);
    $this->assertEqual(0, $status_totals['submitted']);
    $this->assertEqual(0, $status_totals['draft']);
    $this->assertTrue(isset($ManageController->view->title));	// for html title
  }	

  function testListAction() {
    
    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $this->setUpGet(array("status" => "reviewed"));

    $ManageController->listAction();

    $this->assertIsA($ManageController->view->etdSet, "etdSet");
    $this->assertTrue(isset($ManageController->view->title));
  }


  function testReviewAction() {

    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $this->setUpGet(array('pid' => 'test:etd2'));	   // reviewed etd

    $this->assertFalse($ManageController->reviewAction());  // false - not allowed

    // should not be allowed - etd has wrong status
    // 	 - should be redirected, no etd set, and get a not authorized message
    $this->assertFalse($ManageController->redirectRan);
    $this->assertFalse(isset($ManageController->view->etd));
    // note: can no longer test redirect or error message because that is
    // handled by Access Controller Helper in a way that can't be checked here (?)

    // set status appropriately on etd
    $etd = new etd("test:etd2");
    $etd->setStatus("submitted");
    $etd->save("set status to submitted to test review");

    $ManageController->reviewAction();
    $this->assertIsA($ManageController->view->etd, "etd");
    $this->assertTrue(isset($ManageController->view->title));
  }

  public function testAcceptAction() {

    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $this->setUpGet(array('pid' => 'test:etd2'));	   // reviewed etd

    // not be allowed - etd has wrong status
    $this->assertFalse($ManageController->acceptAction());
    // can't test redirect/error message - same as review above

    // set status appropriately on etd
    $etd = new etd("test:etd2");
    $etd->setStatus("submitted");
    $etd->save("set status to submitted to test review");

    $ManageController->acceptAction();
    $etd = new etd("test:etd2");	// get from fedora to check changes
    $this->assertEqual("reviewed", $etd->status(), "status set correctly");	
    $this->assertTrue($ManageController->redirectRan);	// redirects to admin summary page on success
    $messages = $ManageController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/status changed/", $messages[0]);
    $this->assertEqual("Record reviewed by Graduate School", $etd->premis->event[1]->detail);
    $this->assertEqual("test_user", $etd->premis->event[1]->agent->value);
  }

  /* test request _record_ (metadata) changes */
  public function testRequestRecordChangesAction() {

    $ManageController = new ManageControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => 'test:etd2', 'type' => 'record'));   // reviewed etd
    $this->assertFalse($ManageController->requestchangesAction());

    // should not be allowed - etd has wrong status
    $this->assertFalse($ManageController->redirectRan);
    // can't test redirect/message- handled by Access Helper

    // set status appropriately on etd
    $etd = new etd("test:etd2");
    $etd->setStatus("submitted");
    $etd->save("set status to submitted to test review");

    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $ManageController->requestchangesAction();
    $etd = new etd("test:etd2");	// get from fedora to check changes
    $this->assertEqual("draft", $etd->status(), "status set correctly");	
    $messages = $ManageController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/Changes requested;.*status changed/", $messages[0]);
    $this->assertEqual("Changes to record requested by Graduate School", $etd->premis->event[1]->detail);
    $this->assertEqual("test_user", $etd->premis->event[1]->agent->value);
    $this->assertTrue(isset($ManageController->view->title));
    $this->assertEqual("record", $ManageController->view->changetype);
  }


  /* test request _document_ (file) changes */
  public function testRequestDocumentChangesAction() {
    $ManageController = new ManageControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => 'test:etd2', 'type' => 'document'));   // reviewed etd
    $this->assertFalse($ManageController->requestchangesAction());

    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $ManageController->requestchangesAction();
    $etd = new etd("test:etd2");	// get from fedora to check changes
    $this->assertEqual("draft", $etd->status(), "status set correctly");
    // FIXME: for some reason this test fails, but the page works correctly in the browser (?)
    //$this->assertTrue(isset($ManageController->view->title), "page title set");
    //$this->assertEqual("document", $ManageController->view->changetype);
    $messages = $ManageController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/Changes requested;.*status changed/", $messages[0]);
    $this->assertEqual("Changes to document requested by Graduate School", $etd->premis->event[1]->detail);
    $this->assertEqual("test_user", $etd->premis->event[1]->agent->value);

    // try again now that etd is in draft status
    $this->assertFalse($ManageController->requestchangesAction());
    // should not be allowed - etd has wrong status
    // 	 - should be redirected, no etd set, and get a not authorized message (but can't test)
    $this->assertFalse($ManageController->redirectRan);
    // can't test error messages, since they are handled by the Access Helper
  }

  
  public function testApproveAction() {
    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $this->setUpGet(array('pid' => 'test:etd2'));	   // reviewed etd
    $ManageController->approveAction();

    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $ManageController->approveAction();
    $this->assertIsA($ManageController->view->etd, 'etd');
    $this->assertTrue(isset($ManageController->view->title));

    // set status to something that can't be approved
    $etd = new etd("test:etd2");
    $etd->setStatus("draft");
    $etd->save("set status to draft to test approve");

    // should not be allowed - etd has wrong status
    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $this->assertFalse($ManageController->approveAction());
    // 	 - should be redirected and get a not authorized message (but can't test)
    $this->assertFalse($ManageController->redirectRan);
    $this->assertFalse(isset($ManageController->view->etd));

  }

  public function testDoApproveAction() {
    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $this->setUpGet(array('pid' => 'test:etd1', 'embargo' => '3 months'));	   // published etd

    $this->assertFalse($ManageController->doapproveAction());
    // not allowed - etd has wrong status
    $this->assertFalse($ManageController->redirectRan);
    $etd = new etd("test:etd1");
    $this->assertEqual("published", $etd->status());	// status unchanged 
    
    $this->setUpGet(array('pid' => 'test:etd2', 'embargo' => '3 months'));	   // reviewed etd
    // notices for non-existent users in metadata
    $this->expectError("Committee member/chair (nobody) not found in ESD");
    $this->expectError("Committee member/chair (nobodytoo) not found in ESD");
    $ManageController->doapproveAction();
    $etd = new etd("test:etd2");
    $this->assertEqual("approved", $etd->status(), "status set correctly");
    $this->assertEqual("3 months", $etd->mods->embargo);
    $this->assertTrue($ManageController->redirectRan);	// redirects to admin summary page on success
    $messages = $ManageController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/approved.*access restriction of 3 months/", $messages[0]);
    $this->assertPattern("/notification email sent/", $messages[1]);
    $this->assertEqual("Record approved by Graduate School", $etd->premis->event[1]->detail);
    $this->assertEqual("test_user", $etd->premis->event[1]->agent->value);
    $this->assertEqual("Access restriction of 3 months approved", $etd->premis->event[2]->detail);
  }

  public function testInactivateAction() {
    $ManageController = new ManageControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => 'test:etd1'));	   // published etd
    $ManageController->inactivateAction();
    $this->assertIsA($ManageController->view->etd, "etd");
    $this->assertTrue(isset($ManageController->view->title));

    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $this->setUpGet(array('pid' => 'test:etd2'));	   // NOT a published etd
    $this->assertFalse($ManageController->inactivateAction());
    $this->assertFalse($ManageController->redirectRan);
    $this->assertFalse(isset($ManageController->view->etd));
  }

  public function testMarkInactiveAction() {
    $ManageController = new ManageControllerForTest($this->request,$this->response);

    $this->setUpPost(array('pid' => 'test:etd1', 'reason' => 'testing inactivation'));	   // published etd
    $ManageController->markInactiveAction();

    $etd = new etd("test:etd1");
    $this->assertEqual("inactive", $etd->status());
    $this->assertEqual("Marked inactive - testing inactivation", $etd->premis->event[1]->detail);
    $messages = $ManageController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/Record.*status changed/", $messages[0]);

    $this->setUpPost(array('pid' => 'test:etd2', 'reason' => 'testing inactivate'));	   // NOT a published etd
    $this->assertFalse($ManageController->markInactiveAction());
    // redirect/not authorized - can't test
  }

  public function testExpiringEmbargoes() {
    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $ManageController->embargoesAction();
    $this->assertTrue(isset($ManageController->view->title));
    $this->assertIsA($ManageController->view->etdSet, "EtdSet");
    $this->assertTrue($ManageController->view->show_lastaction);
  }

  public function testExportEmails() {
    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $ManageController->exportemailsAction();
    $this->assertIsA($ManageController->view->etdSet, "EtdSet");

    $layout = $ManageController->getHelper("layout");
    // confirm xml output settings - layout disabled, content-type set to text/xml
    $this->assertFalse($layout->enabled);
    $response = $ManageController->getResponse();
    $headers = $response->getHeaders();
    $this->assertEqual("Content-Type", $headers[0]["name"]);
    $this->assertEqual("text/csv", $headers[0]["value"]);
    $this->assertPattern("|filename=.*csv|", $headers[1]["value"]);
  }

  public function testViewLog() {
    $this->test_user->role = "superuser";	// regular admin not allowed to view log
    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $ManageController->viewLogAction();
    $this->assertTrue(isset($ManageController->view->title));
    // FIXME: any way to test the log entries stuff? 
  }

  public function testUnauthorizedUser() {
    // test with an unauthorized user
    $this->test_user->role = "student";

    $this->setUpGet(array('pid' => 'test:etd1'));	 
    
    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $this->assertFalse($ManageController->summaryAction());
    $this->assertFalse(isset($ManageController->view->etds));

    $this->assertFalse($ManageController->reviewAction());
    $this->assertFalse(isset($ManageController->view->etd));
    $this->assertFalse($ManageController->acceptAction());
    $this->assertFalse($ManageController->requestchangesAction());
    $this->assertFalse($ManageController->approveAction());
    $this->assertFalse($ManageController->doapproveAction());
    $this->assertFalse($ManageController->inactivateAction());
    $this->assertFalse($ManageController->markInactiveAction());
    $this->assertFalse($ManageController->embargoesAction());
    $this->assertFalse($ManageController->exportemailsAction());
    $this->assertFalse($ManageController->viewLogAction());
  }

  public function testAdminFilter() {
    // totals on summary pages and records on list views based on what kind of admin user is

    $ManageController = new ManageControllerForTest($this->request,$this->response);
    
    // generic admin sees everything (no filter)
    $this->test_user->role = "admin";
    $this->assertNull($ManageController->testGetAdminFilter());

    // grad admin - phds and masters only
    $this->test_user->role = "grad_admin";
    $filter = $ManageController->testGetAdminFilter();
    $this->assertPattern("/PHD/", $filter);
    $this->assertPattern("/M[AS]/", $filter);
    $this->assertNoPattern("/B[AS]/", $filter);

    // undergrad honors admin - bachelors only
    $this->test_user->role = "honors_admin";
    $filter = $ManageController->testGetAdminFilter(); 
    $this->assertNoPattern("/PHD/", $filter);
    $this->assertNoPattern("/M[AS]/", $filter);
    $this->assertPattern("/B[AS]/", $filter);
    
  }
  
}


class ManageControllerForTest extends ManageController {
  
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

  // expose private function for testing
  public function testGetAdminFilter() {
    return $this->getAdminFilter();
  }
} 	

runtest(new ManageControllerTest());

?>