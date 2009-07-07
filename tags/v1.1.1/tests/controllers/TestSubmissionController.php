<?

require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/SubmissionController.php');
      
class SubmissionControllerTest extends ControllerTestCase {

  // array of test foxml files & their pids 
  private $etdxml;
  private $test_user;

  private $mock_etd;
  
  function setUp() {
    $this->test_user = new esdPerson();
    $this->test_user->role = "student";
    $this->test_user->netid = "author";
    $this->test_user->name = "Author";
    $this->test_user->lastname = "Jones";
    
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

    // use mock etd object for some tests
    $this->mock_etd = &new MockEtd();
    $this->mock_etd->status = "draft";
    $this->mock_etd->user_role = "author";
    $this->mock_etd->setReturnValue("save", "datestamp");	// mimic successful save
  }
  
  function tearDown() {
    foreach ($this->etdxml as $file => $pid)
      fedora::purge($pid, "removing test etd");
    
    Zend_Registry::set('current_user', null);

    // in case any mock-object has been used, clear it
    $SubmissionController = new SubmissionControllerForTest($this->request,$this->response);
    $gff = $SubmissionController->getHelper("GetFromFedora");
    $gff->clearReturnObject();
  }


  function testStartAction() {
    $this->test_user->role = "staff";	// set to non-student
    Zend_Registry::set('current_user', $this->test_user);
    $SubmissionController = new SubmissionControllerForTest($this->request,$this->response);
    $this->assertFalse($SubmissionController->startAction());
    // should not be allowed (not a student)

    // student who already has a submission 
    $this->test_user->role = "student with submission";
    Zend_Registry::set('current_user', $this->test_user);
    // should not be allowed to create a new submission
    $this->assertFalse($SubmissionController->startAction());

    // student
    $SubmissionController = new SubmissionControllerForTest($this->request,$this->response);
    $this->test_user->role = "student";
    Zend_Registry::set('current_user', $this->test_user);
    $SubmissionController->startAction();
    $this->assertFalse($SubmissionController->redirectRan);
  }

  /* FIXME: not sure how to test processPdf action - LOTS of stuff going on, hard to simulate... */


  function testReviewAction() {
    $this->test_user->role = "student";	// set to non-student
    Zend_Registry::set('current_user', $this->test_user);
    $SubmissionController = new SubmissionControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => 'test:etd2'));	   // reviewed etd
    // etd is wrong status, should not be allowed
    $this->assertFalse($SubmissionController->reviewAction());
    $this->assertFalse(isset($SubmissionController->view->etd));

    // set status to draft so it can be reviewed
    $etd = new etd("test:etd2");
    $etd->setStatus("draft");
    $etd->save("setting status to draft to test review");
    
    // etd is not ready to submit; should complain and redirect
    $SubmissionController = new SubmissionControllerForTest($this->request,$this->response);
    $SubmissionController->reviewAction();
    $this->assertTrue($SubmissionController->redirectRan);
    $messages = $SubmissionController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/record is not ready to submit/", $messages[0]);

    // use a mock etd to simulate ready for submission without having all the separate objects
    $SubmissionController = new SubmissionControllerForTest($this->request,$this->response);
    $this->mock_etd->setReturnValue("readyToSubmit", true);
    $gff = $SubmissionController->getHelper("GetFromFedora");
    $gff->clearReturnObject();
    $gff->setReturnObject($this->mock_etd);
    
    $SubmissionController->reviewAction();
    $this->assertFalse($SubmissionController->redirectRan);
    $this->assertTrue(isset($SubmissionController->view->etd), "etd variable set for review");
  }

  function testSubmitAction() {
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);
	
    $this->test_user->role = "student";	// set to non-student
    Zend_Registry::set('current_user', $this->test_user);
    $SubmissionController = new SubmissionControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => 'test:etd2'));	   // reviewed etd
    // etd is wrong status, should not be allowed
    $this->assertFalse($SubmissionController->submitAction());
    $etd = new etd("test:etd2");
    $this->assertEqual("reviewed", $etd->status(), "status unchanged");	

    // set status to draft so it can be submitted
    $etd = new etd("test:etd2");
    $etd->setStatus("draft");
    $etd->save("changing status to test submit");

    // etd is not ready to submit, should be redirected
    $SubmissionController->reviewAction();
    $this->assertTrue($SubmissionController->redirectRan);
    $messages = $SubmissionController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/record is not ready to submit/", $messages[0]);


    // use a mock etd to simulate ready for submission without having all the separate objects
    $this->mock_etd->setReturnValue("readyToSubmit", true);
    $this->mock_etd->mods->chair[0]->id = "nobody";
    $this->mock_etd->mods->committee[0]->id = "nobodytoo";
    $SubmissionController = new SubmissionControllerForTest($this->request,$this->response);
    $gff = $SubmissionController->getHelper("GetFromFedora");
    $gff->clearReturnObject();
    $gff->setReturnObject($this->mock_etd);

    // notices for non-existent users in metadata
    $this->expectError("Committee member/chair (nobody) not found in ESD");
    $this->expectError("Committee member/chair (nobodytoo) not found in ESD");
    $SubmissionController->submitAction();
    //    $etd = new etd("test:etd2");
    //    $this->assertEqual("submitted", $etd->status());
    $this->assertTrue($SubmissionController->redirectRan);	// currently redirects to my etds page...
    $messages = $SubmissionController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/status changed/", $messages[0]);
    $this->assertPattern("/Submission notification email sent to/",
	 $messages[1]);	        // 	   current & permanent email addresses in user information
    $this->assertEqual("Submitted for Approval by " . $this->test_user->fullname, $this->mock_etd->premis->event[1]->detail);
    $this->assertEqual("author", $this->mock_etd->premis->event[1]->agent->value);

    // NOTE: currently no way to test generation of the email, but it may be added in the future

    error_reporting($errlevel);	    // restore prior error reporting
  }


}


class SubmissionControllerForTest extends SubmissionController {
  
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

runtest(new SubmissionControllerTest());
?>