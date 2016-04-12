<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

/** Zend_Controller_Action */
/* Require models */

require_once("models/authorInfo.php");
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

     $config_dir = Zend_Registry::get("config-dir");
     $ldap_config = new Zend_Config_Xml($config_dir . "ldap.xml", $this->env);

     $authAdapter = new Zend_Auth_Adapter_Ldap($ldap_config->toArray(), $username, $password);
     $auth = Zend_Auth::getInstance();

     // Check option to bypass ldap certificate in non-production environments only
     $environment = Zend_Registry::get('env-config');
     $config = Zend_Registry::get('config');
     if (($environment->mode != "production") && isset($config->certificate_required->ldap)) {
       putenv('LDAPTLS_REQCERT=' . $config->certificate_required->ldap);
     }

     $result = $auth->authenticate($authAdapter);
     if (!$result->isValid()) {
       $message = implode(",", $result->getMessages());
       switch($result->getCode()) {
       case Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND :
       case Zend_Auth_Result::FAILURE_IDENTITY_AMBIGUOUS :
   $message .= " - wrong username?";
   break;
       case Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID:
   $message .= " - wrong password?";
   break;
       default:
  // no additional information for user
       }

       // display information about failed login / reason
       $this->_helper->flashMessenger->addMessage($message);

       // reload the last page
       $this->_helper->redirector->gotoUrl($url, array("prependBase" => false));
     } else {
       $this->_helper->flashMessenger->addMessage("Login successful");

       // find this user in ESD and save their user information
       $esd = new esdPersonObject();
       try {
   $current_user = $esd->findByUsername($username);

   // if user is not found in ESD, force init without ESD
   if (! $current_user instanceOf esdPerson)
     throw new Exception("Username $username not found in ESD");
       } catch (Exception $e) {
   // if ESD is not accessible, create an esdPerson object with netid only
   $current_user = $esd->initializeWithoutEsd($username);
   $this->logger->warn("could not access ESD; logging in without full user information");

   $this->_helper->flashMessenger->addMessage("Warning: could not access Emory Shared Data; some functionality may not be available");
       }

       // store username for newly logged in user
       $this->logger->setEventItem('username', $username);
       $this->logger->debug("login (role = " . $current_user->role . ")");


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
    // allow set role functionality in any mode but production (i.e., dev or staging)
     if ($this->env == "production") return false;

     $this->_helper->viewRenderer->setNoRender(true);

     $role = $this->_getParam("role");
     if (strstr($role, "coordinator")) {
       $dept = str_replace("coordinator:", "", $role);
       $this->current_user->role = "staff"; // shouldn't matter
       $this->current_user->program_coord = $dept;  // program coordinator of department
     } elseif (strpos($role, " student")) {
       $schools_cfg = Zend_Registry::get("schools-config");
       switch ($role) {
       case "grad student": $school = "graduate_school"; break;
       case "honors student": $school = "emory_college"; break;
       case "candler student": $school = "candler"; break;
       case "rollins student": $school = "rollins"; break;
       case "nhwson student": $school = "nhwson"; break;
       }
       // set student to be recognized for appropriate school
       $this->current_user->academic_career = $schools_cfg->$school->db_id;
       $this->current_user->role = "student";
     } else {
       $this->current_user->role = $role;
     }


     // if not setting role to program coordinator, clear out any program coordinator dept.
     if (! strstr($role, "coordinator")) {
       $this->current_user->program_coord = null;
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
