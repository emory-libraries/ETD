<?php
/** Zend_Controller_Action */

/* Require models */
require_once("models/etd.php");
require_once("helpers/FileUpload.php");

class EditController extends Zend_Controller_Action {


  public function postDispatch() {
    $this->view->messages = $this->_helper->flashMessenger->getCurrentMessages();

    $env = Zend_Registry::get('env-config');
    $this->view->site_mode = $env->mode;	// better name for this? (test/dev/prod)
  }
  
  // edit main record metadata
  public function recordAction() {
    $pid = $this->_getParam("pid");
    $etd = new etd($pid);
    
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
    $this->view->xforms_model_uri = $this->view->url(array("controller" => "view",
							   "action" => "mods", "pid" => $pid,
							   "mode" => "edit"));
  }

  public function programAction() {
    $pid = $this->_getParam("pid");
    $etd = new etd($pid);
    
    $this->view->title = "Edit Program";
    $this->view->etd = $etd;

    // necessary?
    $xml = new DOMDocument();
    $xml->load("../config/programs.xml"); 
    $programs = new programs($xml);
    $id = $programs->findIdbyLabel(trim($etd->mods->department));
    if ($id)
      $this->view->program = new programs($xml, $id);

    
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
    $this->view->xforms_model_uri = $this->view->url(array("controller" => "view",
							   "action" => "mods", "pid" => $pid));
  }


  public function facultyAction() {
    $pid = $this->_getParam("pid");
    $etd = new etd($pid);

    $this->view->title = "Edit Advisor & Committee Members";
    $this->view->etd = $etd;
  }

  
  public function savefacultyAction() {
    $pid = $this->_getParam("pid");
    $etd = new etd($pid);
    $this->view->etd = $etd;
    
    $advisor_id = $this->_getParam("advisor");
    $committee_ids = $this->_getParam("committee");	//array
    $this->view->committee_ids = $committee_ids;

    // set fields
    $etd->mods->setAdvisor($advisor_id);
    $etd->mods->setCommittee($committee_ids);

    if ($etd->mods->hasChanged()) {
      $save_result = $etd->save("updated advisor & committee members");
      $this->view->save_result = $save_result;
      if ($save_result) 
	$this->_helper->flashMessenger->addMessage("Saved changes to advisor & committee");
      else
      $this->_helper->flashMessenger->addMessage("Could not save changes to advisor & committee (permission denied?)");
    } else {
      $this->_helper->flashMessenger->addMessage("No changes made to advisor & committee");
    }

    // FIXME: why is message not showing up after redirect?
    $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
						"pid" => $pid), '', true);
  }




  public function rightsAction() {
    $pid = $this->_getParam("pid");
    $etd = new etd($pid);

    $this->view->title = "Edit Author Rights/Access Restrictions";

    $this->view->etd = $etd;
    
    // xforms setting - so layout can include needed code in the header
    $this->view->xforms = true;
    $this->view->xforms_bind_script = "edit/_rights_bind.phtml";
    $this->view->namespaces = array("mods" => "http://www.loc.gov/mods/v3");
    // link to xml rather than embedding directly in the page
    $this->view->xforms_model_uri = $this->view->url(array("controller" => "view",
							   "action" => "mods", "pid" => $pid));
  }


  public function researchfieldAction() {
    $pid = $this->_getParam("pid");
    $etd = new etd($pid);
    
    $this->view->title = "Edit Research Fields";
    $this->view->etd = $etd;

    // necessary?
    $xml = new DOMDocument();
    $xml->load("../config/umi-researchfields.xml"); 
    $this->view->fields = new researchfields($xml); 
  }

  public function saveResearchfieldAction() {
    $pid = $this->_getParam("pid");
    $etd = new etd($pid);
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
    $save_result = $etd->save("modified UMI/ProQuest research fields");
    $this->view->save_result = $save_result;
    if ($save_result) 
      $this->_helper->flashMessenger->addMessage("Saved changes to research fields");
    else
      $this->_helper->flashMessenger->addMessage("No changes made to research fields");

    $this->view->etd = $etd;
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
    $pid = $this->_getParam("pid");
    $mode = $this->_getParam("mode", "title");
    $etd = new etd($pid);

    $this->view->mode = $mode;
    $this->view->etd_title = $etd->mods->title;
    $this->view->edit_content = $etd->html->{$mode};
    $this->view->title = "edit $mode";
  }


  public function saveTitleAction() {
    $this->_setParam("mode", "title");
    $this->_forward("saveHtml");
  }
  
  public function saveAbstractAction() {
    $this->_setParam("mode", "abstract");
    $this->_forward("saveHtml");
  }

  public function saveContentsAction() {
    $this->_setParam("mode", "contents");
    $this->_forward("saveHtml");
  }

  
  // save formatted fields
  public function saveHtmlAction() {
    $pid = $this->_getParam("pid");
    $mode = $this->_getParam("mode");
    $content = $this->_getParam("edit_content");

    // FIXME: FCKeditor is adding divs around the edges, sometimes paragraphs...
    // this clean-up should probably be in etd_html class, not here
    $content = preg_replace("|^\s*<div>\s*(.*)\s*</div>\s*$|", "$1", $content);
    if ($mode == "title") {
      $content = preg_replace("|^\s*<p>\s*(.*)\s*</p>\s*$|", "$1", $content);
    }
    
    $etd = new etd($pid);

    // title/abstract/contents - special fields that set values in html, mods and dublin core
    $etd->$mode = $content;

    $save_result = $etd->save("modified $mode");
    $this->view->save_result = $save_result;
    $this->_helper->flashMessenger->addMessage("Saved changes to $mode");

    $this->view->pid = $pid;
    $this->view->mode = $mode;
    $this->view->title = "save $mode";
  }


   public function savemodsAction() {
    $pid = $this->_getParam("pid");
    $log_message = $this->_getParam("log", "edited record information");
    $etd = new etd($pid);

    
     global $HTTP_RAW_POST_DATA;
     $xml = $HTTP_RAW_POST_DATA;

     print_r($xml);

    if ($xml == "") {
      // if no xml is submitted, don't modify 
      // forward to a different view?
      $this->view->noxml = true;
    } else {
      $etd->mods->updateXML($xml);
      $this->view->save_result = $etd->save($log_message);
      $this->_helper->flashMessenger->addMessage("Saved changes");
    }


    $this->view->pid = $pid;
    $this->view->xml = $xml;
    $this->view->title = "save mods";
   }




  
}