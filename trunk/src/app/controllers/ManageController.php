<?php
/** Zend_Controller_Action */
/* Require models */
require_once("models/etd.php");
require_once("models/etd_notifier.php");

class AdminController extends Zend_Controller_Action {

  protected $_flashMessenger = null;

   public function init() {
     $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
     $this->initView();

     
     $this->acl = Zend_Registry::get("acl");
     $this->user = $this->view->current_user;
   }

   // FIXME: how to share this among controllers? make it a helper?
   private function isAllowed($etd, $action) {
     $role = $etd->getUserRole($this->user);
     $allowed = $this->acl->isAllowed($role, $etd, $action);
     if (!$allowed) {
       $this->_helper->flashMessenger->addMessage("Error: " . $this->user->netid . " (role=" . $role . 
						  ") is not authorized to $action " . $etd->getResourceId());
       $this->_helper->redirector->gotoRoute(array("controller" => "auth",
						   "action" => "denied"), "", true);
     }
     return $allowed;
   }
   
   public function indexAction() {
     // forward to appropriate action based on user's role

     // site admin, grad school admin
     $this->_forward("summary");

     // for department admin, list records by department
     // for faculty, list records where they are advisor/on committee
   }

   // FIXME: should this be restricted ? what action?
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

   /* review workflow (review, accept, requestChanges) */

   public function reviewAction() {
     $etd = new etd($this->_getParam("pid"));
     $this->view->etd = $etd;
     if (!$this->isAllowed($etd, "review")) return;
     
     $this->view->title = "Review record information";
     // still needs view script - same basic components as submission review,
     // links to accept or request changes
   }

   public function acceptAction() {
     // change status on etd to reviewed
     $etd = new etd($this->_getParam("pid"));
     if (!$this->isAllowed($etd, "review")) return;  // part of review workflow
     
     $newstatus = "reviewed";
     $etd->setStatus($newstatus);
     
     // log event in record history 
     $etd->premis->addEvent("status change", "Record reviewed by Graduate School",
			    "success",  array("netid", $this->user->netid));
     
     $result = $etd->save("set status to '$newstatus'");
     
     // set flash message, forward to ...
     $this->_helper->flashMessenger->addMessage("Record status changed to <b>$newstatus</b>; record saved at $result");
     // forward to .. main admin page ?
     $this->_helper->redirector->gotoRoute(array("controller" => "admin",
						 "action" => "summary"), "", true);
   }

   public function requestchangesAction() {
     $etd = new etd($this->_getParam("pid"));
     if (!$this->isAllowed($etd, "review")) return;	// part of review workflow
     $newstatus = "draft";
     $etd->setStatus($newstatus);
     
     // log event in record history 
     $etd->premis->addEvent("status change",
			    "Changes to record requested by Graduate School",
			    "success",  array("netid", $this->user->netid));
     
     $result = $etd->save("set status to 'draft'");
     
     $this->_helper->flashMessenger->addMessage("Record status changed to <b>$newstatus</b>; saved at $result");
     // user information also, for email address ?
     
     $this->view->title = "Request changes to record information";
     $this->view->messages = $this->_helper->flashMessenger->getCurrentMessages();
   }

   /* approve workflow  (approve, doapprove) */

   public function approveAction() {
     $etd = new etd($this->_getParam("pid"));
     if (!$this->isAllowed($etd, "approve")) return;
     
     $this->view->etd = $etd;
     $this->view->title = "Approve ETD";
   }

   public function doapproveAction() {
     $etd = new etd($this->_getParam("pid"));
     if (!$this->isAllowed($etd, "approve")) return;
     
     $embargo = $this->_getParam("embargo");	// duration
     
     // set status to approved
     $newstatus = "approved";
     $etd->setStatus($newstatus);
     $etd->mods->embargo = $embargo;	// save embargo duration
     
     // log event in record history 
     // log record approval
     $etd->premis->addEvent("status change",
			    "Record approved by Graduate School",	// by whom ?
			    "success",  array("netid", $this->user->netid));
     // log embargo duration
     // FIXME: should this not be logged if embargo is 0 days ?
     $etd->premis->addEvent("admin",
			    "Access restriction of $embargo approved",	// by whom ?
			    "success",  array("netid", $this->user->netid));
     
     // send approval email & log that it was sent
     $notify = new etd_notifier($etd);
     $notify->approval();		// any way to do error checking? (doesn't seem to be)
     $etd->premis->addEvent("notice",
			    "Approval Notification sent by ETD system",
			    "success",  array("software", "etd system"));
     $result = $etd->save("approved");
     
     $this->_helper->flashMessenger->addMessage("Record approved with an embargo of $embargo; saved at $result");
     $this->_helper->flashMessenger->addMessage("Approval notification email sent");
     
     $this->_helper->redirector->gotoRoute(array("controller" => "admin",
						 "action" => "summary"), "", true); 
   }
   

   /* unpublish workflow (unpublish, doUnpublish) */
   
   public function unpublishAction() {
     $etd = new etd($this->_getParam("pid"));
     if (!$this->isAllowed($etd, "unpublish")) return;
     $this->view->etd = $etd;
     $this->view->title = "Unpublish ETD";
   }

   public function doUnpublishAction() {
     $etd = new etd($this->_getParam("pid"));
     if (!$this->isAllowed($etd, "unpublish")) return;
     
     $reason = $this->_getParam("reason", "");
     
     $newstatus = "draft";
     $etd->setStatus($newstatus);
     
     // log event in record history 
     $etd->premis->addEvent("status change",
			    "Unpublished - $reason",	// by whom ?
			    "success",  array("netid", $this->user->netid));
     $result = $etd->save("unpublished");
     
     $this->_helper->flashMessenger->addMessage("Record unpublished and status changed to <b>$newstatus</b>; saved at $result");
     // user information also, for email address ?
     
     $this->_helper->redirector->gotoRoute(array("controller" => "admin",
						 "action" => "summary"), "", true); 
   }
   
}
?>