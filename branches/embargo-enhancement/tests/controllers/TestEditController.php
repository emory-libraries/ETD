<?
require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/EditController.php');
require_once("fixtures/esd_data.php");
      
class EditControllerTest extends ControllerTestCase {

  // array of test foxml files & their pids 
  private $etdxml;
  private $test_user;
  private $data;

  // fedoraConnection
  private $fedora;

  private $etdpid;
  private $userpid;

  function __construct() {
    $this->fedora = Zend_Registry::get("fedora");
    $fedora_cfg = Zend_Registry::get('fedora-config');

    // get 2 test pids - to be used throughout test
    list($this->etdpid, $this->userpid) = $this->fedora->getNextPid($fedora_cfg->pidspace, 2);
  }
  
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

    // load fixtures
    // note: etd & related user need to be in repository so authorInfo relation will work
    $dom = new DOMDocument();
    // load etd & set pid & author relation
    $dom->loadXML(file_get_contents('../fixtures/etd2.xml'));
    $foxml = new etd($dom);
    $foxml->pid = $this->etdpid;
    $foxml->rels_ext->hasAuthorInfo = $this->userpid;
    $this->fedora->ingest($foxml->saveXML(), "loading test etd object");

    // load author info
    $dom->loadXML(file_get_contents('../fixtures/user.xml'));
    $foxml = new foxml($dom);
    $foxml->pid = $this->userpid;
    $this->fedora->ingest($foxml->saveXML(), "loading test etd authorInfo object");
    
    // set status to draft so it can be edited
    $etd = new etd($this->etdpid);
    $etd->setStatus("draft");
    // add view policy rule and committee ids used in mods
    $etd->policy->addRule("view");
    $etd->policy->view->condition->addUser("nobody");
    $etd->policy->view->condition->addUser("nobodytoo");
    $etd->save("setting status to draft to test edit");

