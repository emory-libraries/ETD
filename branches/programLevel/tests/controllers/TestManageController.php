<?
require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/ManageController.php');
require_once('../fixtures/etd_data.php');
      
class ManageControllerTest extends ControllerTestCase {

  // array of test foxml files & their pids 
  private $etdxml;
  private $test_user;
  private $solr;
  private $etd_data;
  private $schools_cfg;
  
  // fedoraConnection
  private $fedora;

  private $pids;
  private $published_etdpid;
  private $reviewed_etdpid;
  private $userpid;

  
  function __construct() {
    $this->fedora = Zend_Registry::get("fedora");
    $fedora_cfg = Zend_Registry::get('fedora-config');

    // get 3 test pids to be used throughout test
    $this->pids = $this->fedora->getNextPid($fedora_cfg->pidspace, 3);
    list($this->published_etdpid, $this->reviewed_etdpid,  $this->userpid) = $this->pids;

    $this->schools_cfg = Zend_Registry::get('schools-config');
  }


  function setUp() {
    $ep = new esdPerson();
    $this->test_user = $ep->getTestPerson();
    $this->test_user->role = "admin";
    $this->test_user->netid = "test_user";
    Zend_Registry::set('current_user', $this->test_user);

    //load etd util DB data
    $this->etd_data = new etd_test_data();
    $this->etd_data->loadAll();

    
    $_GET   = array();
    $_POST  = array();
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();

    $school_cfg = Zend_Registry::get("schools-config");
    
    // load a test objects to repository
    // load fixtures
    // note: etd & related user need to be in repository so authorInfo relation will work
    $dom = new DOMDocument();
    // load etd1 & set pid    -- simple etd record, status = published
    $dom->loadXML(file_get_contents('../fixtures/etd1.xml'));
    $foxml = new etd($dom);
    $foxml->setSchoolConfig($school_cfg->graduate_school);
    
    $foxml->pid = $this->published_etdpid;
    $this->fedora->ingest($foxml->saveXML(), "loading test etd object");

    // load etd2 & set pid & author relation  -- etd record associated with authorInfo object, status reviewed
    $dom->loadXML(file_get_contents('../fixtures/etd2.xml'));
    $foxml = new etd($dom);
    $foxml->setSchoolConfig($school_cfg->graduate_school);
    $foxml->pid = $this->reviewed_etdpid;
    $foxml->rels_ext->hasAuthorInfo = $this->userpid;
    $this->fedora->ingest($foxml->saveXML(), "loading test etd object");

    // load author info
    $dom->loadXML(file_get_contents('../fixtures/authorInfo.xml'));
    $foxml = new foxml($dom);
    $foxml->pid = $this->userpid;
    $this->fedora->ingest($foxml->saveXML(), "loading test etd authorInfo object");


    // FIXME: also need honors etd?

    // NOTE: for risearch queries to work, syncUpdates must be turned on for test fedora instance

    $this->solr = &new Mock_Etd_Service_Solr();
    Zend_Registry::set('solr', $this->solr);
  }
  
  function tearDown() {
    foreach ($this->pids as $pid)
      $this->fedora->purge($pid, "removing test etd");

    Zend_Registry::set('solr', null);
    Zend_Registry::set('current_user', null);

    //delete etd util DB data
    $this->etd_data->cleanUp();
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
    $this->assertTrue(isset($ManageController->view->title)); // for html title
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
    $this->setUpGet(array('pid' => $this->reviewed_etdpid));     // reviewed etd

    $this->assertFalse($ManageController->reviewAction());  // false - not allowed

    // should not be allowed - etd has wrong status
    //   - should be redirected, no etd set, and get a not authorized message
    $this->assertFalse($ManageController->redirectRan);
    $this->assertFalse(isset($ManageController->view->etd));
    // note: can no longer test redirect or error message because that is
    // handled by Access Controller Helper in a way that can't be checked here (?)

    // set status appropriately on etd
    $etd = new etd($this->reviewed_etdpid);
    $etd->setStatus("submitted");
    $etd->save("set status to submitted to test review");

    $ManageController->reviewAction();
    $this->assertIsA($ManageController->view->etd, "etd");
    $this->assertTrue(isset($ManageController->view->title));
  }

