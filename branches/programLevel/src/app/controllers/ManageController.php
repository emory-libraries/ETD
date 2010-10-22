<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

require_once("models/etd.php");
require_once("models/etd_notifier.php");

class ManageController extends Etd_Controller_Action {
  protected $requires_fedora = true;
  protected $schools_cfg;
  protected $school;
  
   public function indexAction() {
     // forward to appropriate action based on user's role

     // site admin, grad school admin
     $this->_forward("summary");

     // for department admin, list records by department
     // for faculty, list records where they are on the committee as chair or member
   }



   // generate filter needed (if any) based on type of administrator
   protected function getAdminFilter() {

     $filter = null;

     // retrieve multi-school configuration from registry
     $this->schools_cfg = Zend_Registry::get("schools-config");
     
     // if user ir a school-specific admin, determine which school
     if ($pos = strpos($this->current_user->role, " admin")) {
       $admin_type = substr($this->current_user->role, 0, $pos);
       // find the school config that current user is admin for
       $this->school = $this->schools_cfg->getSchoolByAclId($admin_type);
       if ($this->school) {
           // get fedora collection id, then construct query by collection id
        $collection = $this->school->fedora_collection;
        if ($collection){
           $filter .= 'collection:"' . $collection . '"';

           //TODO: Special case for rolins because admins determined by program and configured in mySql DB
          //       All schools should be migrated to use the DB instead of the config files for admin config
           if($collection == $this->schools_cfg->rollins->fedora_collection){
                $programs = $this->getAdminPrograms();

                // if user has programs associated in etd_admins table, add them to filter
                if($programs){
                    //build each program and subfield part in the original array
                    foreach($programs as &$program){
                        $program = "program_id: \"$program\" OR subfield_id: \"$program\"";
                    }
                    unset($program);
                    $filter .= " AND " . "(" . join(" OR ", $programs) . ")";
                }



           }
        }
       }
     }

     // return filter if one was created
     return $filter;
   }

   /*
    * Selects the program codes for an admin user from the mysql DB
    * @returns Array - list of programs configured for the current user
    */
   protected function getAdminPrograms() {
       $etd_db = Zend_Registry::get("etd-db"); //etd util DB
       $programs = array();

       $results = $etd_db->select()
       ->from(array("etd_admins"), array('programid'))
       ->where("netid= UPPER(?)", $this->current_user->netid)->where("schoolid = UPPER(?)", $this->school->db_id);
       $results = $etd_db->fetchAssoc($results);

       foreach($results as $row){
           $programs[] = strtolower($row['programid']);
       }
       return $programs;
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
     
     $etdSet = new EtdSet($options, null, 'find');
     $this->view->etdSet = $etdSet;

     // Pagination Code
    // TODO:  find a way to refactor this code that is repeated in this controller.
    $paginator = new Zend_Paginator($etdSet);
    $paginator->setItemCountPerPage(10);

    if ($this->_hasParam('page')) $paginator->setCurrentPageNumber($this->_getParam('page'));
    $this->view->paginator = $paginator;


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
     $changetype = $this->_getParam("type", "record");    // could also be document (record=metadata)
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
     
     $embargo = $this->_getParam("embargo");  // duration
     
     // set status to approved
     $newstatus = "approved";
     $etd->setStatus($newstatus);
     $etd->mods->embargo = $embargo;  // save embargo duration
     
     // log event in record history 
     // log record approval
     $etd->premis->addEvent("status change",
          "Record approved by " . $this->current_user->getGenericAgent(),
          "success",  array("netid", $this->current_user->netid));
     // log embargo duration
     // FIXME: should this not be logged if embargo is 0 days ?
     $etd->premis->addEvent("admin",
          "Access restriction of $embargo approved",  // by whom ?
          "success",  array("netid", $this->current_user->netid));
     
     // send approval email & log that it was sent
     $notify = new etd_notifier($etd);
     $to = $notify->approval();   // any way to do error checking? (doesn't seem to be)
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
          "Marked inactive - $reason",  // by whom ?
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


      /* re-activation for inactive records (reactivate, doReactivate) */
   
   public function reactivateAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     if (!$this->_helper->access->allowedOnEtd("reactivate", $etd)) return false;
     $this->view->etd = $etd;
     // find previous status & store once
     $this->view->previous_status = $etd->previousStatus();

     $this->view->status_opts = array();
     // generate list of possible etd statuses for use in select
     foreach ($etd->rels_ext->status_list as $status)
       $this->view->status_opts[$status] = $status;

     $this->view->title = "Manage : Reactivate inactive ETD";
   }

