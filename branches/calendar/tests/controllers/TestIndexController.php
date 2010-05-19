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
    $this->expectError();	// news feed
    $this->expectError();	// recent etds
    $IndexController->indexAction();
    
    $this->assertTrue(isset($IndexController->view->title));

    // FIXME: test the feed part of this page by customizing the absoluteUrl helper  ?
    //$this->assertIsA($IndexController->view->feed, "Zend_Feed_Rss");
  }


  function test_getNews() {
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

  function test_getCalendar() {
    $index = new IndexControllerForTest($this->request,$this->response);
    // calendar feed not configured
    try {
      $index->getCalendar($this->testconfig);
    } catch (Exception $e) {
      $ex = $e;		// store for testing outside the try/catch
    }
    $this->assertIsA($ex, "Exception");
    $this->assertPattern("/Calendar feed is not configured/", $ex->getMessage());
    unset($ex);

    // bogus url for calendar feed
    try {
      $index->getCalendar(new Zend_Config(array("calendar_feed" => array("url"  => "http://localhost/"))));
    } catch (Exception $e) {
      $ex = $e;		// store for testing outside the try/catch
    }
    $this->assertIsA($ex, "Exception");
    $this->assertPattern("/Could not parse ETD calendar feed/", $ex->getMessage());
    unset($ex);

    // FIXME: how to test success feed?  how to create mock feed ?

  }



    function test_createCache() {
    $index = new IndexControllerForTest($this->request,$this->response);

    //Check that it is a cache object
    $cache = $index->createCache("name", 60);
    $this->assertIsA($cache, "Zend_Cache_Frontend_Output");

    //Check that lifetime is set correctly
    $this->assertEqual($cache->getOption('lifetime'), 60);

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