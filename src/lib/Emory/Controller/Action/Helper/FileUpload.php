<?php

/**
 * contoller helper to check uploads - file uploaded successfully, allowed type, etc.
 * 
 * @category EmoryZF
 * @package Emory_Controller
 * @package Emory_Controller_Helpers
 */
class Emory_Controller_Action_Helper_FileUpload extends Zend_Controller_Action_Helper_Abstract {


  /**
   * helper shortcut - run default action
   */
  public function direct($fileinfo, $newname, $mimetype = null, $disallowed_types = array()) {
    return $this->get_upload($fileinfo, $newname, $mimetype, $disallowed_types);
  }
  
  /**
   *
   * @param array $fileinfo relevant information (for this particular) from the $_FILES global
   * @param string $newname new filename and path where temporary file should be moved
   * @param array $mimetype (optional) mimetype(s) that upload file should be restricted to
   */
  public function get_upload($fileinfo, $newname, $mimetype = null, $disallowed_types = array()) {
    if ($this->check_upload($fileinfo, $mimetype, $disallowed_types))
      return $this->move_file($fileinfo, $newname);
    else return false;
  }


  /**
   *
   * @param array $fileinfo relevant information (for this particular) from the $_FILES global
   * @param string $newname new filename and path where temporary file should be moved
   * @param array $mimetype (optional) mimetype(s) that upload file should be restricted to
   * @return boolean success 
   */
  public function check_upload($fileinfo, $mimetype = null, $disallowed_types = array()) {

    $flashMessenger = $this->_actionController->getHelper('FlashMessenger');

    $error = false;

    if ($fileinfo['size'] == 0) {
      $flashMessenger->addMessage("Error: file is zero size.");
      $error = true;
    }

    // detect any error on file upload
    if ($fileinfo['error'] != UPLOAD_ERR_OK) {
      $error = true;
      switch($fileinfo['error']) {
      case UPLOAD_ERR_INI_SIZE:
      case UPLOAD_ERR_FORM_SIZE:
        $msg = "Error: file is too large."; break;
      case UPLOAD_ERR_PARTIAL:
        $msg = "Error: file was only partially uploaded."; break;
      case UPLOAD_ERR_NO_FILE:
        $msg = "Error: no file was uploaded."; break;
      case UPLOAD_ERR_NO_TMP_DIR:
        $msg = "Error: missing temporary folder."; break;
      case UPLOAD_ERR_CANT_WRITE:
        $msg = "Error: failed to write file to disk."; break;
      default:
        $msg = "Error: problem with file upload (reason unknown)."; break;
      }
      $flashMessenger->addMessage($msg);
    }
    
    // some browsers (or particular versions) do not properly identify mimetypes
    // Calculate mimetype with fileinfo/mimemagic.
    if (function_exists("finfo_open")) {    

      //workaround for weirdness in different enviroments
      $magicfile= getenv('MAGIC_MIME_PATH'); 
      if($magicfile) {
               $file_info = new finfo(FILEINFO_MIME, $magicfile);
      }
      else{
          $file_info = new finfo(FILEINFO_MIME);
      }

      $mime_type = $file_info->buffer(file_get_contents($fileinfo['tmp_name']));  // e.g. gives "image/jpeg"  
      $fileinfo['type'] = preg_replace("/[; ].*$/", "", $mime_type);
    }   
                
    if (!is_null($mimetype) && is_array($mimetype)) {
      if(! in_array($fileinfo['type'], $mimetype)) {
        $flashMessenger->addMessage("Error: file is not an allowed type.");
        $error = true;
      }
    }
    if (is_array($disallowed_types)) {
      if(in_array($fileinfo['type'], $disallowed_types)) {
        $flashMessenger->addMessage("Error: This file type (" . $fileinfo['type'] . ") is not allowed." );
        $error = true;
      }
    }

    return !$error;
  }

  public function move_file($fileinfo, $newname) {
    $flashMessenger = $this->_actionController->getHelper('FlashMessenger');
    // file type & size are ok, no error - so handle file
    if (move_uploaded_file($fileinfo['tmp_name'], $newname)) {
      $flashMessenger->addMessage("Successfully uploaded file.");
      return true;
    } else {
      // FIXME: should therre be an error or a message here?
      return false;
    }
  }
     
}
