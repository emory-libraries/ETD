<?

require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/SearchController.php');

Mock::generate('etd');

class SearchControllerTest extends ControllerTestCase {

  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
    
    $this->resetGet();
  }

  function testIndexAction() {
    $searchController = new SearchControllerForTest($this->request,$this->response);
    $searchController->indexAction();
    $viewVars = $searchController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
  }

  function testResultsAction() {
    $solr = &new MockEtd_Service_Solr();
    $response = new MockEmory_Service_Solr_Response();
    $response->numFound = 2;
    $response->docs = array();
    $response->facets = array();
    $solr->setReturnReference('query', $response);
    Zend_Registry::set('solr', $solr);
    
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
    $response->numFound = 1;
    $etd = &new MockEtd();
    $etd->pid = "testpid:1";
    $response->docs[] = $etd;
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
    $solr = &new MockEtd_Service_Solr();
    $response = new MockEmory_Service_Solr_Response();
    $response->numFound = 2;
    $response->docs = array();
    $response->facets = array("facet1" => array("hustle", "flow"), "facet2" => array("jingle", "bells"));
    $solr->setReturnReference('suggest', $response);	// calling suggest instead of query here
    Zend_Registry::set('solr', $solr);

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