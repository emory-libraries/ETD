<?
require_once("../bootstrap.php"); 

require_once('../ControllerTestCase.php');
require_once('controllers/BrowseController.php');

class BrowseControllerTest extends ControllerTestCase {

  private $solr;
  
  function setUp() {
    $_GET   = array();
    $_POST  = array();
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();

    $person = new esdPerson();
    $this->test_user = $person->getTestPerson();
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
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);
    
    $BrowseController = new BrowseControllerForTest($this->request,$this->response);

    $this->setUpGet(array('nametype' => 'author', 'value' => 'smith'));
    
    //    $BrowseController->authorAction();
    /* NOTE: can't really test author action because the way we're
       currently testing/mocking Controllers doesn't actually process
       _forward() calls, which is how this is implemented.
    */
    $BrowseController->browseAction();
    $this->assertTrue(isset($BrowseController->view->title));
    $this->assertIsA($BrowseController->view->etdSet, "EtdSet");
    $this->assertIsA($BrowseController->view->paginator, "Zend_Paginator");    

    // FIXME: test browsefieldAction ?

    error_reporting($errlevel);     // restore prior error reporting
  }
  
  // committee and year browse are basically the same as author browse

  function testProgramAction() {
    $this->solr->response->facets = new Emory_Service_Solr_Response_Facets(array("program_facet" => array()));
    
    // no param - should start at top-level
    $BrowseController = new BrowseControllerForTest($this->request,$this->response);
    $BrowseController->programsAction();
    $this->assertTrue(isset($BrowseController->view->title));
    $this->assertIsA($BrowseController->view->collection, "programs");
    $this->assertEqual("Programs", $BrowseController->view->collection->label);
    $this->assertIsA($BrowseController->view->etdSet, "EtdSet");
    $this->assertIsA($BrowseController->view->paginator, "Zend_Paginator");    

    // somewhere deeper in the hierarchy
    $this->setUpGet(array('coll' => 'immunology'));
    $BrowseController->programsAction();
    $this->assertTrue(isset($BrowseController->view->title));
    $this->assertIsA($BrowseController->view->collection, "programs");
    $this->assertEqual("Immunology", $BrowseController->view->collection->label);
    $this->assertIsA($BrowseController->view->etdSet, "EtdSet");

    // bogus collection name
    /**   NOTE: can't actually test because can't simulate gotoRouteAndExit for testing...
    $this->setUpGet(array('coll' => 'bogus'));
    $BrowseController->programsAction();
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
    $this->assertTrue(isset($BrowseController->view->title));
    $this->assertIsA($BrowseController->view->collection, "researchfields");
    $this->assertEqual("UMI Research Fields", $BrowseController->view->collection->label);
    $this->assertIsA($BrowseController->view->etdSet, "EtdSet");

    // somewhere deeper in the hierarchy
    $this->setUpGet(array('coll' => '0413'));
    $BrowseController->researchfieldsAction();
    $this->assertTrue(isset($BrowseController->view->title));
    $this->assertIsA($BrowseController->view->collection, "researchfields");
    $this->assertEqual("Music", $BrowseController->view->collection->label);
    $this->assertIsA($BrowseController->view->etdSet, "EtdSet");
    $this->assertIsA($BrowseController->view->paginator, "Zend_Paginator");    
  }
      
  function testMyAction() {
    // initial role set to student
    $BrowseController = new BrowseControllerForTest($this->request,$this->response);
    $this->solr->response->numFound = 1;
    $BrowseController->myAction();
    $this->assertTrue(isset($BrowseController->view->title));
    $this->assertIsA($BrowseController->view->etdSet, "EtdSet");
    $this->assertIsA($BrowseController->view->paginator, "Zend_Paginator");    
    $this->assertEqual("Your document", $BrowseController->view->list_description);
    // check that view is correctly set to use list template
    $viewRenderer = $BrowseController->getHelper("viewRenderer");
    $this->assertEqual("list", $viewRenderer->getScriptAction());
    // test pluralization of label
    $this->solr->response->numFound = 2;
    $BrowseController->myAction();
    $this->assertEqual("Your documents", $BrowseController->view->list_description);

    // test faculty version of my etds page
    $this->test_user->role = "faculty";
    $BrowseController->myAction();
    $this->assertEqual("Your students' documents", $BrowseController->view->list_description);
    // check that view is correctly set to use list template
    $viewRenderer = $BrowseController->getHelper("viewRenderer");
    $this->assertEqual("faculty-my", $viewRenderer->getScriptAction());

    // staff - no records to find for my etds page
    $this->test_user->role = "staff";
    $BrowseController = new BrowseControllerForTest($this->request,$this->response);
    $BrowseController->myAction();
    $this->assertFalse(isset($BrowseController->view->etdSet));
    $viewRenderer = $BrowseController->getHelper("viewRenderer");
    // not rendering list view script - using default 
    $this->assertEqual("", $viewRenderer->getScriptAction());

    // NOTE: no easy way to test forwarding from my to myProgram for grad coordinator
  }

  function testMyProgramAction() {
    // user is not a program coordinator
    $BrowseController = new BrowseControllerForTest($this->request,$this->response);
    $result = $BrowseController->myProgramAction();
    $this->assertFalse($result);    // not authorized
    $this->assertFalse(isset($BrowseController->view->title));
    $this->assertFalse(isset($BrowseController->view->list_title));
    $this->assertFalse(isset($BrowseController->view->etdSet));
    $this->assertFalse(isset($BrowseController->view->show_status));
    $this->assertFalse(isset($BrowseController->view->show_lastaction));

    // set program coordinator department so user will be recognized as valid
    $this->test_user->program_coord = "Chemistry";
    
    $BrowseController = new BrowseControllerForTest($this->request,$this->response);
    $BrowseController->myProgramAction();
    $this->assertTrue(isset($BrowseController->view->title));
    $this->assertTrue(isset($BrowseController->view->list_title));
    $this->assertIsA($BrowseController->view->etdSet, "EtdSet");
    $this->assertIsA($BrowseController->view->paginator, "Zend_Paginator");    
    $this->assertTrue($BrowseController->view->show_status);
    // not used currently (switched to solrEtd for response time)
    //    $this->assertTrue($BrowseController->view->show_lastaction);
  }

  function testRecentAction() {
    $BrowseController = new BrowseControllerForTest($this->request,$this->response);
    $BrowseController->recentAction();
    $this->assertTrue(isset($BrowseController->view->title));
    $this->assertTrue(isset($BrowseController->view->list_title));
    $this->assertIsA($BrowseController->view->etdSet, "EtdSet");   
    $this->assertIsA($BrowseController->view->paginator, "Zend_Paginator");
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
    Zend_Controller_Action_HelperBroker::addPrefix('Test_Controller_Action_Helper');
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
