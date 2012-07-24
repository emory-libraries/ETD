<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

require_once("models/etd.php");
require_once("models/etd_notifier.php");
require_once("models/programs.php");

class SubmissionController extends Etd_Controller_Action {
  protected $requires_fedora = true;
  
  public function preDispatch() {
    // suppress sidebar search & browse navigation for all submission actions,
    // for any user
    $this->view->suppress_sidenav_browse = true;
  }

  public function indexAction() {
    $this->_forward("start");
    // maybe this should be a summary/status page to check on the submission...
  }
   
  /**
   * Start page for creating a new ETD submission.  Logged in students
   * must answer screening questions and then 
   *
   */
  public function startAction() {
    if (!$this->_helper->access->allowedOnEtd("create")) return false;
    // any other info needed here?
    $this->view->title = "Begin Submission";

    // using jquery and jquery form validation 
    $this->view->extra_scripts = array(
         "//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js",
         "//ajax.aspnetcdn.com/ajax/jquery.validate/1.9/jquery.validate.js",
    );

    $this->view->yes_no = array('yes' => "Yes", 'no' => "No");

    // on POST, process form data
    if ($this->getRequest()->isPost()) {
      // Get posted data for validation
      $answers["copyright"] = $this->_getParam("copyright", null);
      $answers["copyright_permission"] = $this->_getParam("copyright_permission", null);
      $answers["patent"] = $this->_getParam("patent", null);
      $answers["embargo"] = $this->_getParam("embargo", null);
      $answers["embargo_abs_toc"] = $this->_getParam("embargo_abs_toc", null);
      $etd_info['title'] = $answers['title'] = $this->_getParam('etd_title', null);
      $this->view->answers = $answers;

      // if form is not valid, stop processing and re-display form with errors
      $form_errors = $this->validateQuestions($answers);
      if ($form_errors) {
        $this->view->form_errors = $form_errors;
        return;
      } 

      $etd =  $this->initialize_etd($etd_info);
      $errtype = "";
      $pid = $this->_helper->ingestOrError($etd,
           "creating preliminary record with user-entered title",
           "etd record", &$errtype);

      if ($pid == false) {
        // any special handling based on error type
        switch ($errtype) {
        case "FedoraObjectNotValid":
        case "FedoraObjectNotFound":
          $this->view->errors[] = "Could not create record.";
          $this->_helper->flashMessenger->addMessage("Error saving record");
          if ($this->debug) $this->view->xml = $etd->saveXML();
          break;
        }
      } else {  // valid pid was returned
        $this->logger->info("Created new etd record with pid $pid");
        if ($this->debug) $this->_helper->flashMessenger->addMessage("Saved etd as $pid");
    
        // retrieve a fresh copy of the record from fedora for additional updates
        $etd = $this->_helper->getFromFedora->findById($pid, "etd");
        
        // FIXME: can premis events be added before ingest ? 

        // could use fullname as agent id, but netid seems more useful
        $etd->premis->addEvent("ingest", "Record created by " .
             $this->current_user->fullname, "success",
             array("netid", $this->current_user->netid));

        //Save info for copyright questions
        $eventMsg = "The author " . ($answers["copyright"] == 'yes' ? "has" : "has not") . " submitted copyrighted material";
        if ($answers["copyright"] == 'yes'){
            $eventMsg .= " and " . ($answers["copyright_permission"] == 'yes' ? "has" : "has not") . " obtained permission to include this copyrighted material";
        }
        $eventMsg .= ".";
        $etd->premis->addEvent("admin", $eventMsg, "success",
             array("netid", $this->current_user->netid));
               
        // Save info for patent questions
        $eventMsg = "The author " . ($answers["patent"] == 'yes' ? "has" : "has not") . " submitted patented material.";
        $etd->premis->addEvent("admin", $eventMsg, "success",
             array("netid", $this->current_user->netid));

        // send email if patent screening question is yes
        if ($answers["patent"] == "yes") {
          $notice = new etd_notifier($etd);
          $notice->patent_concerns();

          // Document that patent email was sent in record history
          $eventMsg = "Email sent due to patent concerns.";
          $etd->premis->addEvent("admin", $eventMsg, "success",
              array("software", "etd system"));   // email sent by system, not current user
          $this->_helper->flashMessenger->addMessage("An e-mail has been sent to notify "
            . "the appropriate person of potential patent concerns.");
        }

        $message = "added history events";
        $result = $etd->save($message);
        if ($result)
          $this->logger->info("Updated etd " . $etd->pid . " at $result ($message)");
        else
          $this->logger->err("Error updating etd " . $etd->pid . " ($message)");

        // redirect to record view page so details can be added to the draft record
        $this->_helper->redirector->gotoRoute(array("controller" => "view",
                              "action" => "record", "pid" => $etd->pid));

      }
      
    } // end POST handling

    // on GET, display form

  }

