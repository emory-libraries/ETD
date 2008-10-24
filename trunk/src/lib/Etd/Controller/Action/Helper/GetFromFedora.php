<?php

class Etd_Controller_Action_Helper_GetFromFedora extends Zend_Controller_Action_Helper_Abstract {

  public function direct($param, $type) {
    return $this->find_or_error($param, $type);
  }

  // find by a specified id
  public function findById($id, $type) { 
   return $this->handle_errors($id, $type);
  }

  // find by an id stored in a controller parameter
  public function find_or_error($param, $type) {
    $request = $this->_actionController->getRequest();
    $id = $request->getParam($param, null);
    
    return $this->handle_errors($id, $type);
  }

  private function handle_errors($id, $type) {
    $flashMessenger = $this->_actionController->getHelper("FlashMessenger");
    $redirector = $this->_actionController->getHelper("Redirector");

    $denied = false;
    
    if (is_null($id)) {
      $flashMessenger->addMessage("Error: No record specified for $type");
      $redirector->gotoRoute(array("controller" => "error"), "", true);
      return null;
    }

    try {
      $object = new $type($id);
    } catch (FedoraObjectNotFound $e) {
      $message = "Record not found";
      $log_message = $message . " - " . $e->getMessage();
      if ($this->_actionController->view->env != "production")
	$message .= " (message from Fedora: <b>" . $e->getMessage() . "</b>)";
      $flashMessenger->addMessage($message);
      $redirector->gotoRoute(array("controller" => "error", "action" => "notfound"), "", true);
      return null;
    } catch (FedoraAccessDenied $e) {
      $denied = true;
      $message = "access denied to $id";
      $log_message = $message . " - " . $e->getMessage();
      if ($this->_actionController->view->env != "production")
	$message .= " (message from Fedora: <b>" . $e->getMessage() . "</b>)";
    } catch (FedoraNotAuthorized $e) {
      $denied = true;
      $message = "not authorized to view $id";
      $log_message = $message . " - " . $e->getMessage();
      if ($this->_actionController->view->env != "production")
	$message .= " (message from Fedora: <b>" . $e->getMessage() . "</b>)";
    } catch (FoxmlException $e) {
      // another access denied, but at a different level ...
      $denied = true;
      $message = $e->getMessage();
      $log_message = $message;
    }

    // if resources is denied, NOT redirecting - that way logging in can reload
    // the denied page and user may have access
    if ($denied) {
      // set HTTP response code correctly
      $response = $this->_actionController->getResponse();
      $response->setHttpResponseCode(403);	// Forbidden

      $viewRenderer = $this->_actionController->getHelper("viewRenderer");
      $viewRenderer->setNoRender();		// don't render normally
      // instead display an access denied page - still at the denied url
      $viewRenderer->view->title = "Access Denied";
      $viewRenderer->view->deny_message = "Error: " . $message;
      print $viewRenderer->renderScript("auth/denied.phtml");


      // log denial info
      $logger = Zend_Registry::get('logger');
      $logger->warn($log_message);
      
      return null;
    }

    // success
    return $object;
  }
  
}

