<?
require_once("../UnitTest.php");  
 
class ControllerTestCase extends UnitTest
{
	protected $request;
	protected $response;	

	protected function makeRequest($url = null){
		return new Zend_Controller_Request_Http($url);
	}

	protected function makeResponse(){
		return new Zend_Controller_Response_Http();
	}	
	
	protected function setUpPost(array $params = array())
	{
		$_SERVER['REQUEST_METHOD'] = 'POST';
		foreach($params as $key=>$value){
			$_POST[$key] = $value;
		}
	}

	protected function setUpGet(array $params = array())
	{
		$_SERVER['REQUEST_METHOD'] = 'GET';
		foreach($params as $key=>$value){
			$_GET[$key] = $value;
		}
	}		
}
?>