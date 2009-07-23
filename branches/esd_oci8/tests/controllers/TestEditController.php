<?
require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/EditController.php');
      
class EditControllerTest extends ControllerTestCase {

  // array of test foxml files & their pids 
  private $etdxml;
  private $test_user;
  
  function setUp() {
    $ep = new esdPerson();
    $this->test_user = $ep->getTestPerson();
    $this->test_user->role = "student";
    $this->test_user->netid = "author";
    $this->test_user->firstname = "Author";
    $this->test_user->lastname = "Jones";
    Zend_Registry::set('current_user', $this->test_user);
	
    $_GET 	= array();
    $_POST	= array();
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();

    $this->etdxml = array(//"etd1" => "test:etd1",
			  "etd2" => "test:etd2",
			  "user" => "test:user1",
			  //"etd3" => "test:etd3",	// not working for some reason
			  );
    

    // load a test objects to repository
    // NOTE: for risearch queries to work, syncupdates must be turned on for test fedora instance
    foreach (array_keys($this->etdxml) as $etdfile) {
      $pid = fedora::ingest(file_get_contents('../fixtures/' . $etdfile . '.xml'), "loading test etd");
    }

    // set status to draft so it can be edited
    $etd = new etd("test:etd2");
    $etd->setStatus("draft");
    // add view policy rule and committee ids used in mods
    $etd->policy->addRule("view");
    $etd->policy->view->condition->addUser("nobody");
    $etd->policy->view->condition->addUser("nobodytoo");
    $etd->save("setting status to draft to test edit");

  }
  
  function tearDown() {
    foreach ($this->etdxml as $file => $pid)
      fedora::purge($pid, "removing test etd");

    Zend_Registry::set('current_user', null);
  }


