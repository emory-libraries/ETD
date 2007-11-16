<?php
/** Zend_Controller_Action */
/* Require models */
require_once("models/etd.php");

class AdminController extends Zend_Controller_Action {

  protected $_flashMessenger = null;

   public function init() {
     $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
     $this->initView();
   }
   
   public function indexAction() {

     // forward to appropriate action based on user's role

     // site admin, grad school admin
     $this->_forward("summary");

     // for department admin, list records by department
     // for faculty, list records where they are advisor/on committee
   }

   public function summaryAction() {
     $this->view->title = "Admin : Summary";
     $this->view->status_totals = etd::totals_by_status();
   }
   
   public function listAction() {
     $status = $this->_getParam("status");
     
     $this->view->title = "Admin : $status";
     $this->view->status = $status;
     $this->view->etds = etd::findbyStatus($status);
   }

   
   
   public function createAction() {
   }
   
   public function editAction() {
   }
   
   public function saveAction() {
   }
   
   public function viewAction() {
   }
   
   public function deleteAction() {
   }
}
?>