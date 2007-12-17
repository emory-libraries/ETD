<?php
/** Zend_Controller_Action */

/* Require models */
require_once("models/etd.php");
require_once("helpers/ProcessPDF.php");


class SubmissionController extends Zend_Controller_Action {

  public function indexAction() {
    $this->_forward("start");
    // maybe this should be a summary/status page to check on the submission...
  }
   
  public function startAction() {
    // any other info needed here?
    $this->view->title = "Begin Submission";
  }

  // fixme: better action name? (preliminary?)
  public function processPDFAction() {
    Zend_Controller_Action_HelperBroker::addPrefix('Etd_Controller_Action_Helper');
    //     Zend_Controller_Action_HelperBroker::addPath('Controllers/Helpers', 'Helper');
    
    print "<pre>";
    print_r($_FILES);
    print "</pre>";
    
    //     $pdf = Zend_Controller_Action_HelperBroker::getStaticHelper('processPDF');
    $this->view->messages = array();
    
    $this->view->etd_info = $this->_helper->processPDF($_FILES['pdf']);

    $this->view->messages = $this->_helper->flashMessenger->getCurrentMessages();
    //$this->_helper->flashMessenger->clearMessages();
  }

  //  other actions:
  // review, submit, addfile (upload/enter info)
  // check required fields/files/etc
   
}