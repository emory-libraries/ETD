<?php

class Etd_Controller_Action_Helper_GetFromFedora extends Zend_Controller_Action_Helper_Abstract {

  public function direct($param, $type) {
    return $this->find_or_error($param, $type);
  }

  
  public function find_or_error($param, $type) {
    $flashMessenger = $this->_actionController->getHelper("FlashMessenger");
    $redirector = $this->_actionController->getHelper("Redirector");

    $request = $this->_actionController->getRequest();
    $id = $request->getParam($param, null);

    if (is_null($id)) {
      $flashMessenger->addMessage("Error: No record specified for $type");
      $redirector->gotoRoute(array("controller" => "error"), "", true);
      return null;
    }

    try {
      $object = new $type($id);
    } catch (FedoraObjectNotFound $e) {
      $message = "Error: Record not found";
      if ($this->_actionController->view->site_mode != "production")
	$message .= " (message from Fedora: <b>" . $e->getMessage() . "</b>)";

      $flashMessenger->addMessage($message);
      $redirector->gotoRoute(array("controller" => "error"), "", true);
      return null;
    } catch (FedoraAccessDenied $e) {
      $message = "Error: access denied to $id";
      if ($this->_actionController->view->site_mode != "production")
	$message .= " (message from Fedora: <b>" . $e->getMessage() . "</b>)";
      $flashMessenger->addMessage($message);
      $redirector->gotoRoute(array("controller" => "auth", "action" => "denied"), "", true);
	      
      return null;
    } catch (FoxmlException $e) {
      // another access denied, but at a different level ...
      $flashMessenger->addMessage("Error: " . $e->getMessage());
      $redirector->gotoRoute(array("controller" => "auth", "action" => "denied"), "", true);
      return null;
    } 

    // success
    return $object;

  }
  
}

