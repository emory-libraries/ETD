<?php

require_once("models/etd.php");
require_once("models/etd_notifier.php");

class ManageController extends Etd_Controller_Action {
  protected $requires_fedora = true;
  
   public function indexAction() {
     // forward to appropriate action based on user's role

     // site admin, grad school admin
     $this->_forward("summary");

     // for department admin, list records by department
     // for faculty, list records where they are advisor/on committee
   }

   public function summaryAction() {
     if (!$this->_helper->access->allowedOnEtd("manage")) return;
     $this->view->title = "Manage : Summary";
     $this->view->status_totals = etd::totals_by_status();
     $this->view->messages = $this->_helper->flashMessenger->getMessages();
   }
   
   public function listAction() {
     if (!$this->_helper->access->allowedOnEtd("manage")) return;
     $status = $this->_getParam("status");
     $this->view->title = "Manage : " . ucfirst($status);
     $this->view->status = $status;
     $this->view->etds = etd::findbyStatus($status);
     $this->view->show_lastaction = true;
   }

   /* review workflow (review, accept, requestChanges) */

   public function reviewAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     if (!$this->_helper->access->allowedOnEtd("review", $etd)) return false;

     $this->view->etd = $etd;
     $this->view->title = "Manage : Review record information";
     // still needs view script - same basic components as submission review,
     // links to accept or request changes
   }

   // change status on etd to reviewed
   public function acceptAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     // accept is part of review workflow
     if (!$this->_helper->access->allowedOnEtd("review", $etd)) return false;	
     
     $newstatus = "reviewed";
     $etd->setStatus($newstatus);
     
     // log event in record history 
     $etd->premis->addEvent("status change", "Record reviewed by Graduate School",
			    "success",  array("netid", $this->current_user->netid));
     
     $result = $etd->save("set status to '$newstatus'");
     
     // set flash message, forward to ...
     $this->_helper->flashMessenger->addMessage("Record status changed to <b>$newstatus</b>; record saved at $result");
     // forward to .. main admin page ?
     $this->_helper->redirector->gotoRoute(array("controller" => "manage",
						 "action" => "summary"), "", true);
   }

   // request changes to metadata (submitted etd) or document (reviewed etd)
   public function requestchangesAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     $changetype = $this->_getParam("type", "record");	  // could also be document (record=metadata)
     if (!$this->_helper->access->allowedOnEtd("request $changetype changes", $etd)) return false;  

     $newstatus = "draft";
     $etd->setStatus($newstatus);

     $this->view->title = "Manage : Request changes to $changetype";
     $this->view->etd = $etd;
     $this->view->changetype = $changetype;
     
     // log event in record history 
     $etd->premis->addEvent("status change",
			    "Changes to $changetype requested by Graduate School",
			    "success",  array("netid", $this->current_user->netid));
     
     $result = $etd->save("set status to '$newstatus'");

     if ($result)
       $this->_helper->flashMessenger->addMessage("Changes requested; record status changed to <b>$newstatus</b>");
     else
       $this->_helper->flashMessenger->addMessage("Error: could not update record status");
   }

   /* approve workflow  (approve, doapprove) */

   public function approveAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     if (!$this->_helper->access->allowedOnEtd("approve", $etd)) return false;
     
     $this->view->etd = $etd;
     $this->view->title = "Manage : Approve ETD";
   }

   public function doapproveAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     if (!$this->_helper->access->allowedOnEtd("approve", $etd)) return false;
     
     $embargo = $this->_getParam("embargo");	// duration
     
     // set status to approved
     $newstatus = "approved";
     $etd->setStatus($newstatus);
     $etd->mods->embargo = $embargo;	// save embargo duration
     
     // log event in record history 
     // log record approval
     $etd->premis->addEvent("status change",
			    "Record approved by Graduate School",	// by whom ?
			    "success",  array("netid", $this->current_user->netid));
     // log embargo duration
     // FIXME: should this not be logged if embargo is 0 days ?
     $etd->premis->addEvent("admin",
			    "Access restriction of $embargo approved",	// by whom ?
			    "success",  array("netid", $this->current_user->netid));
     
     // send approval email & log that it was sent
     $notify = new etd_notifier($etd);
     $to = $notify->approval();		// any way to do error checking? (doesn't seem to be)
     $etd->premis->addEvent("notice",
			    "Approval Notification sent by ETD system",
			    "success",  array("software", "etd system"));
     $result = $etd->save("approved");

     if ($result)
       $this->_helper->flashMessenger->addMessage("Record <b>approved</b> with an access restriction of $embargo");
     else
       $this->_helper->flashMessenger->addMessage("Error: problem approving and setting access restricton of $embargo");
     $this->_helper->flashMessenger->addMessage("Approval notification email sent to " . implode(', ', array_keys($to)));
     $this->_helper->redirector->gotoRoute(array("controller" => "manage",
						 "action" => "summary"), "", true); 
   }
   

   /* unpublish workflow (unpublish, dounpublish) */
   
   public function unpublishAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     if (!$this->_helper->access->allowedOnEtd("unpublish", $etd)) return false;
     $this->view->etd = $etd;
     $this->view->title = "Manage : Unpublish ETD";
   }

   public function dounpublishAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     if (!$this->_helper->access->allowedOnEtd("unpublish", $etd)) return false;
     
     $reason = $this->_getParam("reason", "");
     
     $newstatus = "draft";
     $etd->setStatus($newstatus);
     
     // log event in record history 
     $etd->premis->addEvent("status change",
			    "Unpublished - $reason",	// by whom ?
			    "success",  array("netid", $this->current_user->netid));
     $result = $etd->save("unpublished");
     
     $this->_helper->flashMessenger->addMessage("Record unpublished and status changed to <b>$newstatus</b>; saved at $result");
     // user information also, for email address ?
     
     $this->_helper->redirector->gotoRoute(array("controller" => "manage",
						 "action" => "summary"), "", true); 
   }

   public function embargoesAction() {
     if (!$this->_helper->access->allowedOnEtd("manage")) return false;

     $start = $this->_getParam("start", 0);
     $max = $this->_getParam("max", 10);	
     $this->view->etds = etd::findEmbargoed($start, $max, $total);
     $this->view->show_lastaction = true;
     $this->view->count = $total;
     $this->view->start = $start;
     $this->view->max = $max;

     
     
   }

   
   public function exportemailsAction() {
     if (!$this->_helper->access->allowedOnEtd("manage")) return false;
     $this->view->etds = etd::findbyStatus('approved');

     $this->_helper->layout->disableLayout();
     $this->getResponse()->setHeader('Content-Type', "text/csv");
     $this->getResponse()->setHeader('Content-Disposition',
				     'attachment; filename="ETD_approved_emails.csv"');
     // FIXME: include date generated in filename? better filename?
   }

   
}
?>