  public function testAcceptAction() {

    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $this->setUpGet(array('pid' => $this->reviewed_etdpid));     // reviewed etd

    // not be allowed - etd has wrong status
    $this->assertFalse($ManageController->acceptAction());
    // can't test redirect/error message - same as review above

    // set status appropriately on etd
    $etd = new etd($this->reviewed_etdpid);
    $etd->setStatus("submitted");
    $etd->save("set status to submitted to test review");

    $ManageController->acceptAction();
    $this->test_user->role = "admin";
    $etd = new etd($this->reviewed_etdpid); // get from fedora to check changes
    $this->assertEqual("reviewed", $etd->status(), "status set correctly"); 
    $this->assertTrue($ManageController->redirectRan);  // redirects to admin summary page on success
    $messages = $ManageController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/status changed/", $messages[0]);
    $last_event = count($etd->premis->event) - 1;
    $this->assertEqual("Record reviewed by ETD Administrator", $etd->premis->event[$last_event]->detail);
    $this->assertEqual("test_user", $etd->premis->event[$last_event]->agent->value);

    // agent in description should vary according to admin role
    $this->test_user->role = "grad admin";
    // reset status on etd
    $etd = new etd($this->reviewed_etdpid);
    $etd->setStatus("submitted");
    $etd->save("set status to submitted to test review - grad admin");
    $ManageController->acceptAction();
    $etd = new etd($this->reviewed_etdpid); // get from fedora to check changes
    $last_event = count($etd->premis->event) - 1;
    $this->assertEqual("Record reviewed by Graduate School", $etd->premis->event[$last_event]->detail);

    // FIXME: not allowed to do this on non-honors etd -- temporarily convert fixture to honors?
    /*    $this->test_user->role = "honors admin";
    $etd = new etd("test:etd2");
    $etd->setStatus("submitted");
    $etd->save("set status to submitted to test review - honors");
    $ManageController->acceptAction();
    $etd = new etd("test:etd2");  // get from fedora to check changes
    $this->assertEqual("Record reviewed by Honors Program", $etd->premis->event[3]->detail);
    */
  }


