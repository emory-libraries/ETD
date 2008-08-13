<?

require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/SearchController.php');

class SearchControllerTest extends ControllerTestCase {

  private $mock_solr;
  
  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
    
    $this->resetGet();

    $this->mock_solr = &new Mock_Etd_Service_Solr();
    Zend_Registry::set('solr', $this->mock_solr);
  }

  function tearDown() {
    Zend_Registry::set('solr', null);
  }

  function testIndexAction() {
    $searchController = new SearchControllerForTest($this->request,$this->response);
    $searchController->indexAction();
    $viewVars = $searchController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
  }

  function testResultsAction() {
    $this->mock_solr->response->numFound = 2;
    
    $searchController = new SearchControllerForTest($this->request,$this->response);
    $this->setUpGet(array('query' => 'dissertation', 'title' => 'analysis',
			  'abstract' => 'exploration', 'tableOfContents' => 'chapter'));
    $searchController->resultsAction();
    $viewVars = $searchController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertIsA($viewVars['etdSet'], "EtdSet");
    // non-facet filter terms should be passed to view for display & facet links
    $this->assertEqual("dissertation", $viewVars['url_params']["query"]);
    $this->assertEqual("analysis", $viewVars['url_params']['title']);
    $this->assertEqual("exploration", $viewVars['url_params']['abstract']);
    $this->assertEqual("chapter", $viewVars['url_params']['tableOfContents']);

    // when only one match is found, should forward to full record page
    $this->mock_solr->response->numFound = 1;
    // mock etd so controller can find and pull out pid for the forward
    $etd = &new MockEtd();
    $etd->pid = "testpid:1";
    $this->mock_solr->response->docs[] = $etd;
    $searchController->resultsAction();
    $this->assertTrue($searchController->redirectRan);
  }

  public function testFacultySuggestorAction() {
    $searchController = new SearchControllerForTest($this->request,$this->response);
    $this->setUpGet(array("faculty" => "Halb"));	// Halbert (NOTE: relying on actual data in ESD)
    $searchController->facultysuggestorAction();
    $viewVars = $searchController->view->getVars();
    $this->assertIsA($viewVars['faculty'], "Array");
    $this->assertIsA($viewVars['faculty'][0], "esdPerson");
    // confirm xml output settings - layout disabled, content-type set to text/xml
    $layout = $searchController->getHelper("layout");
    $this->assertFalse($layout->enabled);
    $response = $searchController->getResponse();
    $headers = $response->getHeaders();
    $this->assertEqual("Content-Type", $headers[0]["name"]);
    $this->assertEqual("text/xml", $headers[0]["value"]);

    // error: no parameter
    $this->resetGet();
    $searchController = new SearchControllerForTest($this->request,$this->response);
    $this->expectException(new Exception("Either faculty or advisor parameter must be specified"));
    $searchController->facultysuggestorAction();

    // FIXME: test error condition when ESD is not accessible (how?)
  }

  // FIXME: test suggestor action (still in process)


  public function testSuggestorAction() {
    $this->mock_solr->response->numFound = 2;
    $this->mock_solr->response->facets = array("facet1" => array("hustle", "flow"), "facet2" => array("jingle", "bells"));

    $searchController = new SearchControllerForTest($this->request,$this->response);
    $this->setUpGet(array("query" => "name", "field" => "author"));
    $searchController->suggestorAction();
    $viewVars = $searchController->view->getVars();
    $this->assertIsA($viewVars['matches'], "Array");
    // terms from both facets should appear in matches
    $this->assertTrue(in_array("flow", $viewVars['matches']));
    $this->assertTrue(in_array("jingle", $viewVars['matches']));
    // confirm xml output settings - layout disabled, content-type set to text/xml
    $layout = $searchController->getHelper("layout");
    $this->assertFalse($layout->enabled);
    $response = $searchController->getResponse();
    $headers = $response->getHeaders();
    $this->assertEqual("Content-Type", $headers[0]["name"]);
    $this->assertEqual("text/xml", $headers[0]["value"]);

    // error without required query parameter
    $this->resetGet();
    $this->expectException(new Exception("Required parameter 'query' is not set"));
    $searchController->suggestorAction();
  }


}


class SearchControllerForTest extends SearchController {
  
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

runtest(new SearchControllerTest());
?>