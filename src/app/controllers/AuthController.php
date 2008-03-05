<?php
/** Zend_Controller_Action */
/* Require models */

require_once("models/user.php");
require_once("models/esdPerson.php");

class AuthController extends Etd_Controller_Action {

   public function loginAction() {
     $login = $this->_getParam('login', null);
     $username = $login['username'];
     $password = $login['password'];

     // make sure both are set before attempting to authenticate...
     if ($username == '' || $password == '') {
       $this->_helper->flashMessenger->addMessage("Error: please supply username and password");
       $this->_helper->redirector->gotoRoute(array("controller" => "index",
						   "action" => "index"), "", true);
     }
     
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
       $this->_helper->flashMessenger->addMessage($message);

       // forward to .. ?
       $this->_helper->redirector->gotoRoute(array("controller" => "index",
						   "action" => "index"), "", true);
						   
     } else {	
       $this->_helper->flashMessenger->addMessage("Login successful");
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

   // only expects to be called via ajax
   public function setroleAction() {
     if ($this->env != "development") return;
     $this->_helper->viewRenderer->setNoRender(true);

     $role = $this->_getParam("role");
     $this->current_user->role = $role;
   }

   public function logoutAction() {
     $auth = Zend_Auth::getInstance();
     $auth->clearIdentity();
     unset($this->view->current_user);
     $this->_helper->flashMessenger->addMessage("Logout successful");

     // forward to ... ?
     $this->_helper->redirector->gotoRoute(array("controller" => "index",
						 "action" => "index"), "", true);

   }

   public function deniedAction() {
     $this->view->title = "Access Denied";
   }
   
}
?>
