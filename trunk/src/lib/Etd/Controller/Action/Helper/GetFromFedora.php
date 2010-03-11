<?php
/**
 * Retrieve an etd, etdfile, or authorinfo object from Fedora, and handle common errors
 * - if object is not found, redirects to document not found error page
 * - if access denied or not authorized, redirects to etd access denied page
 *
 * @category Etd
 * @package Etd_Controllers
 * @subpackage Etd_Controller_Helpers
 */

require_once("etd.php");


class Etd_Controller_Action_Helper_GetFromFedora extends Zend_Controller_Action_Helper_Abstract {

  /**
   * shortcut to find_or_error (default helper action)
   * @see Etd_Controller_Action_Helper_GetFromFedora::find_or_error()
   */
  public function direct($param, $type) {
    return $this->find_or_error($param, $type);
  }

  /**
   * find an object by a specified id
   * @param string $id object pid
   * @param string $type object type (passed to EtdFactory::init)
   * @return etd|etd_file|user
   */
  public function findById($id, $type) { 
   return $this->handle_errors($id, $type);
  }

  /**
   * find an object by an id stored in a controller parameter
   * @param string $param controller parameter name that has the id value
   * @param string $type type of object
   * @return etd|etd_file|user
   */
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
      switch($type) {
      case "etd":  $object = new etd($id); break;
      case "etd_file":  $object = new etd_file($id); break;
      case "user": $object = new user($id); break;
      }
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

