<?php

class ErrorController extends Etd_Controller_Action {

  public function indexAction() {
     $this->view->title = "Error";
   }
  
  public function errorAction() {
    // $this->view->backtrace = debug_backtrace();
    $errorHandler = $this->_getParam("error_handler");
    $this->view->exception = $errorHandler->exception;


    $logger = Zend_Registry::get("logger");
    $logger->err("Exception: " . $errorHandler->exception->getMessage());
    $logger->debug("Exception on line " . $errorHandler->exception->getLine() .
		  " in " . $errorHandler->exception->getFile());
    $logger->debug("Backtrace: " . $errorHandler->exception->getTraceAsString());
    

    switch ($errorHandler->type) {
    case "EXCEPTION_NO_ACTION":
    case "EXCEPTION_NO_CONTROLLER":
      $this->notfound();
      break;
    case "EXCEPTION_OTHER":
    default:
      $this->view->error = "Unknown Error"; 
    }
  }

  public function notfoundAction() {
    $this->notfound();
    $this->_helper->viewRenderer->setScriptAction("error");
  }

  // common logic for action/controller not found or redirect to notfoundAction
  private function notfound() {
    $this->view->error = "Document not Found";
    $this->_response->setHttpResponseCode(404);	// 404 Not Found
  }


  // when Fedora is not accessible, the Etd Controller forwards to this page
  public function fedoraunavailableAction() {
    $this->view->service = "Repository";
    $this->_helper->viewRenderer->setScriptAction("unavailable");
    $this->view->title = "Repository Unavailable";
  }

  public function unavailableAction() {
    $this->view->service = "Services";
    $this->view->title = "Services Unavailable";
  }

}
