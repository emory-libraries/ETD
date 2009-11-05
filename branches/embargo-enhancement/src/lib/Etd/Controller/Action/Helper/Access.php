<?php
/**
 * controller helper for checking access permissions of current user;
 * if not allowed, redirects to an access denied page with an error message.
 *
 * @category Etd
 * @package Etd_Controllers
 * @subpackage Etd_Controller_Helpers
 */

class Etd_Controller_Action_Helper_Access extends Zend_Controller_Action_Helper_Abstract {

  /**
   * check if an action is allowed to current user on an etd (particular or generic)
   * @param string $action
   * @param etd $etd particular etd to check permissions on - OPTIONAL, checks generic etd otherwise
   * @return bool 
   */
  public function allowedOnEtd($action, etd $etd = null) {
    $acl = $this->_actionController->acl;

    if (isset($this->_actionController->current_user)) 
      $current_user = $this->_actionController->current_user;
    else
      $role = "guest";

    if ($etd) {		// a specific etd object
      if (isset($current_user))
	$role = $etd->getUserRole($current_user);	//  - user may have a different role on this etd
      $resource = $etd->getResourceId();
    } else {				// generic etd
      if (isset($current_user))
	$role = $current_user->role;
      $resource = "etd";
    }

    
    $allowed = $acl->isAllowed($role, $resource, $action);
    if (!$allowed) $this->notAllowed($action, $role, $resource);
    return $allowed;
  }

  /**
   * check if an action is allowed to current user on an etd_file (particular or generic)
   * @param string $action
   * @param etd_file $etdfile particular etd_file to check against - OPTIONAL, checks generic etd_file otherwise
   * @return bool 
   */
  public function allowedOnEtdFile($action, etd_file $etdfile = null) {
    $current_user = $this->_actionController->current_user;
    $acl = $this->_actionController->acl;
    
    if ($etdfile) {		// a specific etdFile object
      $resource = $etdfile->getResourceId();
    } else {				// generic etd file
      $resource = "file";
    }

    if ($etdfile && isset($etdfile->etd)) {
      $role = $etdfile->etd->getUserRole($current_user);	//  - user may have a different role on this etd
    } else {
      $role = $current_user->role;
    }

    
    $allowed = $acl->isAllowed($role, $resource, $action);
    if (!$allowed) $this->notAllowed($action, $role, $resource);
    return $allowed;
  }

  /**
   * check if an action is allowed to current user on an author_info object (particular or generic)
   * @param string $action
   * @param user $user particular author_info to check against - OPTIONAL, checks generic authorInfo otherwise
   * @return bool 
   */
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

  
  /**
   * redirect to a generic access denied page, with brief message to user
   * @param string $action denied action
   * @param string $role user's role
   * @param string $resource resource attempted to access
   * @return bool 
   */
  public function notAllowed($action, $role, $resource) {
    $flashMessenger = $this->_actionController->getHelper("FlashMessenger");
    $redirector = $this->_actionController->getHelper("Redirector");
    if (isset($this->_actionController->current_user)) {
      $user = $this->_actionController->current_user->netid;
    } else{
      $user = "guest";
    }

    $message = "Error: $user (role=$role) is not authorized to $action $resource";

    // if resources is denied, NOT redirecting - that way logging in can reload
    // the denied page and user may have access
    $viewRenderer = $this->_actionController->getHelper("viewRenderer");
    $viewRenderer->setNoRender();		// don't render normally

    $response = $this->_actionController->getResponse();
    $response->setHttpResponseCode(403);	// Forbidden
    $viewRenderer->view->title = "Not Authorized";
    $viewRenderer->view->deny_message = $message;
    print $viewRenderer->renderScript("auth/denied.phtml");
  }

  
}