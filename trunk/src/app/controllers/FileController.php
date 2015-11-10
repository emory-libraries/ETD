<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

require_once("models/etd.php");
require_once("models/datastreams/etdfile.php");

class FileController extends Etd_Controller_Action {

  protected $requires_fedora = true;


  public function preDispatch() {
    // suppress sidebar search & browse navigation for all file actions
    // (which are primarily used during the submission process) for any user
    $this->view->suppress_sidenav_browse = true;
  }

  //  serve out a file attached to an ETD record from fedora
   public function viewAction() {

     // don't use layout or templates
     $this->_helper->layout->disableLayout();
     $this->_helper->viewRenderer->setNoRender(true);

     $etdfile = $this->_helper->getFromFedora("pid", "etd_file");

     if (!$this->_helper->access->allowedOnEtdFile("view", $etdfile)){
       return false;
     } else{
       // Short Term Fix:
       // check embargo dates and etd status before serving out any content
       $embargo = $this->_helper->getFromFedora("etd", "etd");
       $embargo = $embargo->mods->embargo_end;
       $time_elasped = (strtotime($this->mods->embargo_end, 0) > time());
       // If the time elasped from the embargo end time is negative, then the
       // embargo has not yet passed and the file should still be embargoed.

       if($time_elasped<0){
         return false;
       }
     }



     // fix headers for IE bug (otherwise file download doesn't work over https)
     $this->getResponse()->setHeader('Cache-Control', "public", true);  // replace any other cache-control values
     $this->getResponse()->setHeader('Pragma', "public", true);         // replace any other pragma values

     // set content type, filename, and set datastream content as the body of the response
     $this->getResponse()->setHeader('Content-Type', $etdfile->file->mimetype);
       // override the aggressive past expiration date set by php cache
     $this->getResponse()->setHeader('Expires', date(DATE_RFC1123, strtotime('+1 year')), true);
       // use datastream last-modification date, if available
     $this->getResponse()->setHeader('Last-Modified', date(DATE_RFC1123, strtotime($etdfile->file->last_modified)));
     $this->getResponse()->setHeader("Content-Transfer-Encoding","binary");
     // if we have a checksum for the datastream, use that for the ETag
     if ($etdfile->file->checksum != 'none') {
       $this->getResponse()->setHeader('ETag', $etdfile->file->checksum);
     }

     // send a not-modified response if we can
     $modified_since = $this->getRequest()->getHeader('If-Modified-Since', '');
     $no_match = $this->getRequest()->getHeader('If-None-Match', '');

     // if either header is set and content has not changed, return 304 Not Modified
     if ( ($modified_since &&
           strtotime($etdfile->file->last_modified) <= strtotime($modified_since))
          || ($no_match && $etdfile->file->checksum != 'none'
               && ($no_match == $etdfile->file->checksum)) ){
       $this->getResponse()->setHttpResponseCode(304);
     } else {
       // if this is a HEAD request, add the file content & download header
       if ($this->getRequest()->isGet()) {
         $this->getResponse()->setHeader("Content-Description","File Transfer");
         $this->getResponse()->setHeader("Content-Transfer-Encoding","binary");
         $this->getResponse()->setHeader('Content-Disposition', 'attachment; filename="' . $etdfile->prettyFilename() . '"');
         $this->getResponse()->sendHeaders(); // Need to send the HttpHeaders to handle a large file size
         $this->getResponse()->setBody($etdfile->getFile());
       }
     }
   }

  // add file to an etd record
   public function addAction() {
     // etd record the file should be added to
     $etd = $this->_helper->getFromFedora("etd", "etd");
     if (!$this->_helper->access->allowedOnEtd("add file", $etd)) return false;

     $this->view->pid = $etd->pid;
     $this->view->etd = $etd;
     $this->view->title = "Add File to Record";
     // allow setting a default file type to pre-select in the drop-down
     $this->view->default_filetype = $this->_getParam("type", null);

     // upload file first, then edit metadata
   }


