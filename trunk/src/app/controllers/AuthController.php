<?php
/** Zend_Controller_Action */
/* Require models */

require_once("models/user.php");

class AuthController extends Zend_Controller_Action {

  protected $_flashMessenger = null;

   public function init() {
     $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
     $this->initView();
   }

   public function postDispatch() {
     $this->view->messages = $this->_helper->flashMessenger->getCurrentMessages();
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
       $message = "Error: login failed";
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

       // forward to .. ?
       $this->_forward("index", "Index");
     } else {
       $this->_flashMessenger->addMessage("Login successful");
       
       $user = user::find_by_username($username);
       if ($user) {
	 // do what? save user to session?
	 $this->_flashMessenger->addMessage("found user information");
	 // forward to ... ?
	 $this->_forward("index", "Index");
       } else {
	 $this->_flashMessenger->addMessage("did not found user information, sending to edit");
	 $this->_forward("new", "user");
       }
       
     }
     
     // forward to ... ?
     //     $this->_forward("index", "Index");
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