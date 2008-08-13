<?
require_once("../bootstrap.php"); 

require_once('../ControllerTestCase.php');
require_once('controllers/BrowseController.php');

class BrowseControllerTest extends ControllerTestCase {

  private $solr;
  
  function setUp() {
    $_GET 	= array();
    $_POST	= array();
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();

    $this->test_user = new esdPerson();
    $this->test_user->role = "student";
    $this->test_user->netid = "test_user";
    Zend_Registry::set('current_user', $this->test_user);

    $this->solr = &new Mock_Etd_Service_Solr(); 
    Zend_Registry::set('solr', $this->solr);

  }

  function tearDown() {
    Zend_Registry::set('solr', null);
    Zend_Registry::set('current_user', null);
  }

  function testAuthorAction() {

    $BrowseController = new BrowseControllerForTest($this->request,$this->response);

    $this->setUpGet(array('nametype' => 'author', 'value' => 'smith'));
    
    //    $BrowseController->authorAction();
    /* NOTE: can't really test author action because the way we're
       currently testing/mocking Controllers doesn't actually process
       _forward() calls, which is how this is implemented.
    */
    $BrowseController->browseAction();
    $viewVars = $BrowseController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertIsA($viewVars['etdSet'], "EtdSet");

    // FIXME: test browsefieldAction ?
  }
  
  // committee and year browse are basically the same as author browse

  function testProgramAction() {
    $this->solr->response->facets = new Emory_Service_Solr_Response_Facets(array("program_facet" => array()));
    
    // no param - should start at top-level
    $BrowseController = new BrowseControllerForTest($this->request,$this->response);
    $BrowseController->programsAction();
    $viewVars = $BrowseController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertIsA($viewVars['collection'], "programs");
    $this->assertEqual("Programs", $viewVars['collection']->label);
    $this->assertIsA($viewVars['etdSet'], "EtdSet");

    // somewhere deeper in the hierarchy
    $this->setUpGet(array('coll' => 'immunology'));
    $BrowseController->programsAction();
    $viewVars = $BrowseController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertIsA($viewVars['collection'], "programs");
    $this->assertEqual("Immunology", $viewVars['collection']->label);
    $this->assertIsA($viewVars['etdSet'], "EtdSet");

    // bogus collection name
    /**		NOTE: can't actually test because can't simulate gotoRouteAndExit for testing...
    $this->setUpGet(array('coll' => 'bogus'));
    $BrowseController->programsAction();
    $viewVars = $BrowseController->view->getVars();
    $this->assertTrue($BrowseController->redirectRan);
    $messages = $BrowseController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/Error: Program not found/", $messages[0]);
    */
  }

  function testResearchFieldsAction() {
    $this->solr->response->facets = new Emory_Service_Solr_Response_Facets(array("subject_facet" => array()));
    
    // no param - should start at top-level
    $BrowseController = new BrowseControllerForTest($this->request,$this->response);
    $BrowseController->researchfieldsAction();
    $viewVars = $BrowseController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertIsA($viewVars['collection'], "researchfields");
    $this->assertEqual("UMI Research Fields", $viewVars['collection']->label);
    $this->assertIsA($viewVars['etdSet'], "EtdSet");

    // somewhere deeper in the hierarchy
    $this->setUpGet(array('coll' => '0413'));
    $BrowseController->researchfieldsAction();
    $viewVars = $BrowseController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertIsA($viewVars['collection'], "researchfields");
    $this->assertEqual("Music", $viewVars['collection']->label);
    $this->assertIsA($viewVars['etdSet'], "EtdSet");
  }
    	
  function testMyAction() {
    $BrowseController = new BrowseControllerForTest($this->request,$this->response);
    $BrowseController->myAction();
    $viewVars = $BrowseController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertIsA($viewVars['etdSet'], "EtdSet");
  }

  // FIXME: test forwarding to grad coord view, records for faculty view, etc. (?)


  function testMyProgramAction() {
    // user is not a program coordinator
    $BrowseController = new BrowseControllerForTest($this->request,$this->response);
    $result = $BrowseController->myProgramAction();
    $this->assertFalse($result);		// not authorized
    $viewVars = $BrowseController->view->getVars();
    $this->assertFalse(isset($viewVars['title']));
    $this->assertFalse(isset($viewVars['list_title']));
    $this->assertFalse(isset($viewVars['etdSet']));
    $this->assertFalse(isset($viewVars['show_status']));
    $this->assertFalse(isset($viewVars['show_lastaction']));

    // set program coordinator department so user will be recognized as valid
    $this->test_user->program_coord = "Chemistry";
    
    $BrowseController = new BrowseControllerForTest($this->request,$this->response);
    $BrowseController->myProgramAction();
    $viewVars = $BrowseController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertTrue(isset($viewVars['list_title']));
    $this->assertIsA($viewVars['etdSet'], "EtdSet");
    $this->assertTrue($viewVars['show_status']);
    $this->assertTrue($viewVars['show_lastaction']);
  }

  function testRecentAction() {
    $BrowseController = new BrowseControllerForTest($this->request,$this->response);
    $BrowseController->recentAction();
    $viewVars = $BrowseController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertTrue(isset($viewVars['list_title']));
    $this->assertIsA($viewVars['etdSet'], "EtdSet");
  }

  function testProquestAction() {
    $BrowseController = new BrowseControllerForTest($this->request,$this->response);
    $BrowseController->proquestAction();
    $this->assertTrue($BrowseController->redirectRan);
  }
  
}
        

class BrowseControllerForTest extends BrowseController {
  public $renderRan = false;
  public $redirectRan = false;
  public $forward_count = 0;
  
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
    
runtest(new BrowseControllerTest());

?>