   // upload a file and create a new etdfile
   public function newAction() {
     $etd = $this->_helper->getFromFedora("etd", "etd");     // etd record the file belongs to

     // creating a new etdfile is part of adding a file to an etd
     if (!$this->_helper->access->allowedOnEtd("add file", $etd)) return false;
     $this->view->etd = $etd;

     // how this file is related to the to the etd record
     $file_rel = $this->_getParam("filetype");
     $allowed_types = null;
     $disallowed_types = array();
     $relation = ucfirst($file_rel);
     switch($file_rel) {
     case "pdf":  $relation = "PDF"; $allowed_types = array("application/pdf"); break;
     // original and supplement use the relation as set above
     case "original": $disallowed_types = array("application/pdf"); break;
     case "supplement":
       break;
     default:
       trigger_error("Unknown etd file relation: $file_rel", E_USER_WARNING);
     }

     $fileinfo = $_FILES['file'];
     $filename = $fileinfo['tmp_name'];

     # The PHP mime magic is broken so we use a bit of a brute force hammer here to get the mimetype
     $fileinfo['type'] = exec('/usr/local/bin/file -b --mime-type -m /usr/local/share/file/magic ' . $fileinfo['tmp_name']);

     $filetype = $fileinfo['type'];
     $this->logger->info("******file type is " . $filetype . " using " . getenv('MAGIC_MIME_PATH'));
     $this->logger->info($fileinfo['name'] . " has a mimetype of " . $fileinfo['type']);

     // check that the file uploaded correctly; check allowed/disallowed types, if any
     $uploaded = $this->_helper->FileUpload->check_upload($fileinfo, $allowed_types, $disallowed_types);

     if ($uploaded) {
       // compare MD5 for new file to existings files attached to this ETD, to avoid duplicate files
       $newfile_checksum = md5_file($filename);
       foreach (array_merge($etd->pdfs, $etd->originals, $etd->supplements) as $file) {
         if ($file->getFileChecksum() == $newfile_checksum) {
           $this->_helper->flashMessenger->addMessage("Error: You have already uploaded this file (" .
                        $fileinfo['name'] . ") before.");
           $this->_helper->redirector->gotoRoute(array("controller" => "file", "action" => "add",
                         "etd" => $etd->pid), '', true);
           return;
         }
       }
       $etdfile = new etd_file(null, $etd); // initialize from template, but associate with parent etd

       $esd = new esdPersonObject();
       $owner = $esd->findByUsername($etd->owner);
       if ($owner == null){
         //we have to keep the same netid at all cost!
         if (isset($etd->authorInfo)){
           $owner = $esd->initializeWithoutEsd($etd->owner);
           $owner->firstname = $etd->authorInfo->mads->name->first;
           $owner->lastname = $etd->authorInfo->mads->name->last;
           }else{
           $this->_helper->flashMessenger->addMessage("Error: Could not identify user, file not attached to ETD");
           return;
           }
       }

       $etdfile->initializeFromFile($filename, $file_rel, $owner, $fileinfo['name']);

       // add relation to etd
       // FIXME: this should probably be a function of etdfile or rels
       $etdfile->rels_ext->addRelationToResource("rel:is{$relation}Of", $etd->pid);

       $filepid = $this->_helper->ingestOrError($etdfile,
            "adding new file",
            "file record", $errtype);
       if ($filepid) {
   $this->logger->info("Created new etd file record with pid $filepid");
   $this->view->file_pid = $filepid;


   // add relation to etd object as well
   switch($file_rel) {
   case "pdf":    $etd->addPdf($etdfile); break;
   case "original":   $etd->addOriginal($etdfile); break;
   case "supplement": $etd->addSupplement($etdfile); break;
   default:
     trigger_warning("relation '$relation' not recognized - not adding to etd", E_USER_WARNING);
   }

   // note: saving etd will also save any changed related objects (etdfile rels & xacml)
   $result = $etd->save("added relation to uploaded $file_rel");
   if ($result) {
     $this->logger->info("Updated etd " . $etd->pid . " at $result (adding relation to pdf)");
   } else {
     $this->_helper->flashMessenger->addMessage("Error: problem  associating new $relation file with your ETD record");
     $this->logger->err("Error updating etd " . $etd->pid . " (adding relation to pdf)");
   }


     // delete temporary file now that we are done with it
   unlink($filename);

   // direct user to edit file info
   $this->_helper->redirector->gotoRoute(array("controller" => "file", "action" => "edit",
                 "pid" => $etdfile->pid, 'etd' => $etd->pid), '', true);
       }  // end successfully ingested file


     } else {
       // upload failed - should be error messages from file upload helper
       $this->_helper->redirector->gotoRoute(array("controller" => "file", "action" => "add",
                                                               "etd" => $etd->pid), '', true);
     }
   }

