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
     // for faculty, list records where they are on the committee as chair or member
   }



   // generate filter needed (if any) based on type of administrator
   protected function getAdminFilter() {
     switch ($this->current_user->role) {
       // FIXME: should use degree level in degree config file to generate this list
     case "honors admin":
       return "degree_name:(BA or BS or BBA)";
     case "grad admin":
       return "degree_name:(PhD or MA or MS)";
     default:
       return null;
	
     }
   }


   public function summaryAction() {
     if (!$this->_helper->access->allowedOnEtd("manage")) return;
     $this->view->title = "Manage : Summary";
     $etdset = new EtdSet();

     $filter = $this->getAdminFilter();
     $this->view->status_totals = $etdset->totals_by_status($filter);
     $this->view->messages = $this->_helper->flashMessenger->getMessages();

   }

   // list records by status; uses browse list template for paging & facet functionality
   public function listAction() {
     if (!$this->_helper->access->allowedOnEtd("manage")) return;

     // pick up common parameters: paging (start/max), sort, facets (including status) 
     $options = $this->getFilterOptions();
     $options["return_type"] = "solrEtd";

     // optionally limit to undergrad or grad records only based on user's role
     $filter = $this->getAdminFilter();
     if ($filter) $options["query"] = $filter;
     
     $etdSet = new EtdSet();
     $etdSet->find($options);
     $this->view->etdSet = $etdSet;
     // should always have a status parameter
     $status = $this->_getParam("status");
     $list_title = ucfirst($status) . " records";

     $this->view->title = "Manage : " . $list_title;;
     $this->view->list_title = $list_title;

     $this->view->status = $status;
     // display administrative info in short-record view
     $this->view->show_status = true;
     // using solrEtd; last event in history not available from solr
     //     $this->view->show_lastaction = true;

     $this->view->sort_fields[] = "modified";

     // don't include status in list of filters that can be removed
     unset($this->view->filters["status"]);
     // do include status in any facet links
     $this->view->url_params["status"] = $status;

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
     $etd->premis->addEvent("status change", "Record reviewed by " .
			    $this->current_user->getGenericAgent(),
			    "success",  array("netid", $this->current_user->netid));
     
     $result = $etd->save("set status to '$newstatus'");
     if ($result) {
       $this->logger->info("Saved etd status change to '$newstatus' on " . $etd->pid . " at $result");
       // set flash message
       $this->_helper->flashMessenger->addMessage("Record status changed to <b>$newstatus</b>; record saved at $result");
     } else {
       $this->logger->info("Could not save etd status change to '$newstatus' on " . $etd->pid);
       $this->_helper->flashMessenger->addMessage("Error: Could not set record status to <b>$newstatus</b>");
     }
     
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
			    "Changes to $changetype requested by " .
			    $this->current_user->getGenericAgent(),
			    "success",  array("netid", $this->current_user->netid));
     
     $result = $etd->save("set status to '$newstatus'");

     if ($result) {
       $this->_helper->flashMessenger->addMessage("Changes requested; record status changed to <b>$newstatus</b>");
       $this->logger->info("Updated etd " . $etd->pid . " - changes requested, status set to $newstatus at $result");
     } else {
       $this->_helper->flashMessenger->addMessage("Error: could not update record status");
       $this->logger->err("Could not save etd " . $etd->pid . " - changes requested, status change to $newstatus");
     }
   }

   // send an email with more information about changes requested
   public function changesEmailAction() {
     // send email about changes requested
     $etd = $this->_helper->getFromFedora("pid", "etd");
     $subject = $this->_getParam("subject");
     $content = $this->_getParam("content");

     $notify = new etd_notifier($etd);
     $to = $notify->request_changes($subject, $content);

     $this->_helper->flashMessenger->addMessage("Email sent to <b>" . implode(', ', array_keys($to)) . "</b>");

     // forward to main admin page 
     $this->_helper->redirector->gotoRoute(array("controller" => "manage",
						 "action" => "summary"), "", true);
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
			    "Record approved by " . $this->current_user->getGenericAgent(),
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

     if ($result) {
       $this->_helper->flashMessenger->addMessage("Record <b>approved</b> with an access restriction of $embargo");
       $this->logger->info("Updated etd " . $etd->pid . " - record approved and embargo set to $embargo");
     } else {
       $this->_helper->flashMessenger->addMessage("Error: problem approving and setting access restricton of $embargo");
       $this->logger->err("Could not update etd " . $etd->pid . " - attempting to approve and set embargo to $embargo");
     }
     
     $this->_helper->flashMessenger->addMessage("Approval notification email sent to " . implode(', ', array_keys($to)));
     $this->_helper->redirector->gotoRoute(array("controller" => "manage",
						 "action" => "summary"), "", true); 
   }
   

   /* inactivation workflow (inactivate, markInactive) */
   
   public function inactivateAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     if (!$this->_helper->access->allowedOnEtd("inactivate", $etd)) return false;
     $this->view->etd = $etd;
     $this->view->title = "Manage : Mark ETD as Inactive";
   }

   public function markInactiveAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     if (!$this->_helper->access->allowedOnEtd("inactivate", $etd)) return false;
     
     $reason = $this->_getParam("reason", "");
     
     $newstatus = "inactive";
     $etd->setStatus($newstatus);
     
     // log event in record history 
     $etd->premis->addEvent("status change",
			    "Marked inactive - $reason",	// by whom ?
			    "success",  array("netid", $this->current_user->netid));
     $result = $etd->save("marked inactivate");
     if ($result) {
       $this->_helper->flashMessenger->addMessage("Record status changed to <b>$newstatus</b>; saved at $result");
       // user information also, for email address ?
       $this->logger->info("Updated etd " . $etd->pid . " at $result - set status to $newstatus");
     } else {
       $this->logger->err("Could not save etd " . $etd->pid . " - attempting to change status to $newstatus");
       $this->_helper->flashMessenger->addMessage("Error: Could not set record status to <b>$newstatus</b>");
     }
     
     $this->_helper->redirector->gotoRoute(array("controller" => "manage",
						 "action" => "summary"), "", true); 
   }

   public function embargoesAction() {
     if (!$this->_helper->access->allowedOnEtd("manage")) return false;

     $options = $this->getFilterOptions();
     $etdSet = new EtdSet();
     $etdSet->findEmbargoed($options);
     $this->view->title = "Expiring Embargoes";
     $this->view->etdSet = $etdSet;
     $this->view->show_lastaction = true;
     // don't allow re-sorting by other fields (doesn't make sense)
     $this->view->sort = null;
   }


   public function viewLogAction() {
     if (!isset($this->current_user) || !$this->acl->isAllowed($this->current_user, "log", "view")) {
       $role = isset($this->current_user) ? $this->current_user->getRoleId() : "guest";
       $this->_helper->access->notAllowed("view", $role, "log");
       return false;
     }
     $priority = $this->_getParam("priority", Zend_Log::DEBUG);
     $this->view->priority = $priority;
     $filter['priority'] = $priority;
     // optionally filter log entries by username
     $username = $this->_getParam("username");
     if ($username)  {
       $filter['username'] = $username;
       $this->view->username = $username;
     }

     $config = Zend_Registry::get('config');
     $log = new Emory_Log_Reader_Xml($config->logfile);

     $this->view->title = "Error Log";
     $this->view->log = $log;
     $this->view->logEntries = $log->getEntries($filter);
   }

   public function reportAction() {
     $this->view->title = "Reports";
     
     $solr = Zend_Registry::get('solr');
     $solr->clearFacets();
     $solr->addFacets(array("program_facet", "year", "dateIssued", "embargo_duration", "num_pages",
			    "degree_level", "degree_name"));
     // would be nice to also have: degree (level?), embargo duration
     $solr->setFacetLimit(-1);	// no limit
     $solr->setFacetMinCount(1);	// minimum one match
     $result = $solr->query("*:*", 0, 0);	// find facets on all records, return none
     $this->view->facets = $result->facets;
     uksort($this->view->facets->embargo_duration, "sort_embargoes");

     // how to do page counts by range?
     for ($i = 0; $i < 1000; $i += 100) {
       $range = sprintf("%05d TO %05d", $i, $i +100);
       if ($i == 0) $label = ">100";
       else $label = $i . " - " . ($i + 100);
       $response = $solr->query("num_pages:[$range]", 0, 0);
       $pages[$label] = $response->numFound;
     }
     $response = $solr->query("num_pages:[01000 TO *]", 0, 0);
     $pages[">1000"] = $response->numFound;
     
     /*     $response = $solr->query("num_pages:[00000 TO 00100]");
     $pages[">100"] = $response->numFound;
     $response = $solr->query("num_pages:[00100 TO 00200]");
     $pages["100-200"] = $response->numFound;
     $response = $solr->query("num_pages:[00200 TO 00300]");
     $pages["200-300"] = $response->numFound;
     */
     $this->view->pages = $pages;
     
   }

   public function embargoReportAction() {
     $this->view->title = "Embargo Trends";

     $solr = Zend_Registry::get('solr');
     $solr->clearFacets();
     
     $result = $solr->browse("dateIssued");	// if desired, sort by most recent and return last 3 (?) only
     $semesters = $result->facets->dateIssued;

     $data = array();
     foreach ($semesters as $sem => $count) {
       $solr->addFacets(array("embargo_duration"));
       $solr->setFacetLimit(-1);	// no limit
       $solr->setFacetMinCount(0);	// minimum one match
       $result = $solr->query("dateIssued:$sem", 0, 0);	// find facets on all records, return none

       // FIXME: convert semester date to human-readable format, e.g. spring/summer/fall 2007 
       $data[$sem] = $result->facets->embargo_duration;
       // sort durations from low to high 
       uksort($data[$sem], "sort_embargoes");

       // FIXME: segment by humanities / social sciences / natural sciences (how?)
     }

     $this->view->data = $data;
   }

   

   
   public function exportemailsAction() {
     if (!$this->_helper->access->allowedOnEtd("manage")) return false;
     $etdSet = new EtdSet();
     // FIXME: how do we make sure to get *all* the records ?
     $etdSet->find(array("AND" => array("status" => "approved"), "start" => 0, "max" => 200));
     $this->view->etdSet = $etdSet;

     $this->_helper->layout->disableLayout();
     // add date to the suggested output filename
     $filename = "ETD_approved_emails_" . date("Y-m-d") . ".csv";
     $this->getResponse()->setHeader('Content-Type', "text/csv");
     $this->getResponse()->setHeader('Content-Disposition',
				     'attachment; filename="' . $filename . '"');
     // date/time this output was generated to be included inside the file
     $this->view->date = date("Y-m-d H:i:s");
   }

   
}


// sort embargo durations logically (days/months/years)
function sort_embargoes($a, $b) {
  // check if either is blank
  if (trim($a) == "") return -1; // a is less than b
  if (trim($b) == "") return 1; // a is greater than b

  // split into number & time unit
  list($a_num, $a_time) = explode(' ', $a);
  list($b_num, $b_time) = explode(' ', $b);

  // convert time unit to numeric for easy comparison
  // days = 1, months = 2, years = 3
  foreach (array($a_time, $b_time) as $time) {
    switch ($time) {
    case "years":
    case "year":
      $t = 3; break;
    case "months":
      $t = 2; break;
    case "days":
      $t = 1; break;
    }
  }

  if ($a_time == $b_time) { 
    // same time duration - compare by numbers only
    if ($a_num == $b_num) return 0;
    return ($a_num < $b_num) ? -1 : 1;
  } else {
    // otherwise, compare by time unit only
    return ($a_time < $b_time) ? -1 : 1;
  }
}
?>