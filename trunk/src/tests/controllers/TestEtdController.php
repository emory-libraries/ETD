<?
	require_once("../bootstrap.php"); 

	require_once('../ControllerTestCase.php');
    require_once('controllers/etdController.php');
      
    class etdControllerTest extends ControllerTestCase {

    	
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
        
    class etdControllerForTest extends etdController 
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
    
    
    $test = &new etdControllerTest();
    $test->run(new HtmlReporter());
    
    $test->tearDown();
?>