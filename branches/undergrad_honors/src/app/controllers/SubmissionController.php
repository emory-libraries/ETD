<?php

require_once("models/etd.php");
require_once("models/etd_notifier.php");
require_once("models/honors_etd.php");

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
  }


  // pulls information from the PDF, creates a new fedora record with associated pdf file,
  // then forwards to the view/master edit page
  public function processpdfAction() {
    if (!$this->_helper->access->allowedOnEtd("create")) return false;

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
	
    // create new etd record and save all the fields
    if ($this->current_user->role == "honors_student") {
      // if user is an honors student, create new record as honors etd
      $etd = new honors_etd();
    } else {
      // for all other users, use default etd
      $etd = new etd();
    }
    
    $etd->title = $etd_info['title'];
    
    // use netid for object ownership and author relation
    $current_user = $this->current_user;
    $etd->owner = $current_user->netid;
    // set author info
    $etd->mods->setAuthorFromPerson($current_user);
    
    $etd->abstract = $etd_info['abstract'];
    $etd->contents = $etd_info['toc'];
    
    // attempt to find a match for department from program list
    $programs = new programs();
    
    // first try to use "academic plan" from ESD to set department
    if ($current_user->academic_plan) {
      $department = $programs->findLabel($current_user->academic_plan);
      if ($department)
	$etd->department = $department;
    } elseif (isset($this->view->etd_info['department'])) {
      // otherwise, use department picked up from the PDF (if any)
      $department = $programs->findLabel($this->view->etd_info['department']);
      if ($department)
	$etd->department = $department;
    }
    
    // match faculty names found in the PDF to persons in ESD
    $esd = new esdPersonObject();
    // only look up advisor if a name was found
    if (isset($etd_info['advisor']) && $etd_info['advisor'] != '') {
      foreach ($etd_info['advisor'] as $name) {
	$advisor = null;
	try {
	  $advisor = $esd->findFacultyByName($name);
	} catch (Exception $e) {
	  $this->ESDerror();
	}
	if ($advisor) {
	  // add through etd object so xacml will get updated
	  $etd->setCommittee(array($advisor), "chair");
	} else {
	  $this->_helper->flashMessenger->addMessage("Couldn't find directory match for " .
						     $name . "; please enter manually");
	}
      }
    }
    
    $committee = array();
    foreach ($etd_info['committee'] as $cm) {
      if ($cm == "") continue;	// skip if blank for some reason
      try {
	$person = $esd->findFacultyByName($cm);
      } catch (Exception $e) {
	$this->ESDerror();
      }
      if ($person) 
	$committee[] = $person;
      else 
	$this->_helper->flashMessenger->addMessage("Couldn't find directory match for "
						   . $cm . "; please enter manually");
    }
    // don't set committee if none are found (causes problem trying to remove blank entry)
    if (count($committee)) $etd->setCommittee($committee);
    
    
    foreach ($etd_info['keywords'] as $i => $keyword) {
      if (array_key_exists($i, $etd->mods->keywords))
	$etd->mods->keywords[$i]->topic = $keyword;
      else 
	$etd->mods->addKeyword($keyword);
    }





    $etd_info = $this->_helper->processPDF($_FILES['pdf']);

    $pid = $this->_helper->ingestOrError($etd,
					 "creating preliminary record from uploaded pdf",
					 "etd record", $errtype);

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
    } else {	// valid pid was returned
      $this->logger->info("Created new etd record with pid $pid");
      if ($this->debug) $this->_helper->flashMessenger->addMessage("Saved etd as $pid");
	
      // need to retrieve record from fedora so datastreams can be saved, etc.
      $etd = $this->_helper->getFromFedora->findById($pid, "etd");
      
      // could use fullname as agent id, but netid seems more useful
      $etd->premis->addEvent("ingest", "Record created by " . $current_user->fullname, "success",
			     array("netid", $current_user->netid));
      $message = "added ingest history event";
      $result = $etd->save($message);
      if ($result)
	$this->logger->info("Updated etd " . $etd->pid . " at $result ($message)");
      else
	$this->logger->err("Error updating etd " . $etd->pid . " ($message)");

      
      // only create the etdfile object if etd was successfully created
      $etdfile = new etd_file(null, $etd);	// initialize from template, but associate with parent etd
      $etdfile->initializeFromFile($etd_info['pdf'], "pdf", $current_user, $etd_info['filename']);
      
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
      } else {	// valid pid returned for file
	$this->logger->info("Created new etd file record with pid $filepid");
	
	// delete temporary file now that we are done with it
	unlink($etd_info['pdf']);
	
	// add relation to etdfile object and save changes
	$etd->addPdf($etdfile);
	$result = $etd->save("added relation to uploaded pdf");	// also saves changed etdfile
	if ($result)
	  $this->logger->info("Updated etd " . $etd->pid . " at $result (adding relation to pdf)");
	else
	  $this->logger->err("Error updating etd " . $etd->pid . " (adding relation to pdf)");
	
	if ($this->debug) $this->_helper->flashMessenger->addMessage("Saved etd file as $filepid");
	
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

  public function debugpdfAction() {
    
    $this->view->messages = array();
    $this->view->etd_info = $this->_helper->processPDF($_FILES['pdf']);

    $programs = new programs();
    $this->view->department =  $programs->findLabel($this->view->etd_info['department']);
    

    $esd = new esdPersonObject();
    $this->view->advisor = array();
    // only look up advisor if a name was found
    if (isset($etd_info['advisor']) && $etd_info['advisor'][0] != '') {
      foreach ($etd_info['advisor'] as $name) {
	$advisor = null;
	try {
	  $advisor = $esd->findFacultyByName($name);
	} catch (Exception $e) {
	  $this->ESDerror();
	}
	if ($advisor)
	  $this->view->advisor[] = $advisor;
	else 
	  $this->_helper->flashMessenger->addMessage("Couldn't find directory match for " . $name . "; please enter manually");
      }
    }
    $this->view->committee = array();
    if (isset($this->view->etd_info['committee'])) {
      foreach ($this->view->etd_info['committee'] as $cm) {
	if ($cm == "") continue;	// skip if blank for some reason
	try {
	  $committee = $esd->findFacultyByName($cm);
	} catch (Exception $e) {
	  $this->ESDerror();
	}
	if ($committee) 
	  $this->view->committee[] = $committee;
	else 
	  $this->_helper->flashMessenger->addMessage("Couldn't find directory match for " . $cm . "; please enter manually");
      }
    }
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
    // 	     (but not actually sending email until save is done)
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
    }
    else {
      $logger->err("Problem submitting " . $etd->pid);
      $this->_helper->flashMessenger->addMessage("Error: there was a problem submitting your record");
    }
    
    // forward to congratulations message
    $this->_helper->redirector->gotoRoute(array("controller" => "submission",
						"action" => "success"), "", true); 
  }

  // display success message with more information
  public function successAction() {
    $this->view->title = "Submission successful";
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