   // upload and save a new version of the binary file
   public function updateAction() {
     $pid = $this->_getParam('pid');


     $this->logger->info("Getting file " . $pid);

     $etdfile = $this->_helper->getFromFedora('pid', "etd_file");
     // print_r ( $etdfile->file->mimetype );
     if (!$this->_helper->access->allowedOnEtdFile("edit", $etdfile)) return false;

     // pass on pids for links to etdfile and etd record
     $this->view->file_pid = $etdfile->pid;
     $this->view->etd_pid = $etdfile->etd->pid;
     $this->view->etd = $etdfile->etd;

     $fileinfo = $_FILES['file'];

     # The PHP mime magic is broken so we use a bit of a brute force hammer here to get the mimetype
     $fileinfo['type'] = exec('/usr/local/bin/file -b --mime-type -m /usr/local/share/file/magic ' . $fileinfo['tmp_name']);

     $this->logger->info($fileinfo['name'] . " has a mimetype of " . $fileinfo['type']);

     Zend_Controller_Action_HelperBroker::addPrefix('Etd_Controller_Action_Helper');
     $filename = $fileinfo['tmp_name'];
     $mimetype = $fileinfo['type'];

     $new_parts = explode(".", $fileinfo['name']);
     $new_ext = $new_parts[count($new_parts)-1];

     switch (rtrim($new_ext, "x")){
       case "doc":
          $allowable = $fileinfo['type'] == "application/vnd.openxmlformats-officedocument.wordprocessingml.document" || strpos($fileinfo['type'], 'msword');
          break;

       case "xls":
          $allowable = $fileinfo['type'] == "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" || strpos($fileinfo['type'], 'excel');
          break;

       case "ppt":
          $allowable = $fileinfo['type'] == "application/vnd.openxmlformats-officedocument.presentationml.presentation" || strpos($fileinfo['type'], 'powerpoint');
          break;
     }

     if ($etdfile->type == "pdf") {
       $allowed_types = array("application/pdf");
       $uploaded = $this->_helper->FileUpload->check_upload($fileinfo, $allowed_types);
     } elseif ($fileinfo['type'] == $etdfile->file->mimetype || $allowable) {
       $uploaded = $this->_helper->FileUpload->check_upload($fileinfo);
     } else {
       $this->_helper->flashMessenger->addMessage("Error: you cannot replace a file with a different file type.");
       $uploaded = false;
       }


     if ($uploaded) {
       $old_pagecount = $etdfile->dc->pages;  // save current page count
       $fileresult = $etdfile->updateFile($filename, "New version of file ", $mimetype);  // update file info, upload new file
       $xmlresult = $etdfile->save("modified metadata for new version of file");

       if ($fileresult === false || $xmlresult === false) { // how to determine which failed?
           $this->_helper->flashMessenger->addMessage("Error: there was a problem updating the file.");
           if ($fileresult === false) {
                 $this->logger->err("Problem updating etdfile " . $etdfile->pid . " with new version of file");
           }
           if ($xmlresult === false) {
             $this->logger->err("Problem saving modified metadata for etdfile " . $etdfile->pid);
           }
       } else {
         $this->_helper->flashMessenger->addMessage("Successfully updated file");
         $this->view->save_result = $fileresult;
       }

       // if file being updated is a PDF, need to update main record page count (can't be caught elsewhere)
       if ($etdfile->type == "pdf") {
           // subtract pages from the old version of file
           $this->logger->info("about to subtract " . (string)$old_pagecount . " from " . (string)$etdfile->etd->mods->pages);
           $etdfile->etd->mods->pages = (int)$etdfile->etd->mods->pages - (int)$old_pagecount;
          // add new page count
          $etdfile->etd->mods->pages = (int)$etdfile->etd->mods->pages + (int)$etdfile->dc->pages;
          print_r ( $etdfile->etd->mods->pages );
          $this->logger->info("pages = " . $etdfile->etd->mods->pages);
          $result = $etdfile->etd->save("updated page count for new version of pdf");
          if ($result) {
            $this->logger->info("Updated etd page count for new version of pdf " . $etdfile->etd->pid);
          } else {
            $this->logger->err("Problem updating etd page count for new version of pdf" .
                   $etdfile->etd->mods->pages);
          }
       }

       // delete temporary file now that we are done with it
       unlink($filename);

       $this->_helper->viewRenderer->setScriptAction("new");

     }//end uploaded

     // when updating binary file, redirect to main ETD record page
     // if update was successful or failed, flash messages will be displayed
     $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
             "pid" => $etdfile->etd->pid), '', true);
   }


   /**
    * Edit file metadata.
    *
    * On GET, displays edit form.  On POST, processes form data; if
    * invalid, redisplays edit form with posted data and error
    * message; otherwise, saves record and redirects to ETD this file
    * belongs to.
    */
   public function editAction() {
     $etdfile = $this->_helper->getFromFedora("pid", "etd_file");
     if (!$this->_helper->access->allowedOnEtdFile("edit", $etdfile)) return false;

     $this->view->title = "Edit File Information";
     $this->view->etdfile = $etdfile;
     $this->view->pid = $etdfile->pid;

     $dc_types =  array("Text"  => "Text",
                        "Dataset" => "Dataset",
                        "MovingImage" => "Video",
                        "StillImage" => "Image",
                        "Software" => "Software",
                        "Sound" => "Sound");
     $this->view->type_options = $dc_types;

     // on GET, display edit form (no additional logic)

     // on POST, process form data
     if ($this->getRequest()->isPost()) {
       // Update file DC with posted data;
       // used either to save or redisplay the edit form
       $etdfile->dc->title = $this->_getParam("title", null);
       $etdfile->dc->creator = $this->_getParam("creator", null);
       $etdfile->dc->description = $this->_getParam("description", null);
       $etdfile->dc->type = $this->_getParam("type", null);
       // validate: check title non-empty, type in list
       $errors = array();
       $invalid = false;   // assume valid until we find otherwise
       if (empty($etdfile->dc->title)) {
           $errors['title'] = 'Error: Title must not be empty';
           $invalid = true;
       }
       if (! isset($etdfile->dc->type) || empty($etdfile->dc->type)) {
         $errors['type'] = "Error: Type is required";
         $invalid = true;
       } else if (!array_key_exists($etdfile->dc->type, $dc_types)) {
         $errors['type'] = 'Error: Type "' . $etdfile->dc->type . '" is not recognized';
         $invalid = true;
       }
       if ($invalid) {
         // invalid - redisplay form
         $this->view->errors = $errors;

       } else {
         // valid : save and redirect
         if ($etdfile->dc->hasChanged()) {
           // NOTE: Consider updating fedora object title based on dc:title
           $save_result = $etdfile->save("edited file information");
           $this->view->save_result = $save_result;
           if ($save_result) {
             $this->_helper->flashMessenger->addMessage("Saved changes to file information");
             $this->logger->info("Saved changes to file metadata for " . $etdfile->pid . " at $save_result");
           } else {
             $this->_helper->flashMessenger->addMessage("Error: could not save changes to file information");
             $this->logger->err("Could not save changes to file metadata for " .  $etdfile->pid);
           }
         } else {
           // dc unchanged
           $this->_helper->flashMessenger->addMessage("No changes made to file information");
         }

         // redirect to main etd record
         $this->_helper->redirector->gotoRoute(array("controller" => "view",
                                                     "action" => "record",
                                                     "pid" => $etdfile->etd->pid), '', true);
       } // end save valid data

     } // end POST

   }


   public function removeAction() {
     $etdfile = $this->_helper->getFromFedora("pid", "etd_file");
     if (!$this->_helper->access->allowedOnEtdFile("remove", $etdfile)) return false;

     // lag warning - if syncUpdates is turned off, record will still be displayed briefly
     $lag_warning = ' (It may take a moment to disappear from your record.)';

     $etd_pid = $etdfile->etd->pid;
     // delete - removes from etd it belongs to and marks as deleted, but does not purge
     $result = $etdfile->delete("removed by user");
     if ($result) {
       $this->logger->info("Marked etdFile " . $etdfile->pid . " as deleted at $result");
       $this->_helper->flashMessenger->addMessage("Successfully removed file <b>" . $etdfile->label .
                                                  "</b>$lag_warning");
     } else {
       $this->_helper->flashMessenger->addMessage("Error: could not remove file <b>" . $etdfile->label . "</b>");
       $this->logger->err("Error marking etdFile " . $etdfile->pid . " as deleted");
     }

     // redirect to etd record
     $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
             "pid" => $etd_pid), '', true);
   }
}
