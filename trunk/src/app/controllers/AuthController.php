<?php
/** Zend_Controller_Action */
/* Require models */

class AuthController extends Zend_Controller_Action {

  protected $_flashMessenger = null;

   public function init() {
     $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
     $this->initView();
   }

   public function loginAction() {
     $login = $this->_getParam('login');
     $username = $login['username'];
     $password = $login['password'];

     // make sure both are set before attempting to authenticate...
     
     $env = Zend_Registry::get('env-config');
     $ldap_config = new Zend_Config_Xml("../config/ldap.xml", $env->mode);
     $authAdapter = new Zend_Auth_Adapter_Ldap($username, $password, $ldap_config->toArray());
     
     
     $auth = Zend_Auth::getInstance();

     $result = $auth->authenticate($authAdapter);
     if (!$result->isValid()) {
       $message = "Login failed";
       switch($result->getCode()) {
       case Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND :
       case Zend_Auth_Result::FAILURE_IDENTITY_AMBIGUOUS :
	 $message .= " - wrong username?";
	 break;
       case Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID:
	 $message .= " - wrong password?";
	 break;
       default:
	 $message .= " (no details?)";
       }
       
       // FIXME: more detailed error message, if possible
       $this->_flashMessenger->addMessage($message);
       print "$message\n";
     } else {
       print "Login successful\n";
     }
     // forward to ... ?
     $this->_forward("index", "Index");
     //     $this->_helper->viewRenderer->setNoRender(true);
   }


   public function logoutAction() {
     $auth = Zend_Auth::getInstance();
     $auth->clearIdentity();

     // forward to ... ?
     $this->_forward("index", "Index");
   }
   
}
?>