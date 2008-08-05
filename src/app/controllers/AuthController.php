<?php
/** Zend_Controller_Action */
/* Require models */

require_once("models/user.php");
require_once("models/esdPerson.php");

class AuthController extends Etd_Controller_Action {

  public function postDispatch() {
    $this->view->messages = $this->_helper->flashMessenger->getMessages();
  }

  
   public function loginAction() {
     $login = $this->_getParam('login', null);
     $username = $login['username'];
     $password = $login['password'];
     $url = $login['url'];

     // make sure both are set before attempting to authenticate...
     if ($username == '' || $password == '') {
       $this->_helper->flashMessenger->addMessage("Error: please supply username and password");
       $this->_helper->redirector->gotoRoute(array("controller" => "index",
						   "action" => "index"), "", true);
     }
     
     $ldap_config = Zend_Registry::get('ldap-config');
     $authAdapter = new Zend_Auth_Adapter_Ldap($ldap_config->toArray(), $username, $password);
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

       // reload the last page
       $this->_helper->redirector->gotoUrl($url, array("prependBase" => false));
     } else {	
       $this->_helper->flashMessenger->addMessage("Login successful");

       // store username for newly logged in user
       $this->logger->setEventItem('username', $username);
       $this->logger->debug("login");

       // find this user in ESD and save their user information
       $esd = new esdPersonObject();
       try {
	 $current_user = $esd->findByUsername($username);
       } catch (Exception $e) {
	 $auth->clearIdentity();
	 $this->_helper->flashMessenger->addMessage("Error: problem accessing Emory Shared Data; could not complete login process");
	 // redirect to an error page
	 $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "unavailable"));
       }
       // store the password for accessing Fedora
       $current_user->setPassword($password);
       $auth->getStorage()->write($current_user);
       $this->view->current_user = $current_user;

       // reload the last page
       $this->_helper->redirector->gotoUrl($url, array("prependBase" => false));
     }
   }

   // only expects to be called via ajax
   public function setroleAction() {
     if ($this->env != "development") return false;
     $this->_helper->viewRenderer->setNoRender(true);

     $role = $this->_getParam("role");
     
     if (strstr($role, "coordinator")) {
       $dept = str_replace("coordinator:", "", $role);
       $this->current_user->role = "staff";	// shouldn't matter
       $this->current_user->ACPL8GPCO_N = $dept;	// program coordinator of department
     } else {
       $this->current_user->role = $role;
     }
   }

   public function logoutAction() {
     $auth = Zend_Auth::getInstance();
     $current_user = $auth->getIdentity();
     $this->logger->debug("logout");

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
