<?php

require_once("models/etd.php");
require_once("models/etd_notifier.php");


class SubmissionController extends Etd_Controller_Action {

  public function indexAction() {
    $this->_forward("start");
    // maybe this should be a summary/status page to check on the submission...
  }
   
  public function startAction() {
    if (!$this->_helper->access->allowedOnEtd("create")) return;
    // any other info needed here?
    $this->view->title = "Begin Submission";
  }


  // pulls information from the PDF, creates a new fedora record with associated pdf file,
  // then forwards to the view/master edit page
  public function processPdfAction() {
    if (!$this->_helper->access->allowedOnEtd("create")) return;
    
    $etd_info = $this->_helper->processPDF($_FILES['pdf']);

    // as long as title is not blank, create fedora object for user to edit
    if ($etd_info['title'] != "") {
      // create new etd record and save all the fields
      $etd = new etd();
      $etd->title = $etd_info['title'];

      $current_user = $this->view->current_user;
      // use netid for object ownership and author relation
      $etd->owner = $current_user->netid;
      $etd->rels_ext->addRelation("rel:author", $current_user->netid);
      // set author info
      $etd->mods->setAuthorFromPerson($current_user);
      
      $etd->abstract = $etd_info['abstract'];
      $etd->contents = $etd_info['toc'];


      // FIXME: should we be using author's "Academic Plan" field from ESD here? (at least as first choice?)
      
      // attempt to find a match for department from program list
      $xml = new DOMDocument();
      $xml->load("../config/programs.xml"); 
      $programs = new programs($xml);

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
      if ($advisor = $esd->findFacultyByName($etd_info['advisor'])) {
	$etd->mods->setAdvisorFromPerson($advisor);
      } else {
	$this->_helper->flashMessenger->addMessage("Couldn't find directory match for " .
				   $this->view->etd_info['advisor'] . "; please enter manually");
      }
      
      $committee = array();
      foreach ($etd_info['committee'] as $cm) {
	if ($person = $esd->findFacultyByName($cm)) 
	  $committee[] = $person;
	else 
	  $this->_helper->flashMessenger->addMessage("Couldn't find directory match for "
						     . $cm . "; please enter manually");
      }
      $etd->mods->setCommitteeFromPersons($committee);


      foreach ($etd_info['keywords'] as $i => $keyword) {
	if (array_key_exists($i, $etd->mods->keywords))
	  $etd->mods->keywords[$i]->topic = $keyword;
	else 
	  $etd->mods->addKeyword($keyword);
      }
          
      // create an etd file object for uploaded PDF and associate with etd record
      $pid = $etd->save("creating preliminary record from uploaded pdf");
      if ($this->debug) $this->_helper->flashMessenger->addMessage("Saved etd as $pid");
      if ($pid) {
	// need to retrieve record from fedora so datastreams can be saved, etc.
	$etd = $this->_helper->getFromFedora->findById($pid, "etd");
	
	// could use fullname as agent id, but netid seems more userful
	//FIXME: use full/display name here for user identity
	$etd->premis->addEvent("ingest", "Record created by " . $current_user->fullname, "success",
			       array("netid", $identity));
	$etd->save();

	// only create the etdfile object if etd was successfully created
	// FIXME: somehow combine this logic with repeated code in FileController ?
	$etdfile = new etd_file();
	$etdfile->owner = $this->view->current_user->netid;	// set current user to object owner
	
	$etdfile->label = "Dissertation";	// FIXME: what should this be?
	// FIXME: set reasonable defaults for author, description
	$etdfile->setFileInfo($etd_info['pdf']);	// set mimetype, filesize, and pages if appropriate
	
	//	$etdfile->file->mimetype = $etdfile->dc->format[0] = "application/pdf";
	// filename is stored in etd_info array as 'pdf'
	//	$etdfile->dc->setFilesize(filesize($etd_info['pdf']));	// file size in bytes
	//	$etdfile->dc->setPages($this->_helper->PdfPageTotal($etd_info['pdf']));

	$etdfile->setFile($etd_info['pdf']);	// upload and set ingest url to upload id

	
	// fixme: any other settings we can/should do?
	
	// add relations between objects
	//      $etdfile->rels_ext->addRelationToResource("rel:owner", $user->pid);
	$etdfile->rels_ext->addRelationToResource("rel:isPDFOf", $etd->pid);
	$filepid = $etdfile->save("creating record from uploaded pdf");	// save and get pid

       // delete temporary file now that we are done with it
	unlink($etd_info['pdf']);
	
	// add relation to etd object and save changes
	$result = $etd->addPdf($etdfile);
	//	$etd->rels_ext->addRelationToResource("rel:hasPDF", $filepid);
	$etd->save("added relation to uploaded pdf");
	if ($this->debug) $this->_helper->flashMessenger->addMessage("Saved etd file as $filepid");
	
	$this->_helper->redirector->gotoRoute(array("controller" => "view",
						    "action" => "record",
						    "pid" => $etd->pid));
	
      } else {
	$this->_helper->flashMessenger->addMessage("Error saving etd to fedora");
	$this->view->foxml = $etd->saveXML();
      }

    } else {
      // error finding title; ask user if pdf is in correct format...
    }
  }

  public function debugPdfAction() {
    
    $this->view->messages = array();
    $this->view->etd_info = $this->_helper->processPDF($_FILES['pdf']);

    $xml = new DOMDocument();
    $xml->load("../config/programs.xml"); 
    $programs = new programs($xml);

    $this->view->department =  $programs->findLabel($this->view->etd_info['department']);
    

    $esd = new esdPersonObject();
    if ($advisor = $esd->findFacultyByName($this->view->etd_info['advisor']))
      $this->view->advisor = $advisor;
    else 
      $this->_helper->flashMessenger->addMessage("Couldn't find directory match for " . $this->view->etd_info['advisor'] . "; please enter manually");

    $this->view->committee = array();
    foreach ($this->view->etd_info['committee'] as $cm) {
      if ($committee = $esd->findFacultyByName($cm)) 
	$this->view->committee[] = $committee;
      else 
	$this->_helper->flashMessenger->addMessage("Couldn't find directory match for " . $cm . "; please enter manually");
    }
  }

  public function reviewAction() {
    // double-check that etd is ready to submit?
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("submit", $etd)) return;
    
    $this->view->etd = $etd;
    $this->view->title = "final review";
  }

  public function submitAction() {
    // fixme: double-check that etd is ready to submit
    
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("submit", $etd)) return;
    
    
    $newstatus = "submitted";
    $etd->setStatus($newstatus);

    // log event in record history 
    $etd->premis->addEvent("status change",
			   "Submitted for Approval by " . $this->current_user->fullname,
			   "success",  array("netid", $this->current_user->netid));
    
    $result = $etd->save("set status to '$newstatus'");
    if ($result)
      $this->_helper->flashMessenger->addMessage("Record status changed to <b>$newstatus</b>");
    else 
      $this->_helper->flashMessenger->addMessage("Error: could not submit record");

    $notify = new etd_notifier($etd);
    $to = $notify->submission();

    $etd->premis->addEvent("notice",
				 "Submission Notification sent by ETD system",
				 "success",  array("software", "etd system"));
    $result = $etd->save("notification event");
    
    $this->_helper->flashMessenger->addMessage("Submission notification email sent to " . implode(', ', array_keys($to)));

    
    // forward to .. my etds page ?
    $this->_helper->redirector->gotoRoute(array("controller" => "browse",
						 "action" => "my"), "", true); 


  }

}