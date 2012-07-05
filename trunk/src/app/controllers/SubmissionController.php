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
  
  public function indexAction() {
    $this->_forward("start");
    // maybe this should be a summary/status page to check on the submission...
  }
   
  public function startAction() {
    if (!$this->_helper->access->allowedOnEtd("create")) return false;
    // any other info needed here?
    $this->view->title = "Begin Submission";

    //Get all the answers to the questions from the post to validate
    $answers["copyright"] = $this->_getParam("copyright");
    $answers["copyright_permission"] = $this->_getParam("copyright_permission");
    $answers["patent"] = $this->_getParam("patent");
    $answers["embargo"] = $this->_getParam("embargo");
    $answers["embargo_abs_toc"] = $this->_getParam("embargo_abs_toc");
    $this->view->answers = $answers;
  }


  // pulls information from the PDF, creates a new fedora record with associated pdf file,
  // then forwards to the view/master edit page
  public function processpdfAction() {
    if (!$this->_helper->access->allowedOnEtd("create")) return false;

    //Validate questions from previous page
     //If they are not valid return to the last page

    //Get all the answers to the questions from the post to validate
    $answers["copyright"] = $this->_getParam("copyright");
    $answers["copyright_permission"] = $this->_getParam("copyright_permission");
    $answers["patent"] = $this->_getParam("patent");
    $answers["embargo"] = $this->_getParam("embargo");
    $answers["embargo_abs_toc"] = $this->_getParam("embargo_abs_toc");
    
    $questionsValid = $this->validateQuestions($answers);
    
    if(!$questionsValid){
                
      //Take parameters array and add values necessary for redirection
      $redir = $answers;
      $redir["controller"] = "submission";
      $redir["action"] = "start";
      $this->_helper->redirector->gotoRoute($redir);
    }

    //Send answers to view   
    $this->view->answers = $answers;

    // allow a way to create record even if there are errors -
    // workaround for unrecognizable PDFs
    $force_create = false;
    if ($this->_getParam("force") == "create") $force_create = true;
    
    $etd_info = $this->_helper->processPDF($_FILES['pdf']);

    // if force_create is set, use the filename as a title so that a record can be created
    // with whatever information was found (may be none)
    if ($etd_info['title'] == "" && $force_create) {
      $etd_info['title'] = $etd_info['filename'];
    }
    
    /** if there is insufficient information or possible problems with pdf,
        do not create record
    **/
    if ($etd_info['title'] == "" ||
      $etd_info["distribution_agreement"] == false) {
      // - cannot create fedora object without a title
      // - if distribution agreement was not detected, there may be a problem
      //   with the text OR the front matter pages are not present/in order

      $errlist = "";
      foreach ($this->view->errors as $err) {
        $errlist .= " $err;";
      }
      $this->logger->err("Error attempting to create new etd record.  Problems found:" 
       . $errlist);
      // display errors encountered in PDF and suggested trouble-shooting steps
      return;
    }

    /** found sufficient information to create fedora record, pdf seems ok **/

    $etd =  $this->initialize_etd($etd_info);

    $errtype = "";
    $pid = $this->_helper->ingestOrError($etd,
           "creating preliminary record from uploaded pdf",
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
  
      // need to retrieve record from fedora so datastreams can be saved, etc.
      $etd = $this->_helper->getFromFedora->findById($pid, "etd");
      
      // could use fullname as agent id, but netid seems more useful
      $etd->premis->addEvent("ingest", "Record created by " .
           $this->current_user->fullname, "success",
           array("netid", $this->current_user->netid));

      //Save info for copyright questions
      $eventMsg="The author " . ($answers["copyright"] == 1 ? "has" : "has not") . " submitted copyrighted material";
      if($answers["copyright"] == 1){
          $eventMsg .= " and " . ($answers["copyright_permission"] == 1 ? "has" : "has not") . " obtained permission to include this copyrighted material";
      }
      $eventMsg .=".";
      $etd->premis->addEvent("admin", $eventMsg, "success",
           array("netid", $this->current_user->netid));
             
      //Save info for patent questions
      $eventMsg="The author " . ($answers["patent"] == 1 ? "has" : "has not") . " submitted patented material.";
      $etd->premis->addEvent("admin", $eventMsg, "success",
           array("netid", $this->current_user->netid));

      $message = "added history events";
      $result = $etd->save($message);
      if ($result)
        $this->logger->info("Updated etd " . $etd->pid . " at $result ($message)");
      else
        $this->logger->err("Error updating etd " . $etd->pid . " ($message)");

      
      // only create the etdfile object if etd was successfully created
      $etdfile = new etd_file(null, $etd);  // initialize from template, but associate with parent etd
      $etdfile->initializeFromFile($etd_info['pdf'], "pdf",
           $this->current_user, $etd_info['filename']);
      
      // add relations between objects
      $etdfile->rels_ext->addRelationToResource("rel:isPDFOf", $etd->pid);
      
      $filepid = $this->_helper->ingestOrError($etdfile,
                 "creating record from uploaded pdf",
                 "file record", $errtype);
      if ($filepid == false) {
        // any special handling based on error type
        switch ($errtype) {
            case "FedoraObjectNotValid":
            case "FedoraObjectNotFound":
              $this->view->errors[] = "Could not save PDF to Fedora.";
              if ($this->debug) $this->view->filexml = $etdfile->saveXML();
              break;
        }
        $this->logger->err("Failure uploading pdf to Fedora : " . errtype);
      } else {  // valid pid returned for file
        $this->logger->info("Created new etd file record with pid $filepid");

        // add relation to etdfile object and save changes
        $etd->addPdf($etdfile);
        $result = $etd->save("added relation to uploaded pdf"); // also saves changed etdfile
        if ($result)
          $this->logger->info("Updated etd " . $etd->pid . " at $result (adding relation to pdf)");
        else
          $this->logger->err("Error updating etd " . $etd->pid . " (adding relation to pdf)");

        if ($this->debug) $this->_helper->flashMessenger->addMessage("Saved etd file as $filepid");

        //send email if patent screening question is yes
        if(strval($answers["patent"]) == "1"){
          $notice = new etd_notifier($etd);
          $notice->patent_concerns();

          //Email sent history event
          $eventMsg="Email sent due to patent concern.";
          $etd->premis->addEvent("admin", $eventMsg, "success",
                     array("netid", $this->current_user->netid));

          $message = "Patent email sent";
          $result = $etd->save($message);
          if ($result)
            $this->logger->info("Updated etd " . $etd->pid . " at $result ($message)");
          else
            $this->logger->err("Error updating etd " . $etd->pid . " ($message)");

          $this->_helper->flashMessenger->addMessage("An e-mail has been sent to notify the appropriate person of potential patent concerns.");
        }

        $this->_helper->redirector->gotoRoute(array("controller" => "view",
                                "action" => "record",
                                "pid" => $etd->pid));
  
      }  // end file successfully ingested

    } // end etd successfully ingested
    
      /* If record cannot be created or there was an error extracting
       information from the PDF, the processpdf view script will be
       displayed, including any error messages, along with a list of
       suggested trouble-shooting steps to try.
      */
  }

  /**
   * Validate questions on submission start page
   *
   * @return boolean
   */
  public function validateQuestions($answers){
      $valid=true;

      // answer to copyright is required
      if (strval($answers["copyright"]) == NULL) {
         $this->_helper->flashMessenger->addMessage("Error: Answer to 1. \"copyright\" is required");
         $valid = false;
      } else if ($answers["copyright"] == 1 && strval($answers["copyright_permission"])== NULL) {
         // if answer to copyright is yes, answer about permissions is required
         $this->_helper->flashMessenger->addMessage("Error: Answer to 1b. \"copyright permissions\" is required");
         $valid = false;
      }

      // answer to patent is required
      if (strval($answers["patent"]) == NULL) {
         $this->_helper->flashMessenger->addMessage("Error: Answer to 2. \"patent\" is required");
         $valid = false;
      }

      // answer to embargo question is required
     if (strval($answers["embargo"]) == NULL) {
         $this->_helper->flashMessenger->addMessage("Error: Answer to 3. \"embargo\" is required");
         $valid = false;
     } else if ($answers["embargo"] == 1 && strval($answers["embargo_abs_toc"]) == NULL) {
       $this->_helper->flashMessenger->addMessage("Error: Answer to 3b. \"embargo level\" is required");
       $valid = false;
     }
     return $valid;
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
    
    $etd->abstract = $etd_info['abstract'];
    $etd->contents = $etd_info['toc'];
    
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

      
      if(($section == "#grad" && $level == 4) || ($section == "#rollins" && $level == 3)){  //These are the casese where subfiled is populated
          $etd->rels_ext->program = strtolower($parent_id);
          $etd->department = $programObject->skos->findLabelbyId($parent_id);
          $etd->rels_ext->subfield = $prog_id;
          $etd->mods->subfield = $programObject->skos->findLabelbyId($prog_id);
      }
      elseif(isset($prog_id)){
          $etd->rels_ext->program = strtolower($prog_id);
          $etd->department = $programs->findLabelbyId($prog_id);;  // set the department name
      }

    }
    else {
      // second try to use "academic plan" from ESD to set department
      if ($current_user->academic_plan) {
        $department = $programs->findLabel($current_user->academic_plan);
      } 
      // as of 2012/06, no longer attempting to detect department from

      // if we found a department either way, set text in mods and id in rels-ext
      if (isset($department) && $department) {
        $etd->department = $department;
        // find program id and store in rels       
        $prog_id = $programs->findIdbyElement("rdfs:label", $department);
        if ($prog_id) $etd->rels_ext->program = $prog_id;
      }      
    }    
    
    // match faculty names found in the PDF to persons in ESD
    $esd = new esdPersonObject();    
    $advisors = array();
    if (isset($etd_info['advisor']) && $etd_info['advisor'][0] != '') {
      foreach ($etd_info['advisor'] as $name) {
        $esd = new esdPersonObject();      
        if ($name == "") continue;  // skip if blank for some reason
        try {
          $advisor = $esd->findFacultyByName($name);
        } catch (Exception $e) {
          $this->ESDerror();
        }
        if ($advisor) 
          $advisors[] = $advisor;
        else 
          $this->_helper->flashMessenger->addMessage("Couldn't find directory match for "
                 . $name . "; please enter manually");
      }
    }


    // NOTE: disabling committee member detection 2012/06 in order to avoid errors
    // and potentially invalid records (duplicate name/id detection)
    // 
    // don't set committee if none are found (causes problem trying to remove blank entry)
    // if (count($advisors)) $etd->setCommittee($advisors, "chair");   
    
    // $committee = array();
    // foreach ($etd_info['committee'] as $cm) {
    //   if ($cm == "") continue;  // skip if blank for some reason
    //   try {
    //     $person = $esd->findFacultyByName($cm);
    //   } catch (Exception $e) {
    //     $this->ESDerror();
    //   }
    //   if ($person) 
    //     $committee[] = $person;
    //   else 
    //     $this->_helper->flashMessenger->addMessage("Couldn't find directory match for "
    //            . $cm . "; please enter manually");
    // }
    // // don't set committee if none are found (causes problem trying to remove blank entry)
    // if (count($committee)) $etd->setCommittee($committee);
    
    
    foreach ($etd_info['keywords'] as $i => $keyword) {
      if (array_key_exists($i, $etd->mods->keywords))
        $etd->mods->keywords[$i]->topic = $keyword;
      else 
        $etd->mods->addKeyword($keyword);
    }

    return $etd;
  }
  

  public function debugpdfAction() {
    
    $this->view->messages = array();
    $this->view->etd_info = $this->_helper->processPDF($_FILES['pdf']);

    // as of 2012/06, department and committee no longer detected in pdf
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
