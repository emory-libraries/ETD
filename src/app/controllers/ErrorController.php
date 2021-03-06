<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

class ErrorController extends Etd_Controller_Action {

  public function indexAction() {
     $this->view->title = "Error";
   }
  
  public function errorAction() {
    // $this->view->backtrace = debug_backtrace();
    $errorHandler = $this->_getParam("error_handler");
    $this->view->exception = $errorHandler->exception;


    $logger = Zend_Registry::get("logger");
    $logger->err("Exception of type " . get_class($errorHandler->exception) . ": " . $errorHandler->exception->getMessage());
    // log current url and referring url if available
    $http = $this->_request->getServer('HTTPS') ? "https://" : "http://";
    $current_url = $http . $this->_request->getServer('HTTP_HOST') . $this->_request->getRequestUri();
    $referrer = $this->_request->getServer('HTTP_REFERER');
    $message = "Current url: $current_url";
    if ($referrer) $message .= "\n referring url: $referrer";
    $logger->info($message);

    $logger->debug("Exception on line " . $errorHandler->exception->getLine() .
       " in " . $errorHandler->exception->getFile());
    $logger->debug("Backtrace:\n" . $errorHandler->exception->getTraceAsString());


    switch ($errorHandler->type) {
    case "EXCEPTION_NO_ACTION":
    case "EXCEPTION_NO_CONTROLLER":
      $this->notfound();
      break;
    case "EXCEPTION_OTHER":
    default:
      // extract FedoraAccessDenied error, before defaulting to Unknown Error.
      if (get_class($errorHandler->exception) == 'FedoraAccessDenied') {        
        $role = isset($this->current_user) ? $this->current_user->getRoleId() : "guest";
        $this->_helper->access->notAllowed("view", $role, "Page");
        return false;
      }
      else {
        $this->_response->setHttpResponseCode(500);        
        $this->view->error = "Unknown Error"; 
      }
    }
  }

  public function notfoundAction() {
    $this->notfound();
    $this->_helper->viewRenderer->setScriptAction("error");
  }

  // common logic for action/controller not found or redirect to notfoundAction
  private function notfound() {
    $this->view->error = "Document not Found";
    $this->_response->setHttpResponseCode(404); // 404 Not Found
  }


  // when Fedora is not accessible, the Etd Controller forwards to this page
  public function fedoraunavailableAction() {
    $this->_response->setHttpResponseCode(500); // 500 Internal Server Error   
    $this->view->service = "Repository";
    $this->_helper->viewRenderer->setScriptAction("unavailable");
    $this->view->title = "Repository Unavailable";
  }

  public function unavailableAction() {
    $this->_response->setHttpResponseCode(500); // 500 Internal Server Error     
    $this->view->service = "Services";
    $this->view->title = "Services Unavailable";
  }

}
