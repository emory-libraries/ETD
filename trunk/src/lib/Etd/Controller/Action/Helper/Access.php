<?php

class Etd_Controller_Action_Helper_Access extends Zend_Controller_Action_Helper_Abstract {

  public function allowedOnEtd($action, etd $etd = null) {
    $current_user = $this->_actionController->current_user;
    $acl = $this->_actionController->acl;
    
    if ($etd) {		// a specific etd object
      $role = $etd->getUserRole($current_user);	//  - user may have a different role on this etd
      $resource = $etd->getResourceId();
    } else {				// generic etd
      $role = $current_user->role;
      $resource = "etd";
    }
    
    $allowed = $acl->isAllowed($role, $resource, $action);
    if (!$allowed) $this->notAllowed($action, $role, $resource);
    return $allowed;
  }

  public function allowedOnEtdFile($action, etd_file $etdfile = null) {
    $current_user = $this->_actionController->current_user;
    $acl = $this->_actionController->acl;
    
    if ($etdfile) {		// a specific etd object
      $role = $etdfile->parent->getUserRole($current_user);	//  - user may have a different role on this etd
      $resource = $etdfile->getResourceId();
    } else {				// generic etd file
      $role = $current_user->role;
      $resource = "file";
    }
    
    $allowed = $acl->isAllowed($role, $resource, $action);
    if (!$allowed) $this->notAllowed($action, $role, $resource);
    return $allowed;
  }


  public function allowedOnUser($action, user $user = null) {
    $current_user = $this->_actionController->current_user;
    $acl = $this->_actionController->acl;

    // logged in user may have specific role on user object
    if ($user) {
      $role = $user->getUserRole($current_user);
    } else {
      $role = $current_user->role;
    }

    // but there are no subclasses of user resources
    $resource = "user";

    $allowed = $acl->isAllowed($role, $resource, $action);
    if (!$allowed) $this->notAllowed($action, $role, $resource);
    return $allowed;
  }

  
  // redirect to a generic access denied page, with minimal information why
  private function notAllowed($action, $role, $resource) {
    $flashMessenger = $this->_actionController->getHelper("FlashMessenger");
    $redirector = $this->_actionController->getHelper("Redirector");
    
    $flashMessenger->addMessage("Error: " . $this->_actionController->current_user->netid
				. " (role=" . $role .  ") is not authorized to $action $resource");
    $redirector->gotoRoute(array("controller" => "auth", "action" => "denied"), "", true);
  }

  
}