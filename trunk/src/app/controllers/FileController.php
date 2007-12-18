<?php
/** Zend_Controller_Action */

/* Require models */
require_once("models/etd.php");
require_once("helpers/FileUpload.php");

class FileController extends Zend_Controller_Action {

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
     $etd_pid = $this->_getParam("etd");	// etd record the file should be added to

     $fileinfo = $_FILES['file'];

     Zend_Controller_Action_HelperBroker::addPrefix('Etd_Controller_Action_Helper');
     $tmpdir = "/tmp/etd";
     $filename = $tmpdir . "/" . $fileinfo['name'];
     $uploaded = $this->_helper->FileUpload($fileinfo, $filename);
     
     if ($uploaded) {
       $etdfile = new etd_file();
       $etdfile->label = $fileinfo['name'];
       $etdfile->dc->format = $fileinfo['type'];

       $upload_id = fedora::upload($filename);
       $upload_id = $upload_id;
       print "fedora upload id is $upload_id<br/>\n";
       
       $etdfile->file->url = $upload_id;
       // FIXME: need a way to set what kind of etd file this is (original, pdf, supplement)
       $etdfile->rels_ext->addRelationToResource("rel:isSupplementOf", $etd_pid);

       print "etd file xml:<pre>" . htmlentities($etdfile->saveXML()) . "</pre>";
       $filepid = $etdfile->save("adding new file");
       $this->view->file_pid = $filepid;

       print "uploaded file as $filepid<br/>\n";


       $etd = new etd($etd_pid);
       // FIXME: etd rels shortcut - add supplement, add pdf, etc.
       $etd->rels_ext->addRelationToResource("rel:hasSupplement", $filepid);
       $result = $etd->save("adding new file: " . $etdfile->label);
       $this->view->etd_pid = $etd_pid;
              
       $this->view->save_result = $result;

       // allow user to edit file info

     } else {
       // $this->_forward("addfile");
     }
   }

   // upload and save a new version of the binary file
   public function updateAction() {
     $pid = $this->_getParam("pid");
     $etdfile = new etd_file($pid);

     $this->view->etd_pid = $etdfile->parent->pid;

     $fileinfo = $_FILES['file'];

     Zend_Controller_Action_HelperBroker::addPrefix('Etd_Controller_Action_Helper');
     $tmpdir = "/tmp/etd";
     $filename = $tmpdir . "/" . $fileinfo['name'];
     $uploaded = $this->_helper->FileUpload($fileinfo, $filename);
     if ($uploaded) {
       $result = $etdfile->updateFile($filename, $fileinfo['type'], "new version of file");
       if ($result === false) {
	 $this->_helper->flashMessenger->addMessage("Error: there was a problem saving the record.");
       } else {
	 $this->view->save_result = $result;
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
     $this->view->namespaces = array("dc" => "http://purl.org/dc/elements/1.1/");

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
   }



   public function removeAction() {
     $pid = $this->_getParam("pid");
     $etdfile = new etd_file($pid);

     $this->view->etd_pid = $etdfile->parent->pid;
     
     $result = $etdfile->purge("removed by user");
     $this->_helper->flashMessenger->addMessage("removed file " . $etdfile->label);
     

     $this->view->title = "remove file";
     $this->view->result = $result;
   }
}