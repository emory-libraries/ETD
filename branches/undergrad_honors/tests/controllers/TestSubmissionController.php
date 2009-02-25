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

  function testProcessPdfAction() {
    $SubmissionController = new SubmissionControllerForTest($this->request,
							    $this->response);
    // unauthorized user
    $this->test_user->role = "guest";
    Zend_Registry::set('current_user', $this->test_user);
    $this->assertFalse($SubmissionController->processpdfAction());

    $this->test_user->role = "student";
    // sample file data
    $testfile = "../fixtures/tinker_sample.pdf";
    $_FILES['pdf'] = array("tmp_name" => $testfile,
			   "size" => filesize($testfile),
			    "type" => "application/pdf", "error" => UPLOAD_ERR_OK,
			    "name" => "diss.pdf");
    // don't need to test what it is handled by processPDF helper (tested elsewhere)
    // using test processpdf helper (~mock)
    $ppdf = $SubmissionController->getHelper("ProcessPDF");
    $ppdf->setReturnResult(array("title" => "test",
				 "distribution_agreement" => true,
				 "abstract" => "abs", "toc" => "1.2.3.",
				 "committee" => array(),
				 "pdf" => $testfile, "filename" => "diss.pdf",
				 "keywords" => array()));
    // mimic error when attempting to save etd
    $ioe = $SubmissionController->getHelper("IngestOrError");
    $ioe->setError("FedoraObjectNotValid");
    $SubmissionController->processpdfAction();
    $this->assertFalse($SubmissionController->redirectRan);
    $messages = $SubmissionController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/Error saving record/", $messages[0]);
    $this->assertPattern("/Could not create record/",
		       $SubmissionController->view->errors[0]);

    // mimic no error on ingest
    $ioe->clearError();
    $gff = $SubmissionController->getHelper("GetFromFedora");
    $this->mock_etd->setReturnValue("save", "datestamp");	// mimic successful save
    $this->assertIsA($this->mock_etd->premis, "premis", "mock_etd premis initialized right");
    $gff->setReturnObject($this->mock_etd);
    $SubmissionController->processpdfAction();
    $this->assertTrue($SubmissionController->redirectRan);
    $messages = $SubmissionController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual(0, count($messages));
    $this->assertEqual(0, count($SubmissionController->view->errors));
  }

  function testInitializeEtd() {
    // ignore php errors - "indirect modification of overloaded property
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);

    $SubmissionController = new SubmissionControllerForTest($this->request,
							    $this->response);
    $test_info = array("title" => "new etd",
		       "abstract" => "my abstract",
		       "toc" => "chapter 1 -- chapter 2",
		       "committee" => array(),
		       "keywords" => array("test", "etd"));
    // leaving out committee/ESD stuff for now

    $etd = $SubmissionController->initialize_etd($test_info);
    $this->assertIsA($etd, "etd");
    $this->assertNotA($etd, "honors_etd");
    $this->assertEqual("new etd", $etd->label);
    $this->assertEqual("my abstract", $etd->mods->abstract);
    $this->assertEqual("chapter 1 -- chapter 2", $etd->mods->tableOfContents);
    $this->assertEqual("test", $etd->mods->keywords[0]->topic);
    $this->assertEqual("etd", $etd->mods->keywords[1]->topic);


    // if student is honors, etd should be initialized the same but as
    // an instance of honors_etd variant
    $this->test_user->role = "honors_student";
    $etd = $SubmissionController->initialize_etd($test_info);
    $this->assertIsA($etd, "etd");
    $this->assertIsA($etd, "honors_etd");
    $this->assertEqual("new etd", $etd->label);
	
    error_reporting($errlevel);	    // restore prior error reporting
  }
    


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
    // enable test version of ingestOrError helper
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