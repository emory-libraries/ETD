<?php

class Etd_Controller_Action_Helper_FileUpload extends Zend_Controller_Action_Helper_Abstract {


  public function direct($fileinfo, $newname, $mimetype = null) {
    return $this->get_upload($fileinfo, $newname, $mimetype);
  }




  /**
   *
   * @param array $fileinfo relevant information (for this particular) from the $_FILES global
   * @param string $newname new filename and path where temporary file should be moved
   * @param array $mimetype (optional) mimetype(s) that upload file should be restricted to
   */
  public function get_upload($fileinfo, $newname, $mimetype = null) {

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


    if (!is_null($mimetype) && is_array($mimetype)) {
      // don't trust mimetype sent by the browser, as it is not always accurate
      $finfo = finfo_open(FILEINFO_MIME);	
      $filetype = finfo_file($finfo, $fileinfo['tmp_name']);	
      if(! in_array($filetype, $mimetype)) {
	// fixme: more information here? list the types expected/allowed?
	$flashMessenger->addMessage("Error: file is not correct type.");
      }
    }

    // don't rename file if there are any problems
    if ($error) {
      return false;
    }

    // file type & size are ok, no error - so handle file
    if (move_uploaded_file($fileinfo['tmp_name'], $newname)) {
      $flashMessenger->addMessage("Successfully uploaded file <b>" . $fileinfo['name'] . "</b>");
      return true;
    }
  }
    
}

