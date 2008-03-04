<?php

class ErrorController extends Etd_Controller_Action {

  public function indexAction() {
     $this->view->title = "Error";
   }
  
  public function errorAction() {
    $this->view->backtrace = debug_backtrace();
  }

}