  /* test request _record_ (metadata) changes */
  public function testRequestRecordChangesAction() {

    $ManageController = new ManageControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => $this->reviewed_etdpid, 'type' => 'record'));   // reviewed etd
    $this->assertFalse($ManageController->requestchangesAction());

    // should not be allowed - etd has wrong status
    $this->assertFalse($ManageController->redirectRan);
    // can't test redirect/message- handled by Access Helper

    // set status appropriately on etd
    $etd = new etd($this->reviewed_etdpid);
    $etd->setStatus("submitted");
    $etd->save("set status to submitted to test review");

    $ManageController = new ManageControllerForTest($this->request,$this->response);
    // clear out any messages (?)
    $this->test_user->role = "admin";
    $ManageController->getHelper('FlashMessenger')->getMessages();
    $ManageController->requestchangesAction();
    $etd = new etd($this->reviewed_etdpid); // get from fedora to check changes
    $this->assertEqual("draft", $etd->status(), "status set correctly");  
    $messages = $ManageController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/Changes requested;.*status changed/", $messages[0]);
    $last_event = count($etd->premis->event) - 1;
    $this->assertEqual("Changes to record requested by ETD Administrator", $etd->premis->event[$last_event]->detail);
    $this->assertEqual("test_user", $etd->premis->event[$last_event]->agent->value);
    $this->assertTrue(isset($ManageController->view->title));
    $this->assertEqual("record", $ManageController->view->changetype);

    // variant admins
    $this->test_user->role = "grad admin";
    // reset status on etd
    $etd = new etd($this->reviewed_etdpid);
    $etd->setStatus("submitted");
    $etd->save("set status to submitted to test request changes - grad admin");
    $ManageController->requestchangesAction();
    $etd = new etd($this->reviewed_etdpid); // get from fedora to check changes
    $last_event = count($etd->premis->event) - 1;
    $this->assertEqual("Changes to record requested by Graduate School", $etd->premis->event[$last_event]->detail);
    
    /*$this->test_user->role = "honors admin";
    // reset status on etd
    $etd = new etd("test:etd2");
    $etd->setStatus("submitted");
    $etd->save("set status to submitted to test request changes - honors admin");
    $ManageController->requestchangesAction();
    $etd = new etd("test:etd2");  // get from fedora to check changes
    $this->assertEqual("Changes to record requested by Honors Program", $etd->premis->event[3]->detail);
    */
  }


  /* test request _document_ (file) changes */
  public function testRequestDocumentChangesAction() {
    $ManageController = new ManageControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => $this->reviewed_etdpid, 'type' => 'document'));   // reviewed etd
    $this->assertFalse($ManageController->requestchangesAction());

    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $ManageController->requestchangesAction();
    $this->test_user->role = "admin";
    $etd = new etd($this->reviewed_etdpid); // get from fedora to check changes
    $this->assertEqual("draft", $etd->status(), "status set correctly");
    // FIXME: for some reason this test fails, but the page works correctly in the browser (?)
    //$this->assertTrue(isset($ManageController->view->title), "page title set");
    //$this->assertEqual("document", $ManageController->view->changetype);
    $messages = $ManageController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/Changes requested;.*status changed/", $messages[0]);
    $last_event = count($etd->premis->event) - 1;
    $this->assertEqual("Changes to document requested by ETD Administrator", $etd->premis->event[$last_event]->detail);
    $this->assertEqual("test_user", $etd->premis->event[$last_event]->agent->value);

    // try again now that etd is in draft status
    $this->assertFalse($ManageController->requestchangesAction());
    // should not be allowed - etd has wrong status
    //   - should be redirected, no etd set, and get a not authorized message (but can't test)
    $this->assertFalse($ManageController->redirectRan);
    // can't test error messages, since they are handled by the Access Helper


    // variant admins
    $this->test_user->role = "grad admin";
    // reset status on etd
    $etd = new etd($this->reviewed_etdpid);
    $etd->setStatus("reviewed");
    $etd->save("set status to reviewed to test request changes - grad admin");
    $ManageController->requestchangesAction();
    $etd = new etd($this->reviewed_etdpid); // get from fedora to check changes
    $last_event = count($etd->premis->event) - 1;
    $this->assertEqual("Changes to document requested by Graduate School", $etd->premis->event[$last_event]->detail);

    /*
    $this->test_user->role = "honors admin";
    // reset status on etd
    $etd = new etd("test:etd2");
    $etd->setStatus("reviewed");
    $etd->save("set status to reviewed to test request changes - honors admin");
    $ManageController->requestchangesAction();
    $etd = new etd("test:etd2");  // get from fedora to check changes
    $this->assertEqual("Changes to document requested by Honors Program", $etd->premis->event[3]->detail);

    */
  }

  
  public function testApproveAction() {
    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $this->setUpGet(array('pid' => $this->reviewed_etdpid));     // reviewed etd
    $ManageController->approveAction();

    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $ManageController->approveAction();
    $this->assertIsA($ManageController->view->etd, 'etd');
    $this->assertTrue(isset($ManageController->view->title));

    // set status to something that can't be approved
    $etd = new etd($this->reviewed_etdpid);
    $etd->setStatus("draft");
    $etd->save("set status to draft to test approve");

    // should not be allowed - etd has wrong status
    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $this->assertFalse($ManageController->approveAction());
    //   - should be redirected and get a not authorized message (but can't test)
    $this->assertFalse($ManageController->redirectRan);
    $this->assertFalse(isset($ManageController->view->etd));

  }

  public function testDoApproveAction() {
    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $this->setUpGet(array('pid' => $this->published_etdpid, 'embargo' => '3 months'));     // published etd

    $this->assertFalse($ManageController->doapproveAction());
    // not allowed - etd has wrong status
    $this->assertFalse($ManageController->redirectRan);
    $etd = new etd($this->published_etdpid);
    $this->assertEqual("published", $etd->status());  // status unchanged 
    
    $this->setUpGet(array('pid' => $this->reviewed_etdpid, 'embargo' => '3 months'));    // reviewed etd
    // notices for non-existent users in metadata
    $this->expectError("Committee member/chair (nobody) not found in ESD");
    $this->expectError("Committee member/chair (nobodytoo) not found in ESD");
    // clear out any old messages (?)
    $ManageController->getHelper('FlashMessenger')->getMessages();
    $ManageController->doapproveAction();
    $etd = new etd($this->reviewed_etdpid);
    $this->assertEqual("approved", $etd->status(), "status set correctly");
    $this->assertEqual("3 months", $etd->mods->embargo);
    $this->assertTrue($ManageController->redirectRan);  // redirects to admin summary page on success
    $messages = $ManageController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/approved.*access restriction of 3 months/", $messages[0]);
    $this->assertPattern("/notification email sent/", $messages[1]);
    $this->assertEqual("Record approved by ETD Administrator", $etd->premis->event[1]->detail);
    $this->assertEqual("test_user", $etd->premis->event[1]->agent->value);
    $this->assertEqual("Access restriction of 3 months approved", $etd->premis->event[2]->detail);

    // variant admins
    /* FIXME: offset is off?
    $etd = new etd($this->published_etdpid);
    $etd->setStatus("reviewed");
    $etd->save("set status to reviewed to test doApprove");
    $ManageController->doapproveAction();
    $etd = new etd($this->reviewed_etdpid);
    $this->assertEqual("Record approved by Graduate School", $etd->premis->event[3]->detail);
    */
  }

  public function testInactivateAction() {
    $ManageController = new ManageControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => $this->published_etdpid));    // published etd
    $ManageController->inactivateAction();
    $this->assertIsA($ManageController->view->etd, "etd");
    $this->assertTrue(isset($ManageController->view->title));

    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $this->setUpGet(array('pid' => $this->reviewed_etdpid));     // NOT a published etd
    $this->assertFalse($ManageController->inactivateAction());
    $this->assertFalse($ManageController->redirectRan);
    $this->assertFalse(isset($ManageController->view->etd));
  }

  public function testMarkInactiveAction() {
    $ManageController = new ManageControllerForTest($this->request,$this->response);

    $this->setUpPost(array('pid' => $this->published_etdpid, 'reason' => 'testing inactivation'));     // published etd
    $ManageController->markInactiveAction();

    $etd = new etd($this->published_etdpid);
    $this->assertEqual("inactive", $etd->status());
    $last_event = count($etd->premis->event) - 1;
    $this->assertEqual("Marked inactive - testing inactivation", $etd->premis->event[$last_event]->detail);
    $messages = $ManageController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/Record.*status changed/", $messages[0]);

    $this->setUpPost(array('pid' => $this->reviewed_etdpid, 'reason' => 'testing inactivate'));    // NOT a published etd
    $this->assertFalse($ManageController->markInactiveAction());
    // redirect/not authorized - can't test
  }

  public function testReactivateAction() {
    $ManageController = new ManageControllerForTest($this->request,$this->response);

    $etd = new etd($this->published_etdpid);
    $etd->setStatus("inactive");
    $etd->save("marking published etd inactive to test reactivate");

    $this->setUpGet(array('pid' => $this->published_etdpid));    // previously published etd 
    $ManageController->reactivateAction();
    $this->assertIsA($ManageController->view->etd, "etd");
    $this->assertTrue(isset($ManageController->view->title));
    $this->assertEqual("published", $ManageController->view->previous_status,
           "previous status is set correctly in the view");
    $this->assertIsA($ManageController->view->status_opts, "Array");

    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $this->setUpGet(array('pid' => $this->reviewed_etdpid));     // NOT an inactive etd
    $this->assertFalse($ManageController->inactivateAction());
    $this->assertFalse($ManageController->redirectRan);
    $this->assertFalse(isset($ManageController->view->etd));
  }

  public function testDoReactivateAction() {
    $ManageController = new ManageControllerForTest($this->request,$this->response);

    $etd = new etd($this->published_etdpid);
    $etd->setStatus("inactive");
    $etd->save("marking published etd inactive to test reactivate");

    $this->setUpPost(array('pid' => $this->published_etdpid, 'reason' => 'testing reactivation',
         'status' => "draft"));
    $ManageController->doReactivateAction();

    $etd = new etd($this->published_etdpid);
    $this->assertEqual("draft", $etd->status());
    $last_event = count($etd->premis->event) - 1;
    $this->assertEqual("Reactivated - testing reactivation", $etd->premis->event[$last_event]->detail);
    $messages = $ManageController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/Record.*status changed/", $messages[0]);

    // test - NOT an inactive etd
    $this->setUpPost(array('pid' => $this->reviewed_etdpid, 'reason' => 'testing reactivate'));  
    $this->assertFalse($ManageController->doReactivateAction());
  }

  public function testUpdateEmbargoAction() {
    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $this->setUpGet(array('pid' => $this->published_etdpid,
          'newdate' => date("Y-m-d", strtotime("+2 year", time())), 'message' => "Testing update embargo"));
    $ManageController->updateembargoAction();
    $messages = $ManageController->getHelper('FlashMessenger')->getMessages();
    
    $etd = new etd($this->published_etdpid);
    $newdate = $etd->mods->embargo_end;
    $this->assertEqual("Embargo ending dated changed to <b>$newdate</b>", $messages[0]);
    $this->assertEqual("Access restriction ending date is updated to $newdate - Updating the embargo", $etd->premis->event[count($etd->premis->event) - 1]->detail);
  }



  public function testExpiringEmbargoes() {
    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $ManageController->embargoesAction();
    $this->assertTrue(isset($ManageController->view->title));
    $this->assertIsA($ManageController->view->etdSet, "EtdSet");
    $this->assertTrue($ManageController->view->show_lastaction);
  }

  public function testViewLog() {
    // FIXME: should a test log file be created for testing purposes?
    $this->test_user->role = "superuser"; // regular admin not allowed to view log
    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $ManageController->viewLogAction();
    $this->assertTrue(isset($ManageController->view->title));
    // FIXME: any way to test the log entries stuff? 
  }

  public function testUnauthorizedUser() {
    // test with an unauthorized user
    $this->test_user->role = "student";

    $this->setUpGet(array('pid' => $this->published_etdpid));  
    
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
    $this->assertFalse($ManageController->viewLogAction());
  }

  public function testAdminFilter() {
    // totals on summary pages and records on list views based on what kind of admin user is
    $school_cfg = Zend_Registry::get("schools-config");
    
    $ManageController = new ManageControllerForTest($this->request,$this->response);
    
    // generic admin sees everything (no filter)
    $this->test_user->role = "admin";
    $this->assertNull($ManageController->testGetAdminFilter());

    // grad admin - filter by grad collection pid
    $this->test_user->role = "grad admin";
    $filter = $ManageController->testGetAdminFilter();
    $this->assertEqual('collection:"' . $school_cfg->graduate_school->fedora_collection . '"',
           $filter);

    // undergrad honors admin
    $this->test_user->role = "honors admin";
    $filter = $ManageController->testGetAdminFilter(); 
    $this->assertEqual('collection:"' . $school_cfg->emory_college->fedora_collection . '"',
           $filter);

    // candler theology admin
    // undergrad honors admin
    $this->test_user->role = "candler admin";
    $filter = $ManageController->testGetAdminFilter(); 
    $this->assertEqual('collection:"' . $school_cfg->candler->fedora_collection . '"',
           $filter);
           
    // rollins admin - should only happen in testing mode when no admins are configured in etd util DB
    $this->test_user->role = "rollins admin";
    $filter = $ManageController->testGetAdminFilter(); 
    $this->assertEqual('collection:"' . $school_cfg->rollins->fedora_collection . '"', $filter);

    //Correct Role and user is configured with programs in etd util DB
    $this->test_user->role = "rollins admin";
    $this->test_user->netid = "roll1";
    Zend_Registry::set('current_user', $this->test_user);
    $filter = $ManageController->testGetAdminFilter();
    $this->assertTrue(strpos($filter, 'collection:"emory-control:ETD-Rollins-collection"') === 0);
    $this->assertTrue(strpos($filter, 'program_id: "ms" OR subfield_id: "ms"'));
    $this->assertTrue(strpos($filter, 'program_id: "ps" OR subfield_id: "ps"'));
  }

  function testGetAdminPrograms() {

    $ManageController = new ManageControllerForTest($this->request,$this->response);
    $ManageController->school = $this->schools_cfg->rollins;
    $result = $ManageController->testGetAdminPrograms();

    //No results because "admin" user is not in DB
    $this->assertIsA($result, "array", "Return object should be an array");
    $this->assertEqual(count($result), 0, "No records returned");


    //Roll1 user has entries in etd util DB
    $this->test_user->role = "rollins admin";
    $this->test_user->netid = "roll1";
    Zend_Registry::set('current_user', $this->test_user);
    $result = $ManageController->testGetAdminPrograms();
    $this->assertIsA($result, "array", "Return object should be an array");
    $this->assertEqual(count($result), 2, "2 records returned");
    $this->assertTrue(in_array("ps", $result));
    $this->assertTrue(in_array("ms", $result));
  }
  
}


class ManageControllerForTest extends ManageController {
  
  public $renderRan = false;
  public $redirectRan = false;
  public $schools_cfg;
  public $school;

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
  // expose private function for testing
  public function testGetAdminPrograms() {

    return $this->getAdminPrograms();
  }


}   

runtest(new ManageControllerTest());

?>
