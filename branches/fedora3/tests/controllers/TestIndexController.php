<?php
require_once("../bootstrap.php"); 
/**
 * unit tests for the Index Controller  (main index page only)
 */


require_once('../ControllerTestCase.php');
require_once('controllers/IndexController.php');

class IndexControllerTest extends ControllerTestCase {
  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
  }
  
  function tearDown() {}
    	
  function testIndexAction() {
    $IndexController = new IndexControllerForTest($this->request,$this->response);

    // NOTE: currently loading feed from another url
    //$this->expectError("Could not parse Feed 'recent'");  // text of error changed...
    $this->expectError();
    $IndexController->indexAction();
    
    $viewVars = $IndexController->view->getVars();	
    $this->assertTrue(isset($IndexController->view->title));

    // FIXME: test the feed part of this page by customizing the absoluteUrl helper  ?
    //    $this->assertIsA($IndexController->view->feed, "Zend_Feed_Rss");
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