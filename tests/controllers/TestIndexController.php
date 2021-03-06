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
      $index->getNews(new Zend_Config(array("news_feed" => array("url"  => "http://xxx/"))));
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
    $this->assertEqual("Submit IT", $result["Submission Workshops"]["description"]);
    //First date and location
    $this->assertEqual("Mon Apr 4 2011 11:00am", $result["Submission Workshops"]["whenWhere"][0]["start"]);
    $this->assertEqual("1:15pm", $result["Submission Workshops"]["whenWhere"][0]["end"]);
    $this->assertEqual("College Study Room", $result["Submission Workshops"]["whenWhere"][0]["where"]);
    //Second date and location
    $this->assertEqual("Mon Apr 11 2011 2:00pm", $result["Submission Workshops"]["whenWhere"][1]["start"]);
    $this->assertEqual("3:00pm", $result["Submission Workshops"]["whenWhere"][1]["end"]);
    $this->assertEqual("Grad House", $result["Submission Workshops"]["whenWhere"][1]["where"]);
    //Third date and location
    $this->assertEqual("Mon Apr 18 2011 5:30pm", $result["Submission Workshops"]["whenWhere"][2]["start"]);
    $this->assertEqual("7:15pm", $result["Submission Workshops"]["whenWhere"][2]["end"]);
    $this->assertEqual("Candler Church", $result["Submission Workshops"]["whenWhere"][2]["where"]);


    //Only has start date (all day event in google) - deadlines etc.
    $this->assertEqual("Saint Patrick's Day Party", $result["Saint Patrick's Day"]["description"]);
    //First date and location
    $this->assertEqual("Thu Mar 17 2011", $result["Saint Patrick's Day"]["whenWhere"][0]["start"]);
    $this->assertEqual("", $result["Saint Patrick's Day"]["whenWhere"][0]["end"]);
    $this->assertEqual("The Pub", $result["Saint Patrick's Day"]["whenWhere"][0]["where"]);


  }


    function test_createCache() {
    $index = new IndexControllerForTest($this->request,$this->response);

    //Check that it is a cache object
    $cache = $index->createCache(60, "ETD_test_prefix");
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
