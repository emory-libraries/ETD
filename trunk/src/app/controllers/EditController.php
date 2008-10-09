<?php

require_once("models/etd.php");
require_once("models/programs.php");
require_once("models/researchfields.php");

class EditController extends Etd_Controller_Action {

  protected $requires_fedora = true;
  
  // edit main record metadata
  public function recordAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return false;
      
    $this->view->title = "Edit Record Information";
    $this->view->etd = $etd;
    // xforms setting - so layout can include needed code in the header
    $this->view->xforms = true;
    $this->view->xforms_bind_script = "edit/_mods_bind.phtml";
    $this->view->namespaces = array("mods" => "http://www.loc.gov/mods/v3",
				    "etd" => "http://www.ndltd.org/standards/metadata/etdms/1.0/",
				    );
    //    $this->view->xforms_model_xml = $etd->mods->saveXML();
    // link to xml rather than embedding directly in the page
    $this->view->xforms_model_uri = $this->_helper->url->url(array("controller" => "view", "action" => "mods",
								   "pid" => $etd->pid, "mode" => "edit"),
							     // no route name, reset, don't url-encode
							     '', true, false);
  }

  public function programAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;
    
    $this->view->title = "Edit Program";
    $this->view->etd = $etd;

    $programs = new programs();
    $id = $programs->findIdbyLabel(trim($etd->mods->department));
    if ($id)	// FIXME: what if id is not found ?
      $this->view->program = new programs($id);

    
    // xforms setting - so layout can include needed code in the header
    $this->view->xforms = true;
    $this->view->xforms_bind_script = "edit/_program_bind.phtml";
    $this->view->namespaces = array("mods" => "http://www.loc.gov/mods/v3",
				    "etd" => "http://www.ndltd.org/standards/metadata/etdms/1.0/",
				    "skos" => "http://www.w3.org/2004/02/skos/core#",
				    "rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
				    "rdfs" => "http://www.w3.org/2000/01/rdf-schema#",
				    );
    // link to xml rather than embedding directly in the page
    $this->view->xforms_model_uri = $this->_helper->url->url(array("controller" => "view", "action" => "mods",
								   "pid" => $etd->pid),
							     // no route name, reset, don't url-encode
							     '', true, false);
  }


  public function facultyAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;
    $this->view->title = "Edit Committee Chair(s) & Members";
    $this->view->etd = $etd;
  }

  
  public function savefacultyAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;
    
    $this->view->etd = $etd;

    $chair_ids = $this->_getParam("chair", array());	       
    $committee = $this->_getParam("committee", array());

    $committee_ids = array();
    // don't allow the same id to be added to both chair and member lists (results in duplicate ids ->  invalid xml)
    foreach ($committee as $id) {
      if (in_array($id, $chair_ids)) {	// don't add to committee list, display a message to the user
	$this->_helper->flashMessenger->addMessage("Warning: cannot add '$id' as both Committee Chair and Member; adding as Chair only");
      } else {		// add to the list of committee ids that will be processed
	$committee_ids[] = $id;
      }
    }
    $this->view->committee_ids = $committee_ids;

    // set fields
    $etd->setCommittee($chair_ids, "chair");
    $etd->setCommittee($committee_ids);

    // handle non-emory committee members as well
    $etd->mods->clearNonEmoryCommittee();
    $nonemory_first = $this->_getParam("nonemory_firstname");
    $nonemory_last = $this->_getParam("nonemory_lastname");
    $nonemory_affiliation = $this->_getParam("nonemory_affiliation");
    for ($i = 0; $i < count($nonemory_first); $i++) {
      if ($nonemory_last[$i] != '')
	$etd->mods->addCommittee($nonemory_last[$i], $nonemory_first[$i], "nonemory_committee",
				 $nonemory_affiliation[$i]);
    }
    
    if ($etd->mods->hasChanged()) {
      $save_result = $etd->save("updated committe chair(s) & members");
      $this->view->save_result = $save_result;
      if ($save_result) {
	$this->_helper->flashMessenger->addMessage("Saved changes to committee chairs & members");
	$this->logger->info("Updated etd " . $etd->pid . " committee chairs & members at $save_result");
      } else {
	$this->_helper->flashMessenger->addMessage("Could not save changes to committee chair(s) & members (permission denied?)");
	$this->logger->err("Could not save changes to committee chairs & members on  etd " . $etd->pid);
      }
    } else {
      $this->_helper->flashMessenger->addMessage("No changes made to committee chair(s) & members");
    }


    $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
    						"pid" => $etd->pid), '', true);
  }




  public function rightsAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;

    $this->view->title = "Edit Author Rights/Access Restrictions";

    $this->view->etd = $etd;
    
    // xforms setting - so layout can include needed code in the header
    $this->view->xforms = true;
    $this->view->xforms_bind_script = "edit/_rights_bind.phtml";
    $this->view->namespaces = array("mods" => "http://www.loc.gov/mods/v3",
				    "etd" => "http://www.ndltd.org/standards/metadata/etdms/1.0/");
    // link to xml rather than embedding directly in the page
    $this->view->xforms_model_uri = $this->_helper->url->url(array("controller" => "view", "action" => "mods",
								   "pid" => $etd->pid),
							     // no route name, reset, don't url-encode
							     '', true, false);
  }


  public function researchfieldAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;
    
    $this->view->title = "Edit Research Fields";
    $this->view->etd = $etd;

    $this->view->fields = new researchfields(); 
  }

  public function saveresearchfieldAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;
    
    $this->view->fields = $this->_getParam("fields");

    // construct an array of id => text name
    $values = array();
    foreach ($this->view->fields as $i => $value) {
      if(preg_match("/#([0-9]{4}) (.*)$/", $value, $matches)) {
	$id = $matches[1];
	$text = $matches[2];
	$values[$id] = $text;
      }
    }
    $etd->mods->setResearchFields($values);

    if ($etd->mods->hasChanged()) {
      $save_result = $etd->save("modified research fields");
      $this->view->save_result = $save_result;
      if ($save_result) {
	$this->_helper->flashMessenger->addMessage("Saved changes to research fields");
	$this->logger->info("Updated etd " . $etd->pid . " research fields at $save_result");
      } else {	// record changed but save failed for some reason
	$this->_helper->flashMessenger->addMessage("Could not save changes to research fields");
	$this->logger->err("Could not save etd " . $etd->pid . " research fields");
      }
    } else {
      $this->_helper->flashMessenger->addMessage("No changes made to research fields");
    }

    // return to record (fixme: make this a generic function of this controller? used frequently)
    $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
    						"pid" => $etd->pid), '', true);

  }
  

  // formatted/html fields - edit one at a time 
  public function titleAction() {
    $this->_setParam("mode", "title");
    $this->_forward("html");
  }

  public function abstractAction() {
    $this->_setParam("mode", "abstract");
    $this->_forward("html");
  }
  
  public function contentsAction() {
    $this->_setParam("mode", "contents");
    $this->_forward("html");
  }

  // edit formatted fields
  public function htmlAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    $mode = $this->_getParam("mode", "title");

    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;

    $this->view->mode = $mode;
    $this->view->etd = $etd;
    $this->view->edit_content = $etd->html->{$mode};
    $label = ($mode == "contents") ? "Table of Contents" : ucfirst($mode);
    $this->view->mode_label =  $label;
    $this->view->title = "Edit $label";
  }


  public function savetitleAction() {
    $this->_setParam("mode", "title");
    $this->_forward("savehtml");
  }
  
  public function saveabstractAction() {
    $this->_setParam("mode", "abstract");
    $this->_forward("savehtml");
  }

  public function savecontentsAction() {
    $this->_setParam("mode", "contents");
    $this->_forward("savehtml");
  }

  
  // save formatted fields
  public function savehtmlAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");

    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;

    $mode = $this->_getParam("mode");
    $content = $this->_getParam("edit_content");
    // title/abstract/contents - special fields that set values in html, mods and dublin core
    $etd->$mode = $content;


    // fixme: make this a generic function? similar throughout controller
    if ($etd->html->hasChanged()) {
      $save_result = $etd->save("modified $mode");
      $this->view->save_result = $save_result;
      if ($save_result) {
	$this->_helper->flashMessenger->addMessage("Saved changes to $mode");
	$this->logger->info("Updated etd " . $etd->pid . " html $mode at $save_result");
      } else {	// record changed but save failed for some reason
	$this->_helper->flashMessenger->addMessage("Could not save changes to $mode");
	$this->logger->err("Could not save changes to etd " . $etd->pid . " html $mode");
      }
    } else {
      $this->_helper->flashMessenger->addMessage("No changes made to $mode");
    }

    $this->view->pid = $etd->pid;
    $this->view->mode = $mode;
    $this->view->title = "save $mode";


    // return to record (fixme: make this a generic function of this controller? used frequently)
    $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
    						"pid" => $etd->pid), '', true);

  }


   public function savemodsAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;

    $component = $this->_getParam("component", "record information");	// what just changed
    $log_message = "edited $component";

    global $HTTP_RAW_POST_DATA;
    $xml = $HTTP_RAW_POST_DATA;

    if ($xml == "") {
      // if no xml is submitted, don't modify 
      // forward to a different view?
      $this->view->noxml = true;
    } else {
      $etd->mods->updateXML($xml);
      // update main record in case dept. has changed - set in etd & etdfile xacml, etc.
      $etd->department = $etd->mods->department;
      
      if ($etd->mods->hasChanged()) {
	$save_result = $etd->save($log_message);
	$this->view->save_result = $save_result;
	if ($save_result) {
	  $this->_helper->flashMessenger->addMessage("Saved changes to $component");	// more info?
	  $this->logger->info("Saved etd " . $etd->pid . " changes to $component at $save_result");
	} else {	// record changed but save failed for some reason
	  $message = "Could not save changes to $component";
	  $this->_helper->flashMessenger->addMessage($message);
	  $this->logger->err($message);
	}
      } else {
	$this->_helper->flashMessenger->addMessage("No changes made to $component");
      }
    }


    $this->view->pid = $etd->pid;
    $this->view->xml = $xml;
    $this->view->title = "save mods";

    // return to record (fixme: make this a generic function of this controller? used frequently)
    $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
						"pid" => $etd->pid), '', true);
   }


   /* FIXME: should these two actions have a different action in the ACL, or is this sufficient? */
   
  public function fileorderAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");

    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;
    $this->view->title = "Edit File order";
    $this->view->etd = $etd;
  }

  public function savefileorderAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");

    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;
    
    $order['pdf'] = $this->_getParam("pdfs");
    $order['orig'] = $this->_getParam("originals");
    $order['supp'] = $this->_getParam("supplements");

    foreach ($order as $type) {
      for ($i = 0; $i < count($type); $i++) {
	$file = new etd_file($type[$i]);
	$seq = ($i + 1);
	// only set it if it's changed
	if ($file->rels_ext->sequence != $seq) {
	  $file->rels_ext->sequence = $seq;
	  $result = $file->save("re-ordered in sequence");
	  if ($result) {
	    $this->logger->info("Saved re-ordered file sequence on " . $file->pid);
	  } else {
	    $this->logger->err("Could not saved re-ordered file sequence for " . $file->pid);
	  }
	}
      }
    }
    // error checking ?
    if ($result)
      $this->_helper->flashMessenger->addMessage("Saved changes");	// more info?
    else
      $this->_helper->flashMessenger->addMessage("No changes made");
    
    // return to record (fixme: make this a generic function of this controller? used frequently)
    $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
    						"pid" => $etd->pid), '', true);

    
    $this->_helper->viewRenderer->setNoRender(true);
  }

   
  
}