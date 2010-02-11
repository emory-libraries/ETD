<?

require_once("../bootstrap.php");
require_once('ControllerTestCase.php');
require_once('controllers/SearchController.php');
require_once("fixtures/esd_data.php");

class SearchControllerTest extends ControllerTestCase {

  private $mock_solr;
  private $data;
  
  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
    
    $this->resetGet();

    $this->mock_solr = &new Mock_Etd_Service_Solr();
    Zend_Registry::set('solr', $this->mock_solr);

    
    $this->data = new esd_test_data();
    $this->data->loadAll();

  }

  function tearDown() {
    Zend_Registry::set('solr', null);
    $this->data->cleanUp();
  }

  function testIndexAction() {
    $searchController = new SearchControllerForTest($this->request,$this->response);
    $searchController->indexAction();
    $this->assertTrue(isset($searchController->view->title));
  }

  function testResultsAction() {
    $this->mock_solr->response->numFound = 2;
    
    $searchController = new SearchControllerForTest($this->request,$this->response);
    $this->setUpGet(array('query' => 'dissertation', 'title' => 'analysis',
			  'abstract' => 'exploration', 'tableOfContents' => 'chapter'));
    $searchController->resultsAction();
    $this->assertTrue(isset($searchController->view->title));
    $this->assertIsA($searchController->view->etdSet, "EtdSet");
    // non-facet filter terms should be passed to view for display & facet links
    $this->assertEqual("dissertation", $searchController->view->url_params["query"]);
    $this->assertEqual("analysis", $searchController->view->url_params['title']);
    $this->assertEqual("exploration", $searchController->view->url_params['abstract']);
    $this->assertEqual("chapter", $searchController->view->url_params['tableOfContents']);

    // when only one match is found, should forward to full record page
    $this->mock_solr->response->numFound = 1;
    // mock etd so controller can find and pull out pid for the forward
    //    $etd = &new MockEtd();
    //    $etd->pid = "testpid:1";
    $etd = new Emory_Service_Solr_Response_Document(array("PID" => "testpid:1"));
    $this->mock_solr->response->docs[] = $etd;
    $searchController->resultsAction();
    $this->assertTrue($searchController->redirectRan);
  }

  public function testFacultySuggestorAction() {
    $searchController = new SearchControllerForTest($this->request,$this->response);
    $this->setUpGet(array("faculty" => "Scho"));
    $searchController->facultysuggestorAction();
    $this->assertIsA($searchController->view->faculty, "esdPeople");
    $this->assertIsA($searchController->view->faculty[0], "esdPerson");
    // confirm xml output settings - layout disabled, content-type set to text/xml
    $layout = $searchController->getHelper("layout");
    $this->assertFalse($layout->enabled);
    $response = $searchController->getResponse();
    $headers = $response->getHeaders();
    $this->assertEqual("Content-Type", $headers[0]["name"]);
    $this->assertEqual("text/xml", $headers[0]["value"]);

    // new parameter for new feature - search for former faculty
    $this->setUpGet(array("faculty" => "Em", "former" => 1));
    $searchController->facultysuggestorAction();
    $this->assertIsA($searchController->view->faculty, "esdPeople");
    $this->assertIsA($searchController->view->faculty[0], "esdPerson");

    // error: no parameter
    $this->resetGet();
    $searchController = new SearchControllerForTest($this->request,$this->response);
    $this->expectException(new Exception("Either faculty or advisor parameter must be specified"));
    $searchController->facultysuggestorAction();


    // FIXME: test error condition when ESD is not accessible (how?)
  }


  public function testSuggestorAction() {
    $this->mock_solr->response->numFound = 2;
    $this->mock_solr->response->facets = array("facet1" => array("hustle", "flow"), "facet2" => array("jingle", "bells"));

    $searchController = new SearchControllerForTest($this->request,$this->response);
    $this->setUpGet(array("query" => "name", "field" => "author"));
    $searchController->suggestorAction();
    $this->assertIsA($searchController->view->matches, "Array");
    // terms from both facets should appear in matches
    $this->assertTrue(in_array("flow", $searchController->view->matches));
    $this->assertTrue(in_array("jingle", $searchController->view->matches));
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
    Zend_Controller_Action_HelperBroker::addPrefix('Test_Controller_Action_Helper');
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