   public function doReactivateAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     if (!$this->_helper->access->allowedOnEtd("reactivate", $etd)) return false;
     
     $reason = $this->_getParam("reason", "");
     $newstatus = $this->_getParam("status");
     $etd->setStatus($newstatus);
     
     // log event in record history 
     $etd->premis->addEvent("status change",
          "Reactivated - $reason",  // by whom ?
          "success",  array("netid", $this->current_user->netid));
     $result = $etd->save("reactivated");
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

   public function reviseembargoAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     if (!$this->_helper->access->allowedOnEtd("revise embargo", $etd)) return false;
   
     $this->view->etd = $etd;

     $this->view->title = "Manage : Revise embargo";
   }
   
   public function updateembargoAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     if (!$this->_helper->access->allowedOnEtd("revise embargo", $etd)) return false;
     $newdate= $this->_getParam("newdate", "");
     $message = "Updating the embargo"; //$this->_getParam("newdate", "");
     $messag = $this->_getParam("message", "");
    
     try
     {
      $etd->updateEmbargo($newdate, $message);
     }
     catch(Exception $e)
     {
  $this->_helper->flashMessenger->addMessage("Error: " . $e->getMessage()); 
      $this->_helper->redirector->gotoRoute(array("controller" => "manage",
             "action" => "reviseembargo")); 
  return;
     }

     $result = $etd->save("embargo ending date updated");

     if ($result) {
       $this->_helper->flashMessenger->addMessage("Embargo ending dated changed to <b>$newdate</b>");
       // user information also, for email address ?
       $this->logger->info("Updated etd " . $etd->pid . " at $result - set embargo ending date to $newdate");
     } else {
       $this->logger->err("Could not save etd " . $etd->pid . " - attempting to update embargo ending date to $newdate");
       $this->_helper->flashMessenger->addMessage("Error: Could not update embargo ending date to<b>$newdate</b>");
     }
     
     $this->_helper->redirector->gotoRoute(array("controller" => "manage",
             "action" => "summary"), "", true); 
   }

   public function embargoesAction() {
     if (!$this->_helper->access->allowedOnEtd("manage")) return false;

     $options = $this->getFilterOptions();
     $etdSet = new EtdSet($options, null, 'findEmbargoed');
     $this->view->title = "Expiring Embargoes";
     $this->view->etdSet = $etdSet;

     // Pagination Code
    // TODO:  find a way to refactor this code that is repeated in this controller.
    $paginator = new Zend_Paginator($etdSet);
    $paginator->setItemCountPerPage(10);

    if ($this->_hasParam('page')) $paginator->setCurrentPageNumber($this->_getParam('page'));
    $this->view->paginator = $paginator;
    
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

   public function embargoReportAction() {
     $this->view->title = "Embargo Trends";

     $solr = Zend_Registry::get('solr');
     $solr->clearFacets();
     
     $result = $solr->browse("dateIssued"); // if desired, sort by most recent and return last 3 (?) only
     $semesters = $result->facets->dateIssued;

     $data = array();
     foreach ($semesters as $sem => $count) {
       $solr->addFacets(array("embargo_duration"));
       $solr->setFacetLimit(-1);  // no limit
       $solr->setFacetMinCount(0);  // minimum one match
       $result = $solr->query("dateIssued:$sem", 0, 0); // find facets on all records, return none

       // FIXME: convert semester date to human-readable format, e.g. spring/summer/fall 2007 
       $data[$sem] = $result->facets->embargo_duration;
       // sort durations from low to high 
       uksort($data[$sem], "sort_embargoes");

       // FIXME: segment by humanities / social sciences / natural sciences (how?)
     }

     $this->view->data = $data;
   }

   public function clearRssCacheAction() {
    //List of caches to clear
    $cacheNames = array("news", "docs");

    foreach($cacheNames as $name){
       //Create cache object with enough info to identify the correct files
       $cache = Zend_Cache::factory('Core', 'File', array(), array('cache_dir' => '/tmp/', "file_name_prefix" => "ETD_".$name."_cache"));
       $cache->clean(Zend_Cache::CLEANING_MODE_ALL);
    }

    //Go back to the summary page
    $this->_forward("summary");
  }
   
}
?>
