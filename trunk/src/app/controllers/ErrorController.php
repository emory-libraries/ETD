<?php

class ErrorController extends Etd_Controller_Action {

  public function indexAction() {
     $this->view->title = "Error";
   }
  
  public function errorAction() {
    // $this->view->backtrace = debug_backtrace();
    $errorHandler = $this->_getParam("error_handler");
    $this->view->exception = $errorHandler->exception;

    switch ($errorHandler->type) {
    case "EXCEPTION_NO_ACTION":
    case "EXCEPTION_NO_CONTROLLER":
      $this->view->error = "Document not Found"; break;
    case "EXCEPTION_OTHER":
    default:
      $this->view->error = "Unknown Error"; break;
    }
  }


  // when Fedora is not accessible, the Etd Controller forwards to this pagemessage
  public function fedoraUnavailableAction() {
    $this->view->service = "Repository";
    $this->_helper->viewRenderer->setScriptAction("unavailable");
    $this->view->title = "Repository Unavailable";
  }

  public function unavailableAction() {
    $this->view->service = "Services";
    $this->view->title = "Services Unavailable";
  }

}
