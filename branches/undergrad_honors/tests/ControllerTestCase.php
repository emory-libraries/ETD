<?
require_once("UnitTest.php");  
 
class ControllerTestCase extends UnitTest {
  protected $request;
  protected $response;

  
  protected function makeRequest($url = null) {
    return new Zend_Controller_Request_Http($url);
  }
  
  protected function makeResponse() {
    return new TestEtd_Controller_Response_Http();
  }	
  
  protected function setUpPost(array $params = array()) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    foreach($params as $key=>$value){
      $_POST[$key] = $value;
    }
  }
  
  protected function setUpGet(array $params = array())  {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    foreach($params as $key=>$value){
      $_GET[$key] = $value;
    }
  }

  protected function resetGet(){
    $_GET = array();
  }
}



// used to test controller helpers
class ControllerForTest extends Etd_Controller_Action {
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



/* bypass the real redirector helper because routes aren't defined */
class Test_Controller_Action_Helper_Redirector extends Zend_Controller_Action_Helper_Abstract {
  public function gotoRoute(array $urlOptions = array(), $name = null, $reset = false) {
    $this->_actionController->_redirect();
  }
  public function gotoRouteAndExit(array $urlOptions = array(), $name = null, $reset = false) {
    $this->gotoRoute($urlOptions, $name, $reset);
    // no real way to simulate exiting...
  }

  public function gotoUrl($url, $opts) {
    $this->_actionController->_redirect();
  }
}

/* bypass real flashmessenger helper to simplify; clear out messages once they are retrieved */
class Test_Controller_Action_Helper_FlashMessenger extends Zend_Controller_Action_Helper_Abstract {
  private $messages;
  public function __construct() { $this->messages = array(); }
  public function addMessage($text) {  $this->messages[] = $text;  }
  public function getMessages() {
    $messages = $this->messages;
    $this->messages = array();		// as a convenience, clear out messages when retrieving them
    return $messages;
  }
}

class Test_Controller_Action_Helper_Layout extends Zend_Controller_Action_Helper_Abstract {
  public $enabled = true;
  public $name;
  public function disableLayout() { $this->enabled = false; }
  public function setLayout($name) { $this->name = $name; }
}

class Test_Controller_Action_Helper_GetFromFedora extends Etd_Controller_Action_Helper_GetFromFedora {
  protected $object = null;
  public function direct($pid, $type) {
    if ($this->object == null)  return $this->find_or_error($pid, $type);
    else return $this->object;
  }
  public function findById($pid, $type) { return $this->direct($pid, $type); }
  public function setReturnObject($obj) { $this->object = $obj; }
  public function clearReturnObject() { $this->object = null; }
}

class Test_Controller_Action_Helper_ProcessPDF
	extends Etd_Controller_Action_Helper_ProcessPDF {
  protected $result = null;
  
  public function direct($fileinfo) {
    $this->_actionController->view->errors = array();
    return $this->result;
  }
  public function setReturnResult($info) { $this->result = $info; }
  public function clearReturnResult() { $this->info = null; }
}

class Test_Controller_Action_Helper_IngestOrError
	extends Etd_Controller_Action_Helper_IngestOrError {
  protected $err = null;

  public function direct($object, $message, $objtype = "record", &$errtype = null) {
    if ($this->err == null) return "testpid";
    else $errtype = $this->err;
    return false;
  }
  public function setError($type) { $this->err = $type; }
  public function clearError() 	  { $this->err = null; }
}


// override http response class to allow setting headers (otherwise not allowed because of simpletest headers)
class TestEtd_Controller_Response_Http extends Zend_Controller_Response_Http {
  public function canSendHeaders() { return true; }
}

?>