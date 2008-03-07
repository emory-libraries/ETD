<?php

class ErrorController extends Etd_Controller_Action {

  public function indexAction() {
     $this->view->title = "Error";
   }
  
  public function errorAction() {
    $this->view->backtrace = debug_backtrace();
  }


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