  /**
   * Validate screening questions on submission start page
   * 
   * @return array with any missing required fields
   */
  public function validateQuestions($answers){
      $errors = array();

      // answer to copyright is required
      if ($answers["copyright"] == null) {
         $errors[] = 'copyright';
      } else if ($answers["copyright"] == 'yes' && 
          $answers["copyright_permission"] == null) {
         // if answer to copyright is yes, answer about permissions is required
         $errors[]  = 'copyright_permission';
      }
      // answer to patent is required
      if ($answers["patent"] == null) {
         $errors[] = 'patent';
      }
      // answer to embargo question is required
      if ($answers["embargo"] == null) {
        $errors[] = 'embargo';
      } else if ($answers["embargo"] == 'yes' && $answers["embargo_abs_toc"] == null) {
        $errors[] = 'embargo_abs_toc';
     }

     if ($answers['title'] == null) {
      $errors[] = 'title';
     }

     return $errors;
  }


  /**
   * Create and initialize a new etd object based on info from ProcessPDF
   *
   * @param array $etd_info info as returned by ProcessPDF helper
   * @return etd
   */
  public function initialize_etd(array $etd_info) {
    // create new etd record and save all the fields

    // get per-school configuration for the current user
    $school_cfg = Zend_Registry::get("schools-config");
    $school_id = $this->current_user->getSchoolId();
    // if no school id is found, set a default so no record is created without a school
    if (!$school_id) {
      $school_id = "graduate_school";
      $this->logger->err("Could not determine school for " . $this->current_user->netid .
       "; defaulting to Graduate School");
    }
    // pass school-specific configuration to new etd record
    $etd = new etd($school_cfg->$school_id);
    
    $etd->title = $etd_info['title'];
    
    // use netid for object ownership and author relation
    $current_user = $this->current_user;
    $etd->owner = $current_user->netid;
    // set author info
    $etd->mods->setAuthorFromPerson($current_user);
    
    // As of 2012/06, table of contents is no longer extracted from pdf.
    // Abstract is ignored (may be extracted, but not reliable)
    
    // attempt to find a match for department from program list
    switch ($school_id) { // limit to relevant section
      case "graduate_school": $section = "#grad"; break;
      case "emory_college": $section = "#undergrad"; break;
      case "candler": $section = "#candler"; break;
      case "rollins": $section = "#rollins"; break; 
      default: $section = "#grad"; break;
    }
    
    $programObject = new foxmlPrograms($section); 
    $programs = $programObject->skos;    
    
    // first try to use the academic_plan_id mapped to dc:identifier
    // to set the program, department, and subfield (if exists).

    if ($current_user->academic_plan_id) {
      $prog_id = $programs->findIdbyElement("dc:identifier", $current_user->academic_plan_id);

      if(isset($prog_id)){
          $collection = new collectionHierarchy($programs->dom, $prog_id);
          $level = $collection->getLevel("#programs");
          $parent_id = $collection->parent->id;
      }

      
      if(($section == "#grad" && $level == 4) 
        || ($section == "#rollins" && $level == 3)){  // cases where subfield is populated
          $etd->rels_ext->program = strtolower($parent_id);
          $etd->department = $programObject->skos->findLabelbyId($parent_id);
          $etd->rels_ext->subfield = $prog_id;
          $etd->mods->subfield = $programObject->skos->findLabelbyId($prog_id);
      }
      elseif(isset($prog_id)){
          $etd->rels_ext->program = strtolower($prog_id);
          $etd->department = $programs->findLabelbyId($prog_id);;  // set the department name
      }

    } else {
      // second try to use "academic plan" from ESD to set department
      if ($current_user->academic_plan) {
        $department = $programs->findLabel($current_user->academic_plan);
      } 
      // as of 2012/06, no longer attempting to detect department from PDF

      // if we found a department either way, set text in mods and id in rels-ext
      if (isset($department) && $department) {
        $etd->department = $department;
        // find program id and store in rels       
        $prog_id = $programs->findIdbyElement("rdfs:label", $department);
        if ($prog_id) $etd->rels_ext->program = $prog_id;
      }      
    }    


    return $etd;
  }
  
