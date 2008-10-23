<?php

require_once("models/etd.php");
require_once("models/etdfile.php");

class FileController extends Etd_Controller_Action {

  protected $requires_fedora = true;
  
   // serve out a file attached to an ETD record from fedora
   public function viewAction() {
     $etdfile = $this->_helper->getFromFedora("pid", "etd_file");
     
     if (!$this->_helper->access->allowedOnEtdFile("view", $etdfile)) return;
     
     // don't use layout or templates
     $this->_helper->layout->disableLayout();
     $this->_helper->viewRenderer->setNoRender(true);

     // fix headers for IE bug (otherwise file download doesn't work over https)
     $this->getResponse()->setHeader('Cache-Control', "public", true);	// replace any other cache-control values
     $this->getResponse()->setHeader('Pragma', "public", true);         // replace any other pragma values
     // set content type, filename, and set datastream content as the body of the response
     $this->getResponse()->setHeader('Content-Type', $etdfile->dc->mimetype);
     $this->getResponse()->setHeader('Content-Disposition',
				     'attachment; filename="' . $etdfile->prettyFilename() . '"');
     $this->getResponse()->setBody($etdfile->getFile());
   }


  
  // add file to an etd record
   public function addAction() {
     // etd record the file should be added to
     $etd = $this->_helper->getFromFedora("etd", "etd");
     if (!$this->_helper->access->allowedOnEtd("add file", $etd)) return;

     $this->view->pid = $etd->pid;
     $this->view->etd = $etd;
     $this->view->title = "Add File to Record";
    //upload file first, then edit metadata
   }


   // upload a file and create a new etdfile
   public function newAction() {
     $etd = $this->_helper->getFromFedora("etd", "etd");     // etd record the file belongs to

     // creating a new etdfile is part of adding a file to an etd
     if (!$this->_helper->access->allowedOnEtd("add file", $etd)) return;
     $this->view->etd = $etd;
     
     // how this file is related to the to the etd record
     $file_rel = $this->_getParam("filetype");
     switch($file_rel) {
     case "pdf":  $relation = "PDF"; break;
     case "original": 
     case "supplement":
       $relation = ucfirst($file_rel);
       break;
     default:
       trigger_error("Unknown etd file relation: $file_rel", E_USER_WARNING);
     }
     
     $fileinfo = $_FILES['file'];

     $tmpdir = "/tmp/etd";
     $filename = $tmpdir . "/" . $fileinfo['name'];
     $uploaded = $this->_helper->FileUpload($fileinfo, $filename);
     
     if ($uploaded) {
       $etdfile = new etd_file(null, $etd);	// initialize from template, but associate with parent etd
       $etdfile->initializeFromFile($filename, $file_rel, $this->current_user);

       // add relation to etd
       // fixme: this should probably be a function of etdfile or rels
       $etdfile->rels_ext->addRelationToResource("rel:is{$relation}Of", $etd->pid);
       try {
	 $filepid = $etdfile->save("adding new file");
       } catch (PersisServiceUnavailable $e) {
	 // ingest relies on persistent id server to generate the pid
	 $this->_helper->flashMessenger->addMessage("Error: could not create new record because Persistent Identifier Service is not available");
	 // redirect to an error page
	 $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "unavailable"));
       }
       // FIXME: catch other errors here - permission? what else?
       
       $this->view->file_pid = $filepid;

       // delete temporary file now that we are done with it
       unlink($filename);

       // add relation to etd object as well
       switch($file_rel) {
       case "pdf": 	  $etd->addPdf($etdfile); break;
       case "original":   $etd->addOriginal($etdfile); break;
       case "supplement": $etd->addSupplement($etdfile); break;
       default:	
	 trigger_warning("relation '$relation' not recognized - not adding to etd", E_USER_WARNING);
      }

       $result = $etd->save("added relation to uploaded $file_rel");
       if (!$result)	// only warn if there is a problem
	 $this->_helper->flashMessenger->addMessage("Error: problem  associating new $relation file with your ETD record");

