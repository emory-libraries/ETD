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
  private $docs_feed_url = "https://digital.library.emory.edu/taxonomy/term/26/all/feed";
  
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
  
  function test_getTopicSubjectAbout() {
    $index = new DocsControllerForTest($this->request,$this->response);
    $subject = "about";
    $subject_title = $index->getTitleSubject($subject);

    $rss_function = "Data pulled from function."; 
    $rss_feed_section = "Data pulled from rss feed."; 

    // Get the title from the function
    try {
      $rss_function = $index->getTopicSubject($subject, $this->rss_data, $this->docs_feed_url);
    } catch (Exception $e) {
      $ex = $e;   // store for testing outside the try/catch
    }

    foreach ($this->rss_data as $part) {
      // Check if the title string in the feed contains the topic
      if (!(strpos($part->title(),$subject_title)===false)) {
        $rss_feed_section = $docSubject = "<h3>" . $part->title() . "</h3>" . $part->description();
      }
    }       
    $this->assertEqual(strlen($rss_feed_section), strlen($rss_function));
    $this->assertEqual($rss_feed_section, $rss_function);
    unset($ex); 
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
