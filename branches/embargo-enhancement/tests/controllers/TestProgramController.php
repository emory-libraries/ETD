<?
require_once("../bootstrap.php"); 
/**
 * unit tests for the Config Controller
 * - display configuration xml (used in various xforms)
 */


require_once('../ControllerTestCase.php');
require_once('controllers/ProgramController.php');
      
class ProgramControllerTest extends ControllerTestCase {

  private $test_user;
  function setUp() {
    $ep = new esdPerson();
    $this->test_user = $ep->getTestPerson();
    $this->test_user->role = "superuser";
    Zend_Registry::set('current_user', $this->test_user);
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
  }
  function tearDown() {
    Zend_Registry::set('current_user', null);
  }

  function testXmlAction() {
    $programController = new ProgramControllerForTest($this->request,$this->response);
    
    $programController->xmlAction();
    $response = $programController->getResponse();
    $headers = $response->getHeaders();
    $this->assertEqual("Content-Type", $headers[0]["name"]);
    $this->assertEqual("text/xml", $headers[0]["value"]);
    $this->assertPattern('|<skos:Collection rdf:about="#programs">|', $response->getBody());
  }

  function testIndexAction() {
    $programController = new ProgramControllerForTest($this->request,$this->response);
    $programController->indexAction();
    $this->assertIsA($programController->view->programs, "programs");
    $this->assertIsA($programController->view->programs, "collectionHierarchy");
    $this->assertEqual("Programs", $programController->view->programs->collection->label);
    $this->assertTrue(isset($programController->view->title), "view has a title");

    $this->setUpGet(array('section' => 'grad'));
    $programController->indexAction();
    $this->assertEqual("Graduate", $programController->view->programs->collection->label);
    $this->assertTrue(isset($programController->view->section), "view has section");
  }

  function testSaveAction() {
    // ignore "indirect modification of overloaded property" notices
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);
    $programController = new ProgramControllerForTest($this->request,$this->response);

    // just simulate editing one subsection
    $this->setUpPost(array('section' => 'religion', 'religion' => "Religion LABEL",
			   "religion_members" => "american ethics hebrew asian music",
			   "american" => "American Religion"));
    $programController->saveAction();
    $messages = $programController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/saved changes/i", $messages[0]);
    $this->assertTrue($programController->redirectRan);

    $programObj = new foxmlPrograms("#religion");
    // labels updated
    $this->assertEqual("Religion LABEL", $programObj->skos->label);
    $this->assertEqual("American Religion", $programObj->skos->collection->american->label);
    $this->assertTrue($programObj->skos->collection->hasMember("ethics"));
    $this->assertTrue($programObj->skos->collection->hasMember("american"));
    $this->assertTrue($programObj->skos->collection->hasMember("hebrew"));
    $this->assertTrue($programObj->skos->collection->hasMember("asian"));
    $this->assertTrue($programObj->skos->collection->hasMember("music"));
    // former collection members should have been removed
    $this->assertFalse($programObj->skos->collection->hasMember("comparative"));
    $this->assertFalse($programObj->skos->collection->hasMember("historical"));

    // make the same changes again
    $this->setUpPost(array('section' => 'religion', 'religion' => "Religion LABEL",
			   "religion_members" => "american ethics hebrew asian music",
			   "american" => "American Religion"));
    $programController->saveAction();
    $messages = $programController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/no changes made/i", $messages[0]);
    $this->assertTrue($programController->redirectRan);


    $this->test_user->role = "guest";
    // permission denied
    $this->assertFalse($programController->saveAction());
    
    // undo the modification to revert object back to initial state
    $fedora = Zend_Registry::get("fedora");
    $hist = $fedora->getDatastreamHistory($programObj->pid, "SKOS");
    // remove just the latest revision of the datastream
    $purged = $fedora->purgeDatastream($programObj->pid, "SKOS", $hist->datastream[0]->createDate,
				     $hist->datastream[0]->createDate,
				     "undoing test change");

    // FIXME: this doesn't seem to undo the change properly/consistently somehow...

    error_reporting($errlevel);	    // restore prior error reporting
  }

}

class ProgramControllerForTest extends ProgramController {
  public $renderRan = false;

  
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

runtest(new ProgramControllerTest());

?>