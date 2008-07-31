<?
	require_once("../bootstrap.php"); 

	require_once('../ControllerTestCase.php');
    require_once('controllers/IndexController.php');
      
    class IndexControllerTest extends ControllerTestCase {

    	
    	function setUp()
    	{
//    		$this->loadDB('');
    		
    		$_GET 	= array();
    		$_POST	= array();
    		
    		$this->response = $this->makeResponse();
    		$this->request  = $this->makeRequest();
    	}
    	
    	function tearDown()
    	{
//    		$this->loadDB('');
    	}
    	
    	function testIndexAction()
    	{
			$IndexController = new IndexControllerForTest($this->request,$this->response);
			
			//$this->setUpPost(array('login' => array('username' => 'user_with_pwd', 'pwd' => 'test')));
			
			$IndexController->indexAction();
			
			$viewVars = $IndexController->view->getVars();	
			$this->assertEqual($viewVars['title'], 'Welcome to testProject');				
    	}
    }
        
    class IndexControllerForTest extends IndexController 
    {
	 	public $renderRan = false;
	 	public $redirectRan = false;
	 	
		public function initView()
		{
			$this->view = new Zend_View();
		}
		
		public function render()
		{
			$this->renderRan = true;
		}
		
		public function _redirect()
		{
			$this->redirectRan = true;
		}
    } 	
    
    
    $test = &new IndexControllerTest();
    $test->run(new HtmlReporter());
    
    $test->tearDown();
?>