<?
require_once("../bootstrap.php"); 
/**
 * unit tests for the Documents Controller
 * - these are basically static pages with content in the templates
 * just checking that titles are set correctly and printable is true
 * 
 */

require_once('../ControllerTestCase.php');
require_once('controllers/DocsController.php');
      
class DocsControllerTest extends ControllerTestCase {
  
  // Test Data
  private $docs_feed_url = "https://digital.library.emory.edu/taxonomy/term/26/all/feed";
  private $topics = array(
    array("subject" => 'about', "link" => 'https://digital.library.emory.edu/content/etd/about'),
    array("subject" => 'faq', "link" => 'https://digital.library.emory.edu/content/etd/faq'),
    array("subject" => 'instructions', "link" => 'https://digital.library.emory.edu/content/etd/instructions'),
    array("subject" => 'ip', "link" => 'https://digital.library.emory.edu/content/etd/ip'),
    array("subject" => 'policies', "link" => 'https://digital.library.emory.edu/content/etd/policies'),
    array("subject" => 'boundcopies', "link" => 'https://digital.library.emory.edu/content/etd/boundcopies'),                                                                       
  );
  
  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
    $this->rss_data = new Zend_Feed_Rss($this->docs_feed_url);
  }
  function tearDown() {
  }

  function testIndexAction() {
    $DocsController = new DocsControllerForTest($this->request,$this->response);
    $DocsController->indexAction();
    $this->assertNotNull($DocsController->view->title);
    $this->assertFalse($DocsController->view->printable);
  }
  
  function testTopicAction() {
    $DocsController = new DocsControllerForTest($this->request,$this->response);
    foreach ($this->topics as $topic) {
      $DocsController->topicAction($topic["subject"]);
      $this->assertNotNull($DocsController->view->title);
    }    

  }
  
  function test_foundSubjectInFeed() {
    $index = new DocsControllerForTest($this->request,$this->response);
    
    foreach ($this->topics as $topic) {
      $this->assertTrue($index->foundSubjectInFeed($topic["subject"], $topic["link"]));
    }
  }
        
  function test_getTopicSubject() {
    $index = new DocsControllerForTest($this->request,$this->response);
    
    foreach ($this->topics as $topic) {

      $rss_function = "Data pulled from function."; 
      $rss_feed_section = "Data pulled from rss feed."; 

      // Get the section from the rss feed via getTopicSubject function
      try {
        $rss_function = $index->getTopicSubject($topic["subject"], $this->rss_data, $this->docs_feed_url);
      } catch (Exception $e) {
        $ex = $e; 
      }

      // Get the section from the rss feed locally
      foreach ($this->rss_data as $part) {
        // Check if the title string in the feed contains the topic
        if ($index->foundSubjectInFeed($topic["subject"],$part->link())) {
          // If we do have a match on the title, then extract this section.
          $rss_feed_section = $docSubject = "<h3>" . $part->title() . "</h3>" . $part->description();
        }
      }  
      
      // Compare the results from the function to those obtained locally.     
      $this->assertEqual(strlen($rss_feed_section), strlen($rss_function));
      $this->assertEqual($rss_feed_section, $rss_function);
      unset($ex); 
    }
  }  
}

class DocsControllerForTest extends DocsController {
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

runtest(new DocsControllerTest());

?>
