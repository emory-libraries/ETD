<?

require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/SubmissionController.php');
      
class SubmissionControllerTest extends ControllerTestCase {

  // array of test foxml files & their pids 
  private $etdxml;
  private $test_user;
  
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

  }
  
  function tearDown() {
    foreach ($this->etdxml as $file => $pid)
      fedora::purge($pid, "removing test etd");
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
    
    $SubmissionController = new SubmissionControllerForTest($this->request,$this->response);
    $SubmissionController->reviewAction();
    $this->assertFalse($SubmissionController->redirectRan);
    $this->assertTrue(isset($SubmissionController->view->etd), "etd variable set for review");
    $messages = $SubmissionController->getHelper('FlashMessenger')->getMessages();
  }

  function testSubmitAction() {
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

    // notices for non-existent users in metadata
    $this->expectError("Committee member/chair (nobody) not found in ESD");
    $this->expectError("Committee member/chair (nobodytoo) not found in ESD");
    $SubmissionController->submitAction();
    $etd = new etd("test:etd2");
    $this->assertEqual("submitted", $etd->status());
    $this->assertTrue($SubmissionController->redirectRan);	// currently redirects to my etds page...
    $messages = $SubmissionController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/status changed/", $messages[0]);
    $this->assertPattern("/Submission notification email sent to mmouse@emory.edu, mmouse@disney.com/",
	 $messages[1]);	        // 	   current & permanent email addresses in user information
    $this->assertEqual("Submitted for Approval by " . $this->test_user->fullname, $etd->premis->event[1]->detail);
    $this->assertEqual("author", $etd->premis->event[1]->agent->value);

    // NOTE: currently no way to test generation of the email, but it may be added in the future
  }


}


class SubmissionControllerForTest extends SubmissionController {
  
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

runtest(new SubmissionControllerTest());
?>