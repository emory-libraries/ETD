<?php
/** Zend_Controller_Action */

/* Require models */
require_once("models/etd.php");
require_once("helpers/ProcessPDF.php");


class SubmissionController extends Zend_Controller_Action {

  public function init() {
    Zend_Controller_Action_HelperBroker::addPath('../library/Emory/Controller/Action/Helper',
						 'Emory_Controller_Action_Helper');
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
      print "title is not  blank, creating new etd and setting values<br/>\n";

      // create new etd record and save all the fields
      $etd = new etd();
      $etd->title = $etd_info['title'];

      // fixme: need a better way of doing this...
      $auth = Zend_Auth::getInstance();
      if ($auth->hasIdentity()) {
	$identity = $auth->getIdentity();
	$etd->owner = $identity;
      }

      $etd->abstract = $etd_info['abstract'];
      $etd->contents = $etd_info['toc'];
      // FIXME: need to find a match from controlled vocab list
      $etd->mods->department = $etd_info['department'];
      // fixme: also set degree discipline from department?

      // FIXME: need to find names from faculty list
      $etd->mods->advisor->full = $etd_info['advisor'];
      foreach ($etd_info['committee'] as $i => $member) {
	if (array_key_exists($i, $etd->mods->committee)) {
	  $etd->mods->committee[$i]->full = $member;
	} else {
	  // need a way to add committee members....
	}
      }

      foreach ($etd_info['keywords'] as $i => $keyword) {
	if (array_key_exists($i, $newetd->mods->keywords))
	  $etd->mods->keywords[$i]->topic = $keyword;
	else 
	  $etd->mods->addKeyword($keyword);
      }

      // create an etd file object for uploaded PDF and associate with etd record
      $pid = $etd->save();

      $etdfile = new etd_file();
      $etdfile->label = "Dissertation";
      $etdfile->file->url = fedora::upload($etd_info['pdf']);
      $etdfile->file->mimetype = $etdfile->dc->type = "application/pdf";
      // fixme: any other settings we can/should do?

      // add relations between objects
      //      $etdfile->rels_ext->addRelationToResource("rel:owner", $user->pid);
      $etdfile->rels_ext->addRelationToResource("rel:isPDFOf", $etd->pid);
      $filepid = $etdfile->save();	// save and get pid

      // add relation to etd object and save changes
      $etd->rels_ext->addRelationToResource("rel:hasPDF", $filepid);
      $etd->save();
  
      $this->_helper->redirector->gotoRoute(array("controller" => "view",
						  "action" => "record",
						  "pid" => $pid));
    } else {
      // error finding title; ask user if pdf is in correct format...
    }
  }

  public function debugPdfAction() {
    Zend_Controller_Action_HelperBroker::addPrefix('Etd_Controller_Action_Helper');
    
    $this->view->messages = array();
    $env = Zend_Registry::get('env-config');
    $this->view->site_mode = $env->mode;	// better name for this? (test/dev/prod)
     
    $this->view->etd_info = $this->_helper->processPDF($_FILES['pdf']);
    $this->view->messages = $this->_helper->flashMessenger->getCurrentMessages();
    
  }

  //  other actions:
  // review, submit, addfile (upload/enter info)
  // check required fields/files/etc
   
}