    $this->data = new esd_test_data();
    $this->data->loadAll();
  }
  
  function tearDown() {
    foreach (array($this->etdpid, $this->userpid) as $pid)
      $this->fedora->purge($pid, "removing test etd");

    // is there a better way to discard messages?
    $EditController = new EditControllerForTest($this->request, $this->response);
    $EditController->getHelper('FlashMessenger')->getMessages();

    Zend_Registry::set('current_user', null);
    $this->data->cleanUp();
  }


  function testRecordAction() {
    $EditController = new EditControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => $this->etdpid));	   

    $EditController->recordAction();
    $viewVars = $EditController->view->getVars();
    $this->assertIsA($EditController->view->etd, "etd");
    // required namespaces for this xform
    $this->assertTrue(isset($EditController->view->namespaces['mods']));
    $this->assertTrue(isset($EditController->view->namespaces['etd']));
    $this->assertPattern("|/view/mods/pid/" . $this->etdpid . "/mode/edit$|", $EditController->view->xforms_model_uri);

    // set status to non-draft to test access controls
    $etd = new etd($this->etdpid);
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

    $this->setUpGet(array('pid' => $this->etdpid));	   
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
    $this->setUpPost(array('pid' => $this->etdpid,
			   'program_id' => 'religion',
			   'subfield_id' => 'american'));
    $EditController->saveProgramAction();
    $viewVars = $EditController->view->getVars();
    $messages = $EditController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Saved changes to program", $messages[0]);
    $this->assertTrue($EditController->redirectRan);	// redirects back to record

    // check for updated values - text & id
    $etd = new etd($this->etdpid);
    $this->assertEqual("religion", $etd->rels_ext->program);
    $this->assertEqual("american", $etd->rels_ext->subfield);
    $this->assertEqual("Religion", $etd->mods->department);
    $this->assertPattern("/American Religio/", $etd->mods->subfield);
  }
  
  /** 
   * rights
   */

  function testSaveRightsAction() {
    // No embargo, everything else left blank. This is the default state for
    // this fixture.
    $this->postSaveRights(null, 0, null, null, null, 1);
    $this->assertRights(0, etd_mods::EMBARGO_NONE, null, null, null);
  }

  private function postSaveRights($controller, $embargo, $embargo_level,
                                  $pq_submit, $pq_copyright, 
                                  $submission_agreement) {
    if ($controller === null)
      $controller = new EditControllerForTest($this->request, $this->response);

    $args = array('pid' => $this->etdpid);
    if ($embargo !== null) $args['embargo'] = $embargo;
    if ($embargo_level !== null) $args['embargo_level'] = $embargo_level;
    if ($pq_submit !== null) $args['pq_submit'] = $pq_submit;
    if ($pq_copyright !== null) $args['pq_copyright'] = $pq_copyright;
    if ($submission_agreement !== null) $args['submission_agreement'] = $submission_agreement;

    $this->setUpPost($args);
    $controller->saveRightsAction();
    $this->assertTrue($controller->redirectRan);
  }

  private function assertRights($embargo, $embargo_level,
                                $pq_submit, $pq_copyright,
                                $submission_agreement) {
    $etd = new etd($this->etdpid);
    if ($embargo !== null)
      $this->assertEqual($embargo, $etd->mods->isEmbargoRequested());
    if ($embargo_level !== null)
      $this->assertEqual($embargo_level, $etd->mods->embargoRequestLevel());
    if ($pq_submit !== null)
      /* FIXME: ?? */;
    if ($pq_copyright !== null)
      /* FIXME: ?? */;
    if ($submission_agreement !== null)
      /* FIXME: ?? */;
  }

  /**
   * end rights
   */

  function testFacultyAction() {
    $EditController = new EditControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => $this->etdpid));	   
    $EditController->facultyAction();
    $viewVars = $EditController->view->getVars();
    $this->assertIsA($EditController->view->etd, "etd");
  }

  function testSaveFacultyAction() {
    $EditController = new EditControllerForTest($this->request,$this->response);

    $this->setUpPost(array('pid' => $this->etdpid, 'chair' => array('mthink'), 'committee' => array('engrbs'),
			   'nonemory_firstname' => array('Marvin'), 'nonemory_lastname' => array('the Martian'),
			   'nonemory_affiliation' => array('Mars Polytechnic')));	   
    $EditController->savefacultyAction();
    $viewVars = $EditController->view->getVars();
    $messages = $EditController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Saved changes to committee chairs & members", $messages[0]);
    $this->assertTrue($EditController->redirectRan);	// redirects back to record
    
    $etd = new etd($this->etdpid);
    $this->assertEqual("Thinker", $etd->mods->chair[0]->last);
    $this->assertEqual("Scholar", $etd->mods->committee[0]->last);
    $this->assertEqual(1, count($etd->mods->committee));
    $this->assertEqual("Mars Polytechnic", $etd->mods->nonemory_committee[0]->affiliation);

    // test setting affiliation for former faculty
    $this->setUpPost(array('pid' => $this->etdpid, 'chair' => array('mthink'),
			   'committee' => array('engrbs'),
			   "mthink_affiliation" => "grants",
			   "engrbs_affiliation" => "preservation"));
    $EditController->savefacultyAction();
    $etd = new etd($this->etdpid);
    $this->assertEqual("grants", $etd->mods->chair[0]->affiliation);
    $this->assertEqual("preservation", $etd->mods->committee[0]->affiliation);
    
    // simulate bad input (nonexistent ids - shouldn't happen in real life)
    $this->setUpPost(array('pid' => $this->etdpid, 'chair' => array('nobody'), 'committee' => array('nobodytoo'),
			   'nonemory_firstname' => array(), 'nonemory_lastname' => array(),
			   'nonemory_affiliation' => array()));
    
    $this->expectError("Could not find person information for 'nobody' in Emory Shared Data", E_USER_WARNING);
    $this->expectError("Could not find person information for 'nobodytoo' in Emory Shared Data", E_USER_WARNING);
    $EditController->savefacultyAction();
    $viewVars = $EditController->view->getVars();
    $messages = $EditController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Saved changes to committee chairs & members", $messages[0]);
    $this->assertTrue($EditController->redirectRan);	// redirects back to record
    
    $etd = new etd($this->etdpid);
    // if names are not found, values will not be changed
    $this->assertEqual("Thinker", $etd->mods->chair[0]->last);
    $this->assertEqual("Scholar", $etd->mods->committee[0]->last);
    // no non-emory info sent- should be empty
    $this->assertFalse(isset($etd->mods->nonemory_committee[0]));

  }

  function testRightsAction() {
    $EditController = new EditControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => $this->etdpid));	   
    $EditController->rightsAction();
    $this->assertIsA($EditController->view->etd, "etd");
    $this->assertTrue(isset($EditController->view->title), "page title is set in view");
    $this->assertTrue(isset($EditController->view->dojo_config), "dojo config is set in view");

    // if degree is blank, should redirect to main edit page
    $etd = new etd($this->etdpid);
    $etd->mods->degree->name = "";
    $etd->save("blank degree to test rights edit page");
    $EditController->rightsAction();
    $this->assertTrue($EditController->redirectRan);	// redirects back to record
    $messages = $EditController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("You must select your degree before editing Rights and Access Restrictions", $messages[0]);
      
  }

  function testResearchfieldAction() {
    $EditController = new EditControllerForTest($this->request,$this->response);
    
    $this->setUpGet(array('pid' => $this->etdpid));	   
    $EditController->researchfieldAction();
    $viewVars = $EditController->view->getVars();
    $this->assertIsA($EditController->view->etd, "etd");
    $this->assertTrue(isset($EditController->view->fields));
    $this->assertIsA($EditController->view->fields, 'researchfields');
    $this->assertIsA($EditController->view->fields, 'collectionHierarchy');
  }

  function testSaveResearchfieldAction() {
    // ignore php errors - "indirect modification of overloaded property"
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);

    $EditController = new EditControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => $this->etdpid,
			   'fields' => array(0 => '#1234 Martian Studies',
					     1 => '#9876 Extraterrestrial Science')));
    $EditController->saveResearchfieldAction();
    $messages = $EditController->getHelper('FlashMessenger')->getMessages();
    $this->assertEqual("Saved changes to research fields", $messages[0]);
    $this->assertTrue($EditController->redirectRan);	// redirects back to record
    
    // check that the values were saved correctly
    $etd = new etd($this->etdpid);
    $this->assertEqual("2", count($etd->mods->researchfields));
    $this->assertEqual("1234", $etd->mods->researchfields[0]->id);
    $this->assertEqual("Martian Studies", $etd->mods->researchfields[0]->topic);
    $this->assertEqual("9876", $etd->mods->researchfields[1]->id);
    $this->assertEqual("Extraterrestrial Science", $etd->mods->researchfields[1]->topic);

    error_reporting($errlevel);	    // restore prior error reporting
  }

  // not testing editHtml actions because there is nothing of substance to test


  function testFileOrderAction() {
    $EditController = new EditControllerForTest($this->request,$this->response);
    $this->setUpPost(array('pid' => $this->etdpid));
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
