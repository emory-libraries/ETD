<?php
/** Zend_Controller_Action */

/* Require models */
require_once("models/etd.php");
require_once("models/etd_notifier.php");
require_once("helpers/ProcessPDF.php");


class SubmissionController extends Zend_Controller_Action {

  public function init() {
    Zend_Controller_Action_HelperBroker::addPath('../library/Emory/Controller/Action/Helper',
						 'Emory_Controller_Action_Helper');
    $this->acl = Zend_Registry::get("acl");
    $this->user = $this->view->current_user;

    // submit action should cover the whole controller
    if (!$this->acl->isAllowed($this->user->role, "etd", "submit")) {
      $this->_helper->flashMessenger->addMessage("Error: " . $this->user->netid . " (role=" . $this->user->role . 
						 ") is not authorized to submit an etd");
      $this->_helper->redirector->gotoRoute(array("controller" => "auth",
						  "action" => "denied"), "", true);
    }
  }

  public function indexAction() {
    $this->_forward("start");
    // maybe this should be a summary/status page to check on the submission...
  }
   
  public function startAction() {
    // any other info needed here?
    $this->view->title = "Begin Submission";
  }


  // pulls information from the PDF, creates a new fedora record with associated pdf file,
  // then forwards to the view/master edit page
  public function processPdfAction() {
    Zend_Controller_Action_HelperBroker::addPrefix('Etd_Controller_Action_Helper');
    $etd_info = $this->_helper->processPDF($_FILES['pdf']);

    // as long as title is not blank, create fedora object for user to edit
    if ($etd_info['title'] != "") {
      // create new etd record and save all the fields
      $etd = new etd();
      $etd->title = $etd_info['title'];

      // fixme: can we rely on current_user stored in view object?
      // user must be logged in to run this action anyway
      $auth = Zend_Auth::getInstance();
      if ($auth->hasIdentity()) {
	$identity = $auth->getIdentity();
	// use netid for object ownership and author relation
	$etd->owner = $identity->netid;
	$etd->rels_ext->addRelation("rel:author", $identity->netid);
      }
      // set author info
      $etd->mods->setAuthorFromPerson($identity);
      
      $etd->abstract = $etd_info['abstract'];
      $etd->contents = $etd_info['toc'];


      // FIXME: should we be using author's "Academic Plan" field from ESD here? (at least as first choice?)
      
      // attempt to find a match for department from program list
      $xml = new DOMDocument();
      $xml->load("../config/programs.xml"); 
      $programs = new programs($xml);
      $department = $programs->findLabel($this->view->etd_info['department']);
      if ($department)
	$etd->mods->department = $department;

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
      if ($pid) {
	// need to retrieve record from fedora so datastreams can be saved, etc.
	$etd = new etd($pid);
	
	// could use fullname as agent id, but netid seems more userful
	//FIXME: use full/display name here for user identity
	$etd->premis->addEvent("ingest", "Record created by " . $identity->fullname, "success",
			       array("netid", $identity));
	$etd->save();

	$this->_helper->flashMessenger->addMessage("Saved etd to fedora with pid $pid");

	// only create the etdfile object if etd was successfully created
	$etdfile = new etd_file();
	$etdfile->label = "Dissertation";	// FIXME: what should this be?
	$etdfile->file->url = fedora::upload($etd_info['pdf']);
	$etdfile->file->mimetype = $etdfile->dc->type = "application/pdf";
	// fixme: any other settings we can/should do?
	
	// add relations between objects
	//      $etdfile->rels_ext->addRelationToResource("rel:owner", $user->pid);
	$etdfile->rels_ext->addRelationToResource("rel:isPDFOf", $etd->pid);
	$filepid = $etdfile->save("creating record from uploaded pdf");	// save and get pid
	$this->_helper->flashMessenger->addMessage("Saved etdfile to fedora with pid $filepid");
	
	
	// add relation to etd object and save changes
	$etd->rels_ext->addRelationToResource("rel:hasPDF", $filepid);
	$etd->save("added relation to uploaded pdf");
	
	$this->_helper->redirector->gotoRoute(array("controller" => "view",
						    "action" => "record",
						    "pid" => $pid));
	
      } else {
	$this->_helper->flashMessenger->addMessage("Error saving etd to fedora");
	$this->view->foxml = $etd->saveXML();
      }

    } else {
      // error finding title; ask user if pdf is in correct format...

      $this->view->messages = $this->_helper->flashMessenger->getCurrentMessages();
    }
  }

  public function debugPdfAction() {
    Zend_Controller_Action_HelperBroker::addPrefix('Etd_Controller_Action_Helper');
    
    $this->view->messages = array();
    $env = Zend_Registry::get('env-config');
    $this->view->site_mode = $env->mode;	// better name for this? (test/dev/prod)
     
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


    $this->view->messages = $this->_helper->flashMessenger->getCurrentMessages();
    
  }

  public function reviewAction() {
    // double-check that etd is ready to submit?
    $this->view->etd = new etd($this->_getParam("pid"));	// fixme: error handling if pid is not specified?
    // probably also need user record...

    $this->view->title = "final review";
    
    if ($this->view->current_user) {
      // don't retrieve from Fedora again if we already have it 
      $this->view->user = $this->view->current_user;
    }
  }

  public function submitAction() {
    // fixme: double-check that etd is ready to submit
    
    $etd = new etd($this->_getParam("pid"));
    $newstatus = "submitted";
    $etd->setStatus($newstatus);

    // log event in record history (fixme: better way of getting user?)
    $user = $this->view->current_user;
    $etd->premis->addEvent("status change",
			   "Submitted for Approval by " .
			   $user->firstname . " " . $user->lastname,
			   "success",  array("netid", $user->netid));
    
    $result = $etd->save("set status to '$newstatus'");
    // fixme: more student-readable message here
    $this->_helper->flashMessenger->addMessage("Record status changed to <b>$newstatus</b>; record saved at $result");

    // ?

    $notify = new etd_notifier($etd);
    $notify->submission();

    $etd->premis->addEvent("notice",
			   "Submission Notification sent by ETD system",
			   "success",  array("software", "etd system"));
    $result = $etd->save("notification event");
    
    $this->_helper->flashMessenger->addMessage("Submission notification email sent");

    
    // forward to .. my etds page ?
    $this->_helper->redirector->gotoRoute(array("controller" => "browse",
						 "action" => "my"), "", true); 


  }

  
  //  other actions:
  // review, submit, addfile (upload/enter info)
  // check required fields/files/etc
   
}