  function testRecordAction() {
    $EditController = new EditControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => 'test:etd2'));	   

    $EditController->recordAction();
    $viewVars = $EditController->view->getVars();
    $this->assertIsA($EditController->view->etd, "etd");
    // required namespaces for this xform
    $this->assertTrue(isset($EditController->view->namespaces['mods']));
    $this->assertTrue(isset($EditController->view->namespaces['etd']));
    $this->assertPattern("|/view/mods/pid/test:etd2/mode/edit$|", $EditController->view->xforms_model_uri);

    // set status to non-draft to test access controls
    $etd = new etd("test:etd2");
    $etd->setStatus("reviewed");
    $etd->save("setting status to reviewed to test edit");

    $EditController = new EditControllerForTest($this->request,$this->response);
    // should not be allowed (wrong status etd)
    $this->assertFalse($EditController->recordAction());
    // no easy way to access response - check error code & message
    $response = $EditController->getResponse();
    $this->assertEqual(403, $response->getHttpResponseCode());
    $this->assertPattern("/not authorized to edit metadata/", $response->getBody());
  }

  // Note: not testing access on all of these because they are exactly the same
  
  function testProgramAction() {
    // ignore php errors - "indirect modification of overloaded property"
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);

    $EditController = new EditControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => 'test:etd2'));	   
    $EditController->programAction();
    $viewVars = $EditController->view->getVars();
    $this->assertIsA($EditController->view->etd, "etd");
    $this->assertTrue(isset($EditController->view->programs));
    $this->assertIsA($EditController->view->programs, 'programs');
    $this->assertIsA($EditController->view->programs, 'collectionHierarchy');
    $this->assertFalse($EditController->view->honors, 'not in honors mode');

    error_reporting($errlevel);	    // restore prior error reporting
  }

  function testSaveProgramsAction() {
    $EditController = new EditControllerForTest($this->request,$this->response);
    $this->setUpPost(array('pid' => 'test:etd2',
			   'program_id' => 'religion',
			   'subfield_id' => 'american'));
    $EditController->saveProgramAction();
    $viewVars = $EditController->view->getVars();
    $messages = $EditController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Saved changes to program", $messages[0]);
    $this->assertTrue($EditController->redirectRan);	// redirects back to record

    // check for updated values - text & id
    $etd = new etd("test:etd2");
    $this->assertEqual("religion", $etd->rels_ext->program);
    $this->assertEqual("american", $etd->rels_ext->subfield);
    $this->assertEqual("Religion", $etd->mods->department);
    $this->assertPattern("/American Religio/", $etd->mods->subfield);
  }
  


  function testFacultyAction() {
    $EditController = new EditControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => 'test:etd2'));	   
    $EditController->facultyAction();
    $viewVars = $EditController->view->getVars();
    $this->assertIsA($EditController->view->etd, "etd");
  }

  function testSaveFacultyAction() {
    $EditController = new EditControllerForTest($this->request,$this->response);

    $this->setUpPost(array('pid' => 'test:etd2', 'chair' => array('mhalber'), 'committee' => array('jfenton'),
			   'nonemory_firstname' => array('Marvin'), 'nonemory_lastname' => array('the Martian'),
			   'nonemory_affiliation' => array('Mars Polytechnic')));	   
    $EditController->savefacultyAction();
    $viewVars = $EditController->view->getVars();
    $messages = $EditController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Saved changes to committee chairs & members", $messages[0]);
    $this->assertTrue($EditController->redirectRan);	// redirects back to record
    
    $etd = new etd("test:etd2");
    $this->assertEqual("Halbert", $etd->mods->chair[0]->last);
    $this->assertEqual("Fenton", $etd->mods->committee[0]->last);
    $this->assertEqual(1, count($etd->mods->committee));
    $this->assertEqual("Mars Polytechnic", $etd->mods->nonemory_committee[0]->affiliation);

    // test setting affiliation for former faculty
    $this->setUpPost(array('pid' => 'test:etd2', 'chair' => array('mhalber'),
			   'committee' => array('jfenton'),
			   "mhalber_affiliation" => "grants",
			   "jfenton_affiliation" => "preservation"));
    $EditController->savefacultyAction();
    $etd = new etd("test:etd2");
    $this->assertEqual("grants", $etd->mods->chair[0]->affiliation);
    $this->assertEqual("preservation", $etd->mods->committee[0]->affiliation);
    

    
    // simulate bad input (nonexistent ids - shouldn't happen in real life)
    $this->setUpPost(array('pid' => 'test:etd2', 'chair' => array('nobody'), 'committee' => array('nobodytoo'),
			   'nonemory_firstname' => array(), 'nonemory_lastname' => array(),
			   'nonemory_affiliation' => array()));
    
    $this->expectError("Could not find person information for 'nobody' in Emory Shared Data", E_USER_WARNING);
    $this->expectError("Could not find person information for 'nobodytoo' in Emory Shared Data", E_USER_WARNING);
    $EditController->savefacultyAction();
    $viewVars = $EditController->view->getVars();
    $messages = $EditController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Saved changes to committee chairs & members", $messages[0]);
    $this->assertTrue($EditController->redirectRan);	// redirects back to record
    
    $etd = new etd("test:etd2");
    // if names are not found, values will not be changed
    $this->assertEqual("Halbert", $etd->mods->chair[0]->last);
    $this->assertEqual("Fenton", $etd->mods->committee[0]->last);
    // no non-emory info sent- should be empty
    $this->assertFalse(isset($etd->mods->nonemory_committee[0]));


  }

  function testRightsAction() {
    $EditController = new EditControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => 'test:etd2'));	   
    $EditController->rightsAction();
    $viewVars = $EditController->view->getVars();
    $this->assertIsA($EditController->view->etd, "etd");
    $this->assertTrue(isset($EditController->view->namespaces['mods']));
    $this->assertPattern("|/view/mods/pid/test:etd2$|", $EditController->view->xforms_model_uri);

    // if degree is blank, should redirect to main edit page
    $etd = new etd("test:etd2");
    $etd->mods->degree->name = "";
    $etd->save("blank degree to test rights edit page");
    $EditController->rightsAction();
    $this->assertTrue($EditController->redirectRan);	// redirects back to record
    $messages = $EditController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("You must select your degree before editing Rights and Access Restrictions", $messages[0]);
      
  }

  function testResearchfieldAction() {
    $EditController = new EditControllerForTest($this->request,$this->response);
    
    $this->setUpGet(array('pid' => 'test:etd2'));	   
    $EditController->researchfieldAction();
    $viewVars = $EditController->view->getVars();
    $this->assertIsA($EditController->view->etd, "etd");
    $this->assertTrue(isset($EditController->view->fields));
    $this->assertIsA($EditController->view->fields, 'researchfields');
    $this->assertIsA($EditController->view->fields, 'collectionHierarchy');
  }

  function testSaveResearchfieldAction() {
    $EditController = new EditControllerForTest($this->request,$this->response);

    $this->setUpPost(array('pid' => 'test:etd2',
			   'fields' => array('1234 Martian Studies', '9876 Extraterrestrial Science')));
    $EditController->saveResearchfieldAction();
    $messages = $EditController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Saved changes to research fields", $messages[0]);
    $this->assertTrue($EditController->redirectRan);	// redirects back to record
    
    // check that the values were saved correctly
    /*    $etd = new etd("test:etd2");
    $this->assertEqual("2", count($etd->mods->researchfields));
    $this->assertEqual("1234", $etd->mods->researchfields[0]->id);
    $this->assertEqual("Martin Studies", $etd->mods->researchfields[0]->topic);
    $this->assertEqual("9876", $etd->mods->researchfields[1]->id);
    $this->assertEqual("Extraterrestrial Science", $etd->mods->researchfields[1]->topic);*/
  }

  // not testing editHtml actions because there is nothing of substance to test


  function testFileOrderAction() {
    $EditController = new EditControllerForTest($this->request,$this->response);
    $this->setUpPost(array('pid' => 'test:etd2'));
    $EditController->fileorderAction();
    $viewVars = $EditController->view->getVars();
    $this->assertTrue(isset($EditController->view->title));
    $this->assertIsA($EditController->view->etd, "etd");
    
  }

  // FIXME: can't test saving new file order because test object does not have multiple (any?) etd files



}


class EditControllerForTest extends EditController {
  
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

runtest(new EditControllerTest());
?>