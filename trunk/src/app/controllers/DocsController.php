<?php

class DocsController extends Etd_Controller_Action {

  public function init() {
    parent::init();

    // all pages in this section should have the print view link
    $this->view->printable = true;
  }

  public function indexAction() {
    $this->view->title = "ETD Documents";
  }
  
  public function aboutAction() {
    $this->view->title = "About Emory's ETD Repository";
  }
  public function faqAction() {
    $this->view->title = "Frequently Asked Questions";
  }
  public function ipAction() {
    $this->view->title = "Intellectual Property";
  }
  public function policiesAction() {
    $this->view->title = "Policies & Procedures";
  }
  public function boundcopiesAction() {
    $this->view->title = "Bound Copies";
  }
  public function instructionsAction() {
    $this->view->title = "Submission Instructions";
  }
     

}
