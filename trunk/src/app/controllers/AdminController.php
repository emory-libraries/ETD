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
     $this->view->messages = $this->_helper->flashMessenger->getMessages();
   }
   
   public function listAction() {
     $status = $this->_getParam("status");
     
     $this->view->title = "Admin : $status";
     $this->view->status = $status;
     $this->view->etds = etd::findbyStatus($status);
   }

   public function reviewAction() {
     $etd = new etd($this->_getParam("pid"));
     $this->view->etd = $etd;
     $this->view->title = "Review record information";
     // fixme: should probably also find and include user object
     //	       (review contact information)

     // still needs view script - same basic components as submission review,
     // links to accept or request changes
   }

   public function acceptAction() {
     // change status on etd to reviewed
     $etd = new etd($this->_getParam("pid"));
     $newstatus = "reviewed";
     $etd->rels_ext->status = $newstatus;

     // log event in record history (fixme: better way of getting user)
     $auth = Zend_Auth::getInstance();
     if ($auth->hasIdentity()) $identity = $auth->getIdentity();
     $etd->premis->addEvent("status change", "Record reviewed by Graduate School",
			    "success",  array("netid", $identity));
     print htmlentities($etd->premis->saveXML());

     
     $result = $etd->save("set status to '$newstatus'");
     
     // set flash message, forward to ...
     $this->_helper->flashMessenger->addMessage("Record status changed to <b>$newstatus</b>; record saved at $result");
     // forward to .. main admin page ?
     $this->_helper->redirector->gotoRoute(array("controller" => "admin",
						 "action" => "summary"), "", true); 
   }

   public function requestChangesAction() {
     $etd = new etd($this->_getParam("pid"));
     $newstatus = "draft";
     $etd->rels_ext->status = $newstatus;

     // log event in record history (fixme: better way of getting user)
     $auth = Zend_Auth::getInstance();
     if ($auth->hasIdentity()) $identity = $auth->getIdentity();
     $etd->premis->addEvent("status change",
			    "Changes to record requested by Graduate School",
			    "success",  array("netid", $identity));
     
     $result = $etd->save("set status to 'draft'");


     
     $this->_helper->flashMessenger->addMessage("Record status changed to <b>$newstatus</b>; saved at $result");
     // user information also, for email address ?
     
     $this->view->title = "Request changes to record information";
     $this->view->messages = $this->_helper->flashMessenger->getCurrentMessages();
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