       // direct user to edit file info
       $this->_helper->redirector->gotoRoute(array("controller" => "file", "action" => "edit",
       						   "pid" => $etdfile->pid, 'etd' => $etd->pid), '', true);
       
     } else {
       // upload failed (what should happen here?)  - there should be error messages from file upload failure
       $this->_forward("add");
     }
   }

   // upload and save a new version of the binary file
   public function updateAction() {
     $etdfile = $this->_helper->getFromFedora("pid", "etd_file");
     if (!$this->_helper->access->allowedOnEtdFile("edit", $etdfile)) return;

     // pass on pids for links to etdfile and etd record
     $this->view->file_pid = $etdfile->pid;
     $this->view->etd_pid = $etdfile->etd->pid;
     $this->view->etd = $etdfile->etd;

     $fileinfo = $_FILES['file'];

     Zend_Controller_Action_HelperBroker::addPrefix('Etd_Controller_Action_Helper');
     $tmpdir = "/tmp/etd";
     $filename = $tmpdir . "/" . $fileinfo['name'];
     $uploaded = $this->_helper->FileUpload($fileinfo, $filename);
     if ($uploaded) {
       $fileresult = $etdfile->updateFile($filename, "new version of file");	// update file info, upload new file
       $xmlresult = $etdfile->save("modified metadata for new version of file");
       if ($fileresult === false || $xmlresult === false) {	// how to determine which failed?
	 $this->_helper->flashMessenger->addMessage("Error: there was a problem saving the record.");
       } else {
	 $this->view->save_result = $fileresult;
       }

       // delete temporary file now that we are done with it
       unlink($filename);

       $this->_helper->viewRenderer->setScriptAction("new");

       // direct user to edit file info
       $this->_helper->redirector->gotoRoute(array("controller" => "file", "action" => "edit",
       						   "pid" => $etdfile->pid, 'etd' => $etd->pid), '', true);
       
     } else {
       // FIXME: problem with file upload - redirect somewhere else? error message?
       $this->_helper->redirector->gotoRoute(array("controller" => "file", "action" => "edit",
       						   "pid" => $etdfile->pid, 'etd' => $etd->pid), '', true);
     }
     
   }

   
   // edit file metadata
   public function editAction() {
     $etdfile = $this->_helper->getFromFedora("pid", "etd_file");
     
     if (!$this->_helper->access->allowedOnEtdFile("edit", $etdfile)) return;

     $this->view->title = "Edit File Information";
     $this->view->etdfile = $etdfile;
     $this->view->pid = $etdfile->pid;

     // xforms setting - so layout can include needed code in the header
     $this->view->xforms = true;
     $this->view->namespaces = array("dc" => "http://purl.org/dc/elements/1.1/",
				     "dcterms" => "http://purl.org/dc/terms/");

     $this->view->xforms_bind_script = "file/_dc_bind.phtml";
     
     //    $this->view->xforms_model_xml = $etd->mods->saveXML();
     // link to xml rather than embedding directly in the page
     $this->view->xforms_model_uri = $this->view->url(array("controller" => "view",
							    "action" => "dc", "pid" => $etdfile->pid,
							    "type" => "etdfile"));
   }


   // save file metadata 
   public function saveAction() {
     $etdfile = $this->_helper->getFromFedora("pid", "etd_file");
     if (!$this->_helper->access->allowedOnEtdFile("edit", $etdfile)) return;

     global $HTTP_RAW_POST_DATA;
     $xml = $HTTP_RAW_POST_DATA;

     // FIXME: if dc:title is changed, should label change? (possible to save changed fedora label?)
     if ($xml == "") {
       // if no xml is submitted, don't modify 
       // forward to a different view?
       $this->view->noxml = true;
     } else {
       $etdfile->dc->updateXML($xml);

       if ($etdfile->dc->hasChanged()) {
	 // fixme: needs better error checking (like in EditController)
	 $save_result = $etdfile->save("edited file information");
	 $this->view->save_result = $save_result;
	 if ($save_result)
	   $this->_helper->flashMessenger->addMessage("Saved changes to file information");
	 else
	   $this->_helper->flashMessenger->addMessage("Error: could not save changes to file information");
       } else {
       	 $this->_helper->flashMessenger->addMessage("No changes made to file information");
       }

    }


     $this->view->etd_pid = $etdfile->etd->pid;
    $this->view->pid = $pid;
    //    $this->view->xml = $xml;
    $this->view->xml = $etdfile->dc->saveXML();
    $this->view->title = "save file information";

    // redirect to etd record
    $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
						"pid" => $etdfile->etd->pid), '', true);
   }


   public function removeAction() {
     $etdfile = $this->_helper->getFromFedora("pid", "etd_file");
     if (!$this->_helper->access->allowedOnEtdFile("remove", $etdfile)) return;

     $etd_pid = $etdfile->etd->pid;
     $etdfile->etd->removeFile($etdfile);	// remove from the etd it belongs to
     
     $result = $etdfile->purge("removed by user");
     if ($result) {	// fixme: filename?
       $this->_helper->flashMessenger->addMessage("Successfully removed file <b>" . $etdfile->label . "</b>");
     } else {
       $this->_helper->flashMessenger->addMessage("Error: could not remove file <b>" . $etdfile->label . "</b>");
     }

     // redirect to etd record
     $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
						 "pid" => $etd_pid), '', true);

     // shouldn't be necessary ...
     $this->view->etd_pid = $etd_pid;
     $this->view->title = "remove file";
     $this->view->result = $result;
   }
}