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
  private $honors_etdpid;
  private $masters_etdpid;
  private $userpid;

  function __construct() {
    $this->fedora = Zend_Registry::get("fedora");
    $fedora_cfg = Zend_Registry::get('fedora-config');

    // get 4 test pids - to be used throughout test
    list($this->etdpid, $this->honors_etdpid, $this->masters_etdpid, $this->userpid) = $this->fedora->getNextPid($fedora_cfg->pidspace, 4);
  }
  
  function setUp() {
    $config = Zend_Registry::get('config');

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

    $school_cfg = Zend_Registry::get("schools-config");
    
    // load fixtures
    // note: etd & related user need to be in repository so authorInfo relation will work
    $etd = new etd($this->loadEtdDom());
    $etd->setSchoolConfig($school_cfg->graduate_school);
    $etd->pid = $this->etdpid;
    $this->prepareForEtdTest($etd);
    $this->fedora->ingest($etd->saveXML(), "test etd object");

    $honors_etd = new etd($this->loadEtdDom());
    $honors_etd->setSchoolConfig($school_cfg->emory_college);
    $honors_etd->pid = $this->honors_etdpid;
    // configure so membership so etd will be recognized as honors when loading from fedora
    $honors_etd->rels_ext->removeRelation("rel:isMemberOfCollection",
					  $school_cfg->graduate_school->fedora_collection);
    $honors_etd->rels_ext->addRelationToResource("rel:isMemberOfCollection",
                                                 $school_cfg->emory_college->fedora_collection);
    $this->prepareForEtdTest($honors_etd);
    $this->fedora->ingest($honors_etd->saveXML(), "test honors etd object");

    $masters_etd = new etd($this->loadEtdDom());
    $masters_etd->pid = $this->masters_etdpid;
    $masters_etd->mods->degree->name = "MA";
    $this->prepareForEtdTest($masters_etd);
    $this->fedora->ingest($masters_etd->saveXML(), "test masters etd object");

    // load author info
    $foxml = new foxml($this->loadAuthorInfoDom());
    $foxml->pid = $this->userpid;
    $this->fedora->ingest($foxml->saveXML(), "loading test etd authorInfo object");

    $this->data = new esd_test_data();
    $this->data->loadAll();
  }

  // load foxml for an etd. all of our etd fixtures are currently derived
  // from the same foxml
  private function loadEtdDom() {
    $dom = new DOMDocument();
    $dom->loadXML(file_get_contents('../fixtures/etd2.xml'));
    return $dom;
  }

  // load foxml for a user. only one user right now, but keep this function
  // to maintain analog with loadEtdDom().
  private function loadAuthorInfoDom() {
    $dom = new DOMDocument();
    $dom->loadXML(file_get_contents('../fixtures/authorInfo.xml'));
    return $dom;
  }

  private function prepareForEtdTest($etd) {
    // note: etd & related user need to be in repository so authorInfo relation will work
    $etd->rels_ext->hasAuthorInfo = $this->userpid;

    // set status to draft so it can be edited
    $etd->setStatus("draft");
    // add view policy rule and committee ids used in mods
    $etd->policy->addRule("view");
    $etd->policy->view->condition->addUser("nobody");
    $etd->policy->view->condition->addUser("nobodytoo");
  }
  
  function tearDown() {
    foreach (array($this->etdpid, $this->honors_etdpid, $this->masters_etdpid, $this->userpid) as $pid)
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
    $EditController = new EditControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => $this->etdpid));	   
    $EditController->programAction();
    $this->assertIsA($EditController->view->etd, "etd");
    $this->assertTrue(isset($EditController->view->programs));
    $this->assertIsA($EditController->view->programs, 'programs');
    $this->assertIsA($EditController->view->programs, 'collectionHierarchy');
    $this->assertTrue(isset($EditController->view->program_section), "progam section is set");
    $this->assertEqual("#grad", $EditController->view->program_section->id);

    $this->setUpGet(array('pid' => $this->honors_etdpid));	   
    $EditController->programAction();
    $this->assertEqual("#undergrad", $EditController->view->program_section->id);
  }

  function testSaveProgramsAction() {
    $EditController = new EditControllerForTest($this->request,$this->response);
    $this->setUpPost(array('pid' => $this->etdpid,
			   'program_id' => '#religion',
			   'subfield_id' => '#american'));
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

    // EMBARGO

    // No embargo. This is the default state for this fixture.
    $this->postSaveRights($this->etdpid, 0, null, 1, 1, 1);
    $this->assertRights(new etd($this->etdpid),
                        0, etd_mods::EMBARGO_NONE, 1, 1, 1);

    // Embargo files.
    $this->postSaveRights($this->etdpid, 1, etd_mods::EMBARGO_FILES, 1, 1, 1);
    $this->assertRights(new etd($this->etdpid),
                        1, etd_mods::EMBARGO_FILES, 1, 1, 1);

    // Embargo ToC.
    $this->postSaveRights($this->etdpid, 1, etd_mods::EMBARGO_TOC, 1, 1, 1);
    $this->assertRights(new etd($this->etdpid),
                        1, etd_mods::EMBARGO_TOC, 1, 1, 1);

    // Embargo abstract.
    $this->postSaveRights($this->etdpid, 1, etd_mods::EMBARGO_ABSTRACT, 1, 1, 1);
    $this->assertRights(new etd($this->etdpid),
                        1, etd_mods::EMBARGO_ABSTRACT, 1, 1, 1);

    // And set it back to none for good measure.
    $this->postSaveRights($this->etdpid, 0, null, 1, 1, 1);
    $this->assertRights(new etd($this->etdpid),
                        0, etd_mods::EMBARGO_NONE, 1, 1, 1);

    // If they say no $embargo but somehow send $embargo_level, we
    // interpret as no embargo.
    $this->postSaveRights($this->etdpid, 0, etd_mods::EMBARGO_ABSTRACT, 1, 1, 1);
    $this->assertRights(new etd($this->etdpid),
                        0, etd_mods::EMBARGO_NONE, 1, 1, 1);

    // PROQUEST

    // PhD ETD always goes to PQ (no option given on form) but copyright
    // is optional. Here, no copyright.
    $this->postSaveRights($this->etdpid, 0, null, null, 0, 1);
    $this->assertRights(new etd($this->etdpid),
                        0, etd_mods::EMBARGO_NONE, 1, 0, 1);

    // PhD ETD with copyright
    $this->postSaveRights($this->etdpid, 0, null, null, 1, 1);
    $this->assertRights(new etd($this->etdpid),
                        0, etd_mods::EMBARGO_NONE, 1, 1, 1);

    // Masters ETD, no PQ. (the default)
    $this->postSaveRights($this->masters_etdpid, 0, null, 0, null, 1);
    $this->assertRights(new etd($this->masters_etdpid),
                        0, etd_mods::EMBARGO_NONE, 0, null, 1);

    // Masters ETD, PQ, no copyright
    $this->postSaveRights($this->masters_etdpid, 0, null, 1, 0, 1);
    $this->assertRights(new etd($this->masters_etdpid),
                        0, etd_mods::EMBARGO_NONE, 1, 0, 1);
    
    // Masters ETD, PQ, copyright
    $this->postSaveRights($this->masters_etdpid, 0, null, 1, 1, 1);
    $this->assertRights(new etd($this->masters_etdpid),
                        0, etd_mods::EMBARGO_NONE, 1, 1, 1);
    
    // Honors ETD never goes to PQ: it doesn't even have the option
    $this->postSaveRights($this->honors_etdpid, 0, null, null, null, 1);
    $this->assertRights(new etd($this->honors_etdpid),
                        0, etd_mods::EMBARGO_NONE, 0, 0, 1);

  }

  function testInvalidSaveRightsAction() {
    // The default state. We'll be checking this again.
    $this->assertRights(new etd($this->etdpid),
                        0, etd_mods::EMBARGO_NONE, 0, null, null);

    // EMBARGO

    // Skipping $embargo is invalid. Shouldn't change anything, even if
    // other fields are set.
    list($view, $redirect) =  $this->postSaveRights($this->etdpid, null, etd_mods::EMBARGO_ABSTRACT, 1, 1, 1);
    $this->assertRights(new etd($this->etdpid),
                        0, etd_mods::EMBARGO_NONE, 0, null, null);
    $this->assertFalse($redirect, "saveRights should not redirect when invalid");
    $this->assertPattern("/Error: invalid input/", $view->messages[0],
			 "invalid input should display error message to user ");

    // If they request an $embargo, $embargo_level is required. Reject if
    // they skip it.
    list($view, $redirect) =  $this->postSaveRights($this->etdpid, 1, null, 0, 0, 1);
    $this->assertRights(new etd($this->etdpid),
                        0, etd_mods::EMBARGO_NONE, 0, null, null);
    $this->assertFalse($redirect, "saveRights should not redirect when invalid");
    $this->assertPattern("/Error:.*please specify the type of restriction/", $view->messages[0],
			 "missing embargo level should display error message to user ");

    // Invalid $embargo_level is invalid.
    list($view, $redirect) =  $this->postSaveRights($this->etdpid, 1, 42, 1, 1, 1);
    $this->assertRights(new etd($this->etdpid),
                        0, etd_mods::EMBARGO_NONE, 0, null, null);
    $this->assertFalse($redirect, "saveRights should not redirect when invalid");
    $this->assertPattern("/Error: invalid input/", $view->messages[0],
			 "invalid input should display error message to user ");
	

    // PROQUEST

    // PhD ETDs must go to PQ; the form does not offer this option
    $this->postSaveRights($this->etdpid, 1, etd_mods::EMBARGO_ABSTRACT, 0, 0, 1);
    $this->assertRights(new etd($this->etdpid),
                        0, etd_mods::EMBARGO_NONE, 0, null, null);

    // PhD ETDs must specify copyright option
    $this->postSaveRights($this->etdpid, 1, etd_mods::EMBARGO_ABSTRACT, null, null, 1);
    $this->assertRights(new etd($this->etdpid),
                        0, etd_mods::EMBARGO_NONE, 0, null, null);

    // Masters ETD must specify PQ choice
    $this->postSaveRights($this->masters_etdpid, 1, etd_mods::EMBARGO_ABSTRACT, null, null, 1);
    $this->assertRights(new etd($this->masters_etdpid),
                        0, etd_mods::EMBARGO_NONE, 0, null, null);

    // Masters ETD that elects to send to PQ must specify copyright option
    $this->postSaveRights($this->masters_etdpid, 1, etd_mods::EMBARGO_ABSTRACT, 1, null, 1);
    $this->assertRights(new etd($this->masters_etdpid),
                        0, etd_mods::EMBARGO_NONE, 0, null, null);

    // Honors ETDs are not given the option to request PQ

    $this->postSaveRights($this->honors_etdpid, 1, etd_mods::EMBARGO_ABSTRACT, 1, 1, 1);
    $this->assertRights(new etd($this->honors_etdpid),
                        0, etd_mods::EMBARGO_NONE, 0, null, null);

    // SUBMISSION AGREEMENT

    // If they don't accept the agreement, process everything else as usual
    $this->postSaveRights($this->etdpid, 1, etd_mods::EMBARGO_ABSTRACT, 1, 1, 0);
    $this->assertRights(new etd($this->etdpid),
                        1, etd_mods::EMBARGO_ABSTRACT, 1, 1, 0);
  }

  private function postSaveRights($pid, $embargo, $embargo_level,
                                  $pq_submit, $pq_copyright, 
                                  $submission_agreement) {
    $controller = new EditControllerForTest($this->request, $this->response);

    $args = array('pid' => $pid);
    if ($embargo !== null) $args['embargo'] = $embargo;
    if ($embargo_level !== null) $args['embargo_level'] = $embargo_level;
    if ($pq_submit !== null) $args['pq_submit'] = $pq_submit;
    if ($pq_copyright !== null) $args['pq_copyright'] = $pq_copyright;
    if ($submission_agreement !== null) $args['submission_agreement'] = $submission_agreement;

    $this->setUpPost($args);
    $controller->saveRightsAction();
    // return array of info for additional checking - view, did redirect run
    return array($controller->view, $controller->redirectRan);
  }

  private function assertRights($etd, $embargo, $embargo_level,
                                $pq_submit, $pq_copyright,
                                $submission_agreement) {
    if ($embargo !== null)
      $this->assertEqual($embargo, $etd->mods->isEmbargoRequested());
    if ($embargo_level !== null)
      $this->assertEqual($embargo_level, $etd->mods->embargoRequestLevel());
    if ($pq_submit !== null) {
      if ($pq_submit)
        $this->assertTrue(isset($etd->mods->pq_submit) && ($etd->mods->pq_submit == 'yes'));
      else
        $this->assertTrue( ! isset($etd->mods->pq_submit) || ($etd->mods->pq_submit == '') || ($etd->mods->pq_submit == 'no'));
    }
    if ($pq_copyright !== null) {
      if ($pq_copyright)
        $this->assertTrue(isset($etd->mods->copyright) && ($etd->mods->copyright == 'yes'));
      else
        $this->assertTrue( ! isset($etd->mods->copyright) || ($etd->mods->copyright == '') || ($etd->mods->copyright == 'no'));
    }
    if ($submission_agreement !== null) {
      if ($submission_agreement) {
        $config = Zend_Registry::get('config');
        $this->assertEqual($config->useAndReproduction, $etd->mods->useAndReproduction);
      } else {
        $this->assertEqual('', $etd->mods->useAndReproduction);
      }
    }
  }

  /**
   * end rights
   */


  /**
   * Test school action
   * * @todo test for incorrect role
   */
  function testSchoolAction() {
    $this->honors_etdpid;

    ///Test with superuser - this is the only one who should be able to access the view
    $this->test_user->role = "superuser";
    Zend_Registry::set('current_user', $this->test_user);

    $EditController = new EditControllerForTest($this->request,$this->response);

    $this->setUpGet(array('pid' => $this->honors_etdpid));
    $EditController->schoolAction();
    $this->assertIsA($EditController->view->etd, "etd");
    $this->assertEqual($EditController->view->title,  "Edit School");
    $this->assertEqual($EditController->view->schoolId,  "honors");
    $this->assertIsA($EditController->view->options, "array");
    $this->assertEqual(count($EditController->view->options),  4);


  }

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

    // default form values should be pre-set from etd fixture
    $this->assertFalse($EditController->view->embargo);
    $this->assertEqual(etd_mods::EMBARGO_NONE, $EditController->view->embargo_level);
    $this->assertTrue($EditController->view->pq_submit);
    $this->assertTrue($EditController->view->pq_copyright);
    $this->assertTrue($EditController->view->submission_agreement);

    // when redirected from save-rights (invalid input), posted values should supercede etd values
    $this->setUpGet(array("pid" => $this->etdpid, "embargo" => 1, "embargo_level" => etd_mods::EMBARGO_TOC,
			  "pq_submit" => 1, "pq_copyright" => 1,
			  "submission_agreement" => 0));
    $EditController->rightsAction();
    $this->assertTrue($EditController->view->embargo);
    $this->assertEqual(etd_mods::EMBARGO_TOC, $EditController->view->embargo_level);
    $this->assertTrue($EditController->view->pq_submit);
    $this->assertTrue($EditController->view->pq_copyright);
    $this->assertFalse($EditController->view->submission_agreement);
    

    // if degree is blank, should redirect to main edit page
    $etd = new etd($this->etdpid);
    $etd->mods->degree->name = "";
    $etd->save("blank degree to test rights edit page");
    $this->setUpGet(array('pid' => $this->etdpid));
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
