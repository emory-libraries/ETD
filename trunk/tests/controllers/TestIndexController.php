<?php
require_once("../bootstrap.php"); 
/**
 * unit tests for the Index Controller  (main index page only)
 */


require_once('../ControllerTestCase.php');
require_once('controllers/IndexController.php');

class IndexControllerTest extends ControllerTestCase {
  private $_realconfig;
  private $testconfig;
  
  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();

    // store real config to restore later
    $this->_realconfig = Zend_Registry::get('config');

    // stub config 
    $this->testconfig = new Zend_Config(array());
    Zend_Registry::set('config', $this->testconfig);
  }
  
  function tearDown() {
    // restore real config
    Zend_Registry::set('config', $this->_realconfig);
  }
    	
  function testIndexAction() {
    $IndexController = new IndexControllerForTest($this->request,$this->response);

    // NOTE: currently loading feed from another url
    $this->expectException(new Exception("Calendar feed is not configured"));
    $this->expectError("Error retrieving news: News feed is not configured");
    $IndexController->indexAction();
    
    $this->assertTrue(isset($IndexController->view->title));

    // FIXME: test the feed part of this page by customizing the absoluteUrl helper  ?
    //$this->assertIsA($IndexController->view->feed, "Zend_Feed_Rss");
  }


  function testGetNews() {
    $index = new IndexControllerForTest($this->request,$this->response);
    // news feed not configured
    try {
      $index->getNews($this->testconfig);
    } catch (Exception $e) {
      $ex = $e;		// store for testing outside the try/catch
    }
    $this->assertIsA($ex, "Exception");
    $this->assertPattern("/News feed is not configured/", $ex->getMessage());
    unset($ex);

    // bogus url for news feed
    try {
      $index->getNews(new Zend_Config(array("news_feed" => array("url"  => "http://localhost/"))));
    } catch (Exception $e) {
      $ex = $e;		// store for testing outside the try/catch
    }
    $this->assertIsA($ex, "Exception");
    $this->assertPattern("/Could not parse ETD news feed/", $ex->getMessage());
    unset($ex);

    // FIXME: how to test success feed?  how to create mock feed ?
	
  }

  function testGetCalendar() {
      //Create a feed
      $calendarFeed = Zend_Feed_Reader::importFile("../fixtures/calendar.xml");

    $index = new IndexControllerForTest($this->request,$this->response);
    $result = $index->getCalendar($calendarFeed);
    $this->assertIsA($result, "array");
        
    //Test event with mutiple dates and times
    //The order of whenWhere arrays are important because the dates are sorted by start time
    $this->assertEqual("Come and submit your etd", $result["ETD Test Event"]["description"]);
    //First date and location
    $this->assertEqual("Mon Feb 14 2011 5:15pm", $result["ETD Test Event"]["whenWhere"][0]["start"]);
    $this->assertEqual("6:15pm", $result["ETD Test Event"]["whenWhere"][0]["end"]);
    $this->assertEqual("Grad School Building", $result["ETD Test Event"]["whenWhere"][0]["where"]);
    //Second date and location
    $this->assertEqual("Sun Feb 20 2011 6:00pm", $result["ETD Test Event"]["whenWhere"][1]["start"]);
    $this->assertEqual("7:00pm", $result["ETD Test Event"]["whenWhere"][1]["end"]);
    $this->assertEqual("Candler Building", $result["ETD Test Event"]["whenWhere"][1]["where"]);
    //Third date and location
    $this->assertEqual("Sun Feb 27 2011 7:00pm", $result["ETD Test Event"]["whenWhere"][2]["start"]);
    $this->assertEqual("8:00pm", $result["ETD Test Event"]["whenWhere"][2]["end"]);
    $this->assertEqual("Rollins Building", $result["ETD Test Event"]["whenWhere"][2]["where"]);


    //Only has start date (all day event in google) - deadlines etc.
    $this->assertEqual("Last change to submit!", $result["Submission Deadline"]["description"]);
    //First date and location
    $this->assertEqual("Sun Mar 13 2011", $result["Submission Deadline"]["whenWhere"][0]["start"]);
    $this->assertEqual("", $result["Submission Deadline"]["whenWhere"][0]["end"]);
    $this->assertEqual("", $result["Submission Deadline"]["whenWhere"][0]["where"]);

    
  }


    function test_createCache() {
    $index = new IndexControllerForTest($this->request,$this->response);

    //Check that it is a cache object
    $cache = $index->createCache(60);
    $this->assertIsA($cache, "Zend_Cache_Frontend_Output");

    //Check that lifetime is set correctly
    $this->assertEqual($cache->getOption('lifetime'), 60);

  }

  function testSortByStartDate(){
      $index = new IndexControllerForTest($this->request,$this->response);

      //arrays containing the minimum amout of data for the function to work
      $a = array("start" => "Sun Jan 2, 2011 9am");
      $b = array("start" => "Wed Jan 5, 2011 10pm");

      $result = $index->sortByStartDate($a, $b);
      $this->assertEqual(-1, $result, "param 1 is earlier than parmam 2");

      $result = $index->sortByStartDate($b, $a);
      $this->assertEqual(1, $result, "param 1 is later than parmam 2");

      $result = $index->sortByStartDate($a, $a);
      $this->assertEqual(0, $result, "param 1 and parmam 2 are equal");


  }

}
        
class IndexControllerForTest extends IndexController {
  public $renderRan = false;
  public $redirectRan = false;
  
  public function initView() {
    $this->view = new Zend_View();
  }
  
  public function render() {
    $this->renderRan = true;
  }
  
  public function _redirect() {
    $this->redirectRan = true;
  }
} 	

    
runtest(new IndexControllerTest());
?>
