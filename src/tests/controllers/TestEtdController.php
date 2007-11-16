<?
require_once('../ControllerTestCase.php');
require_once('controllers/EtdController.php');
      
class etdControllerTest extends ControllerTestCase {

  // array of test foxml files & their pids 
  private $etdxml;
  
  function setUp() {
    $_GET 	= array();
    $_POST	= array();
    
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();

    $this->etdxml = array("etd1" => "test:etd1",
		    "etd2" => "test:etd2",
		    "etd3" => "test:etd3");
    

    // load a test objects to repository
    foreach (array_keys($this->etdxml) as $etdfile) 
      fedora::ingest(file_get_contents('fixtures/' . $etdfile . '.xml'), "loading test object");

  }
  
  function tearDown() {
    foreach ($this->etdxml as $file => $pid)
      fedora::purge($pid, "removing test object");
  }
  
  function testIndexAction() {
    $IndexController = new IndexControllerForTest($this->request,$this->response);
    
    //$this->setUpPost(array('login' => array('username' => 'user_with_pwd', 'pwd' => 'test')));
    
    $IndexController->indexAction();
    
    $viewVars = $IndexController->view->getVars();	
    $this->assertEqual($viewVars['title'], 'Welcome to testProject');				
  }
 }



class etdControllerForTest extends etdController {
  
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


?>