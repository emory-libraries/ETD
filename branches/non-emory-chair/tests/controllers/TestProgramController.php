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

  private $pid;

  /**
   * FedoraConnection 
   */
  private $fedora;

  private $_realconfig;

  function __construct() {
    $this->fedora = Zend_Registry::get('fedora');
    $fedora_cfg = Zend_Registry::get('fedora-config');
    
    // get test pid for fedora fixture
    $this->pid = $this->fedora->getNextPid($fedora_cfg->pidspace);
  }

  
  function setUp() {
    $ep = new esdPerson();
    $this->test_user = $ep->getTestPerson();
    $this->test_user->role = "superuser";
    Zend_Registry::set('current_user', $this->test_user);
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();


    $dom = new DOMDocument();
    $dom->load("../fixtures/programs.xml");
    $foxml = new foxml($dom);
    $foxml->pid = $this->pid;

    $foxml->ingest("loading test object");

    // store real config to restore later
    $this->_realconfig = Zend_Registry::get('config');

    // stub config with test pid for programs_pid
    $testconfig = new Zend_Config(array("programs_collection" => array("pid" => $this->pid)));
    
    // temporarily override config in with test configuration
    Zend_Registry::set('config', $testconfig);
  }
  
  function tearDown() {
    // restore real config to registry
    Zend_Registry::set('config', $this->_realconfig);
    Zend_Registry::set('current_user', null);
    $this->fedora->purge($this->pid, "removing test programs object");
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
    $programController = new ProgramControllerForTest($this->request,$this->response);

    // edit one subsection
    $this->setUpGet(array('section' => 'religion', 'religion' => "Religion LABEL",
         "religion_members" => "american ethics hebrew asian music",
         "american" => "American Religion"));
    $programController->saveAction();
    $messages = $programController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/saved changes.*religion/i", $messages[0]);
    $this->assertTrue($programController->redirectRan);

    $programObj = new foxmlPrograms("#religion");
    // labels updated
    $this->assertEqual("Religion LABEL", $programObj->skos->label, "label updated");
    $this->assertEqual("American Religion", $programObj->skos->collection->american->label,
           "sub-label updated");
    $this->assertTrue($programObj->skos->collection->hasMember("ethics"),
          "religion has member ethics after update");
    $this->assertTrue($programObj->skos->collection->hasMember("american"),
          "religion has member american after update");
    $this->assertTrue($programObj->skos->collection->hasMember("hebrew"),
          "religion has member hebrew after update");
    $this->assertTrue($programObj->skos->collection->hasMember("asian"),
          "religion has member asian after update");
    $this->assertTrue($programObj->skos->collection->hasMember("music"),
          "religion has member music after update");
    // former collection members should have been removed
    $this->assertFalse($programObj->skos->collection->hasMember("comparative"),
           "religion no longer has member comparative");
    $this->assertFalse($programObj->skos->collection->hasMember("historical"),
           "religion no longer has member historical");

  }

  function testSave_nochanges() {
    $programController = new ProgramControllerForTest($this->request,$this->response);

    // set something the same value as it already is
    $this->setUpGet(array("section" => "music", "music" => "Music"));
    $programController->saveAction();
    $messages = $programController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/no changes made/i", $messages[0]);
    $this->assertTrue($programController->redirectRan);
  }

  function testSave_notauthorized() {
    $programController = new ProgramControllerForTest($this->request,$this->response);

    // permission denied
    $this->test_user->role = "guest";
    $this->assertFalse($programController->saveAction());
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
