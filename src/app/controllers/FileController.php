<?php
/** Zend_Controller_Action */

/* Require models */
require_once("models/etd.php");
require_once("helpers/FileUpload.php");
require_once("helpers/PdfPageTotal.php");

class FileController extends Zend_Controller_Action {

  public function init() {
    Zend_Controller_Action_HelperBroker::addPrefix('Etd_Controller_Action_Helper');
  }

  
  public function postDispatch() {
    $this->view->messages = $this->_helper->flashMessenger->getCurrentMessages();

    $env = Zend_Registry::get('env-config');
    $this->view->site_mode = $env->mode;	// better name for this? (test/dev/prod)
  }


   // serve out a file attached to an ETD record from fedora
   public function viewAction() {

     // FIXME: write routes or set headers so file will save with original filename
     
     $pid = $this->_getParam("pid");
     $etdfile = new etd_file($pid);

     //     $this->getResponse()->setHeader('Content-Type', $etdfile->dc->type);
     $this->getResponse()->setHeader('Content-Disposition',
				     'attachment; filename="' . $etdfile->dc->description);

     
     $fedora = Zend_Registry::get('fedora-config');
     $url = "http://" . $fedora->server . ":" . $fedora->port . "/fedora/get/$pid/FILE";

     // do a redirect so the file doesn't get loaded into memory by php
     $this->_redirect($url);
     // for debugging redirect url:
     //    $this->_helper->viewRenderer->setNoRender(true);

   }


  
  // add file to an etd record

   public function addAction() {
     $etd_pid = $this->_getParam("etd");	// etd record the file should be added to

     $etd = new etd($etd_pid);
     $this->view->pid = $etd_pid;
     $this->view->etd = $etd;
     
    //upload file first, then edit metadata
   }


