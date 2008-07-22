<?php

require_once("models/etd.php");
require_once("models/etd_notifier.php");


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
    
    $etd_info = $this->_helper->processPDF($_FILES['pdf']);

    // as long as title is not blank, create fedora object for user to edit
    if ($etd_info['title'] != "") {
      // create new etd record and save all the fields
      $etd = new etd();
      $etd->title = $etd_info['title'];

      $current_user = $this->current_user;
      // use netid for object ownership and author relation
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
	if ($advisor = $esd->findFacultyByName($etd_info['advisor'])) {
	  $etd->mods->setAdvisorFromPerson($advisor);
	} else {
	  $this->_helper->flashMessenger->addMessage("Couldn't find directory match for " .
						     $this->view->etd_info['advisor'] . "; please enter manually");
	}
      }
      
      $committee = array();
      foreach ($etd_info['committee'] as $cm) {
	if ($cm == "") continue;	// skip if blank for some reason
	if ($person = $esd->findFacultyByName($cm)) 
	  $committee[] = $person;
	else 
	  $this->_helper->flashMessenger->addMessage("Couldn't find directory match for "
						     . $cm . "; please enter manually");
      }
      // don't set committee if none are found (causes problem trying to remove blank entry)
      if (count($committee)) $etd->mods->setCommitteeFromPersons($committee);


      foreach ($etd_info['keywords'] as $i => $keyword) {
	if (array_key_exists($i, $etd->mods->keywords))
	  $etd->mods->keywords[$i]->topic = $keyword;
	else 
	  $etd->mods->addKeyword($keyword);
      }

      try {
	// create an etd file object for uploaded PDF and associate with etd record
	$pid = $etd->save("creating preliminary record from uploaded pdf");
      } catch (PersisServiceUnavailable $e) {
	// ingest relies on persistent id server to generate the pid
	$this->_helper->flashMessenger->addMessage("Error: could not create new record because Persistent Identifier Service is not available");
	// redirect to an error page
	$this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "unavailable"));
      } catch (FedoraObjectNotValid $e) {
	print $e->getMessage() . "<br/>\n";
	$this->view->errors[] = "Could not create record.";
	$this->view->xml = $etd->saveXML();
	return;
      }
      // FIXME: convert this to logging
      if ($this->debug) $this->_helper->flashMessenger->addMessage("Saved etd as $pid");
      if ($pid) {
	// need to retrieve record from fedora so datastreams can be saved, etc.
	$etd = $this->_helper->getFromFedora->findById($pid, "etd");
	
	// could use fullname as agent id, but netid seems more useful
	$etd->premis->addEvent("ingest", "Record created by " . $current_user->fullname, "success",
			       array("netid", $identity));
	$etd->save("added ingest history event");

	// only create the etdfile object if etd was successfully created
	$etdfile = new etd_file(null, $etd);	// initialize from template, but associate with parent etd
	$etdfile->initializeFromFile($etd_info['pdf'], "pdf", $current_user);
	
	// add relations between objects
	$etdfile->rels_ext->addRelationToResource("rel:isPDFOf", $etd->pid);

	try {
	  $filepid = $etdfile->save("creating record from uploaded pdf");	// save and get pid
	} catch (PersisServiceUnavailable $e) {
	  // if Persis is down this would probably already be caught when attempting to save new etd
	  $this->_helper->flashMessenger->addMessage("Error: could not create new record because Persistent Identifier Service is not available");
	  // redirect to an error page
	  $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "unavailable"));
	}

	// delete temporary file now that we are done with it
	unlink($etd_info['pdf']);
	
	// add relation to etd object and save changes
	$result = $etd->addPdf($etdfile);
	$etd->save("added relation to uploaded pdf");
	if ($this->debug) $this->_helper->flashMessenger->addMessage("Saved etd file as $filepid");
	
	$this->_helper->redirector->gotoRoute(array("controller" => "view",
						    "action" => "record",
						    "pid" => $etd->pid));
	
      } else {
	$this->_helper->flashMessenger->addMessage("Error saving record");
	$this->view->foxml = $etd->saveXML();
      }
    }
    /* If record cannot be created or there was an error extracting
     information from the PDF, the processpdf view script will be
     displayed, including any error messages, alont with a list of
     suggested trouble-shooting steps to try.
    */
  }

  public function debugpdfAction() {
    
    $this->view->messages = array();
    $this->view->etd_info = $this->_helper->processPDF($_FILES['pdf']);

    $programs = new programs();
    $this->view->department =  $programs->findLabel($this->view->etd_info['department']);
    

    $esd = new esdPersonObject();
    // only look up advisor if a name was found
    if (isset($etd_info['advisor']) && $etd_info['advisor'] != '') {
      if ($advisor = $esd->findFacultyByName($this->view->etd_info['advisor']))
	$this->view->advisor = $advisor;
      else 
	$this->_helper->flashMessenger->addMessage("Couldn't find directory match for " . $this->view->etd_info['advisor'] . "; please enter manually");
    }
    $this->view->committee = array();
    if (isset($this->view->etd_info['committee'])) {
      foreach ($this->view->etd_info['committee'] as $cm) {
	if ($cm == "") continue;	// skip if blank for some reason
	if ($committee = $esd->findFacultyByName($cm)) 
	  $this->view->committee[] = $committee;
	else 
	  $this->_helper->flashMessenger->addMessage("Couldn't find directory match for " . $cm . "; please enter manually");
      }
    }
  }

  public function reviewAction() {
    // double-check that etd is ready to submit?
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("submit", $etd)) return false;
    
    $this->view->etd = $etd;
    $this->view->title = "Final Review";
  }

  public function submitAction() {
    // fixme: double-check that etd is ready to submit
    
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("submit", $etd)) return false;
    
    
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

      // send notification only if submit succeeded 
      $notify = new etd_notifier($etd);
      $to = $notify->submission();
      $this->_helper->flashMessenger->addMessage("Submission notification email sent to " . implode(', ', array_keys($to)));
    }
    else {
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

}