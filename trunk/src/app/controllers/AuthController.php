<?php
/** Zend_Controller_Action */
/* Require models */

require_once("models/user.php");
require_once("models/esdPerson.php");

class AuthController extends Zend_Controller_Action {

  protected $_flashMessenger = null;

   public function init() {
     $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
     $this->initView();
   }

   public function postDispatch() {
     $this->view->messages = $this->_helper->flashMessenger->getMessages();
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
	 //	 $message .= " (no details)";
	 // fixme: should anything be added here?
       }

       // display information about failed login / reason
       $this->_flashMessenger->addMessage($message);

       // forward to .. ?
       $this->_helper->redirector->gotoRoute(array("controller" => "index",
						   "action" => "index"), "", true);
     } else {	
       $this->_flashMessenger->addMessage("Login successful");
       // find this user in ESD and save their user information
       $esd = new esdPersonObject();
       $current_user = $esd->findByUsername($username);
       $auth->getStorage()->write($current_user);
       $this->view->current_user = $current_user;
       
       // fixme: where should this forward to ... ?
       $this->_helper->redirector->gotoRoute(array("controller" => "index",
						   "action" => "index"), "", true);
     }
   }

   public function setroleAction() {
     if ($this->view->site_mode != "development") {
       $this->_helper->flashMessenger->addMessage("Error: set role can only be used in development");
       $this->_helper->redirector->gotoRoute(array("controller" => "auth",
						   "action" => "denied"), "", true);
     }

     $role = $this->_getParam("role");
     $this->view->current_user->role = $role;
     
     // fixme: how to return to last page?
     $this->_forward("index", "Index");
   }

   public function logoutAction() {
     $auth = Zend_Auth::getInstance();
     $auth->clearIdentity();
     unset($this->view->current_user);
     $this->_flashMessenger->addMessage("Logout successful");

     // forward to ... ?
     $this->_helper->redirector->gotoRoute(array("controller" => "index",
						 "action" => "index"), "", true);

   }

   public function deniedAction() {
     $this->view->title = "Access Denied";
   }
   
}
?>