   // upload a file and create a new etdfile
   public function newAction() {
     $etd_pid = $this->_getParam("etd");	// etd record the file belongs to
     $etd = new etd($etd_pid);		
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
       $etdfile = new etd_file();
       $etdfile->owner = $this->view->current_user->netid;
       $etdfile->label = $fileinfo['name'];

       $finfo = finfo_open(FILEINFO_MIME);	// fixme: pull out into a helper?
       if ($finfo) {
	 $filetype = finfo_file($finfo, $filename);	// don't trust mimetype reported by the browser
	 $etdfile->dc->format[0] = $filetype;	// $fileinfo['type'];	// mimetype
	 $etdfile->file->mimetype = $filetype;

	 if ($filetype == "application/pdf")
	   // get number of pages for pdfs (especially important for pdf, but okay for supplements too)
	   $etdfile->dc->setPages($this->_helper->PdfPageTotal($filename));

       }
       // since mimetype from upload info is not reliable, don't rely on that for size either
       $etdfile->dc->setFilesize(filesize($filename));	// file size in bytes

       $etdfile->setFile($filename);	// upload and set ingest url to upload id

       // add relation to etd
       // fixme: this should probably be a function of etdfile or rels
       $etdfile->rels_ext->addRelationToResource("rel:is{$relation}Of", $etd->pid);
       $filepid = $etdfile->save("adding new file");
       $this->_helper->flashMessenger->addMessage("Saved etd file object as $filepid");
       $this->view->file_pid = $filepid;



       //FIXME: not working - not getting added to etd
       // add relation to etd object as well
       switch($file_rel) {
       case "pdf": $result = $etd->addPdf($etdfile); break;
       case "original":   $result = $etd->addOriginal($etdfile); break;
       case "supplement": $result = $etd->addSupplement($etdfile); break;
       default:
	 trigger_warning("relation '$relation' not recognized - not adding to etd", E_USER_WARNING);
      } 
       if ($result)
	 $this->_helper->flashMessenger->addMessage("Added file to etd as $relation - updated at $result");
       else 
	 $this->_helper->flashMessenger->addMessage("Error: could not add file to etd as $relation");

       // direct user to edit file info
       //       $this->_helper->redirector->gotoRoute(array("controller" => "file", "action" => "edit",
       //						   "pid" => $etdfile->pid, 'etd' => $etd->pid), '', true);
       
     } else {
       // upload failed (what should happen here?)  - there should be error messages from file upload failure
       $this->_forward("addfile");
     }
   }

   // upload and save a new version of the binary file
   public function updateAction() {
     $pid = $this->_getParam("pid");
     $etdfile = new etd_file($pid);

     // pass on pids for links to etdfile and etd record
     $this->view->file_pid = $pid;
     $this->view->etd_pid = $etdfile->parent->pid;
     $this->view->etd = $etdfile->parent;

     $fileinfo = $_FILES['file'];

     Zend_Controller_Action_HelperBroker::addPrefix('Etd_Controller_Action_Helper');
     $tmpdir = "/tmp/etd";
     $filename = $tmpdir . "/" . $fileinfo['name'];
     $uploaded = $this->_helper->FileUpload($fileinfo, $filename);
     if ($uploaded) {

       $finfo = finfo_open(FILEINFO_MIME);	// fixme: pull out into a helper?
       if ($finfo) {
	 $filetype = finfo_file($finfo, $filename);	// don't trust mimetype reported by the browser
	 $etdfile->dc->mimetype = $filetype;		 // mimetype 

	 if ($filetype == "application/pdf") {
	   // get number of pages for pdfs (especially important for pdf, but okay for supplements too)
	   $pages = $this->_helper->PdfPageTotal($filename);

	   // if a pdf of the dissertation & page total changed, adjust page number total for the etd record
	   if ($etdfile->type == "pdf" && $pages != $etdfile->dc->pages) {
	     $pagediff = $pages - $etdfile->dc->pages;
	     $etdfile->parent->mods->pages += $pagediff;	// adjust page # for parent etd record
	     $etdfile->parent->save("updated page total");
	   }
	   $etdfile->dc->setPages($pages);

	 }

       }
       // since mimetype from upload info is not reliable, don't rely on that for size either
       $etdfile->dc->setFilesize(filesize($filename));	// file size in bytes
       
       $fileresult = $etdfile->updateFile($filename, $filetype, "new version of file");
       $xmlresult = $etdfile->save("modified metadata for new version of file");
       if ($fileresult === false || $xmlresult === false) {	// how to determine which failed?
	 $this->_helper->flashMessenger->addMessage("Error: there was a problem saving the record.");
       } else {
	 $this->view->save_result = $fileresult;
       }
       $this->_helper->viewRenderer->setScriptAction("new");
     } else {
       // problem with file - redirect somewhere?
     }
     
   }

   
   // edit file metadata
   public function editAction() {
     $pid = $this->_getParam("pid");
     $etdfile = new etd_file($pid);

     $this->view->title = "Edit File Information";
     $this->view->etdfile = $etdfile;
     $this->view->pid = $pid;

     // xforms setting - so layout can include needed code in the header
     $this->view->xforms = true;
     $this->view->namespaces = array("dc" => "http://purl.org/dc/elements/1.1/",
				     "dcterms" => "http://purl.org/dc/terms/");

     $this->view->xforms_bind_script = "file/_dc_bind.phtml";
     
     //    $this->view->xforms_model_xml = $etd->mods->saveXML();
     // link to xml rather than embedding directly in the page
     $this->view->xforms_model_uri = $this->view->url(array("controller" => "view",
							    "action" => "dc", "pid" => $pid, "type" => "etdfile"));
   }


   // save file metadata 
   public function saveAction() {
     $pid = $this->_getParam("pid");
     // FIXME: if pid is not defined, need to error out
     $etdfile = new etd_file($pid);

     global $HTTP_RAW_POST_DATA;
     $xml = $HTTP_RAW_POST_DATA;

     // FIXME: if dc:title is changed, should label change? (possible to save changed fedora label?)
     if ($xml == "") {
       // if no xml is submitted, don't modify 
       // forward to a different view?
       $this->view->noxml = true;
     } else {
       $etdfile->dc->updateXML($xml);

       // fixme: needs better error checking (like in EditController)
       $save_result = $etdfile->save("edited file information");
       $this->view->save_result = $save_result;
       if ($save_result)
	 $this->_helper->flashMessenger->addMessage("Saved changes");
       else 
	 $this->_helper->flashMessenger->addMessage("No changes made");
    }


     $this->view->etd_pid = $etdfile->parent->pid;
    $this->view->pid = $pid;
    //    $this->view->xml = $xml;
    $this->view->xml = $etdfile->dc->saveXML();
    $this->view->title = "save file information";

    // redirect to etd record... (?) or to view file record ?
   }



   public function removeAction() {
     $pid = $this->_getParam("pid");
     $etdfile = new etd_file($pid);

     $this->view->etd_pid = $etdfile->parent->pid;
     $etdfile->parent->removeFile($etdfile);	// remove from parent etd
     
     $result = $etdfile->purge("removed by user");
     $this->_helper->flashMessenger->addMessage("removed file " . $etdfile->label);
     

     $this->view->title = "remove file";
     $this->view->result = $result;
   }
}