  public function reviewAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("submit", $etd)) return false;
    // double-check that etd is ready to submit
    if (! $etd->readyToSubmit()) {
      $this->_helper->flashMessenger->addMessage("Your record is not ready to submit; first complete any missing portions before you review and submit");
      $this->_helper->redirector->gotoRoute(array("controller" => "view",
              "action" => "record",
              "pid" => $etd->pid));
    }
   
    $this->view->etd = $etd;
    $this->view->title = "Final Review";
  }

  public function submitAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("submit", $etd)) return false;
    
    // double-check that etd is ready to submit
    if (! $etd->readyToSubmit()) {
      $this->_helper->flashMessenger->addMessage("Your record is not ready to submit; first complete any missing portions before you review and submit");
      $this->_helper->redirector->gotoRoute(array("controller" => "view",
              "action" => "record",
              "pid" => $etd->pid));
      return;
    }
    
    
    $newstatus = "submitted";
    $etd->setStatus($newstatus);

    // log event in record history 
    $etd->premis->addEvent("status change",
         "Submitted for Approval by " . $this->current_user->fullname,
         "success",  array("netid", $this->current_user->netid));

    // note: assuming email will be successful if record is saved succesfully
    //       (but not actually sending email until save is done)
    $etd->premis->addEvent("notice",
         "Submission Notification sent by ETD system",
         "success",  array("software", "etd system"));


    /** NOTE: the record should not be saved until after the submission event is added.
     Otherwise the record will no longer be a draft and the author will not
     be allowed to make changes.
    */
    $result = $etd->save("set status to '$newstatus'; notification event");
    if ($result) {
      $this->_helper->flashMessenger->addMessage("Record status changed to <b>$newstatus</b>");
      $this->logger->info("Successfully submitted " . $etd->pid);

      // send notification only if submit succeeded 
      $notify = new etd_notifier($etd);
      $to = $notify->submission();
      $this->_helper->flashMessenger->addMessage("Submission notification email sent to " . implode(', ', array_keys($to)));
    }     else {
      $this->logger->err("Problem submitting " . $etd->pid);
      $this->_helper->flashMessenger->addMessage("Error: there was a problem submitting your record");
    }
    
    // forward to congratulations message
    $this->_helper->redirector->gotoRoute(array("controller" => "submission",
            "action" => "success", "pid" => $etd->pid), "", true);
  }

  // display success message with more information
  public function successAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    $this->view->title = "Submission successful";
    $this->view->etd = $etd;
  }


  /**
   * error accessing ESD - show an error and redirect to "service
   * unavailable" page; called in processpdf and debugpdf actions if
   * an exception is thrown when attempting to find faculty by name in ESD.
   */
  private function ESDerror() {
    $this->_helper->flashMessenger->addMessage("Error: could not access Emory Shared Data");
    // redirect to an error page
    $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "unavailable"));
  }

}
