<?
require_once("UnitTest.php");  
 
class ControllerTestCase extends UnitTest {
  protected $request;
  protected $response;	
  
  protected function makeRequest($url = null) {
    return new Zend_Controller_Request_Http($url);
  }
  
  protected function makeResponse() {
    return new Zend_Controller_Response_Http();
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
}


/* bypass the real redirector helper because routes aren't defined */
class TestEtd_Controller_Action_Helper_Redirector extends Zend_Controller_Action_Helper_Abstract {
  public function gotoRoute(array $urlOptions = array(), $name = null, $reset = false) {
    $this->_actionController->_redirect();
  }
}

/* bypass real flashmessenger helper to simplify; clear out messages once they are retrieved */
class TestEtd_Controller_Action_Helper_FlashMessenger extends Zend_Controller_Action_Helper_Abstract {
  private $messages;
  public function __construct() { $this->messages = array(); }
  public function addMessage($text) {  $this->messages[] = $text;  }
  public function getMessages() {
    $messages = $this->messages;
    $this->messages = array();		// as a convenience, clear out messages when retrieving them
    return $messages;
  }


}


?>