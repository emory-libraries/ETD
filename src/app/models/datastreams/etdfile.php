<?php
/**
 * @category Etd
 * @package Etd_Models
 * @subpackage Etd_File
 */

require_once("XmlObject.class.php");
require_once("models/foxml.php");
require_once("fedora/models/fileDatastream.php");

require_once("etd.php");
require_once("etd_rels.php");
require_once("datastreams/etd_dc.php");
require_once("file_policy.php");

// helper for number of pages in a pdf
require_once("Etd/Controller/Action/Helper/PdfPageTotal.php");

class etd_file extends foxml implements Zend_Acl_Resource_Interface {

  protected $_type = null;
  protected $reltype;

  protected $_etd = null;
  protected $etd_pid = null;
  
  public function __construct($pid = null, etd $parent = null) {
    parent::__construct($pid);

    if ($this->init_mode == 'template') {
        // add relation to contentModel new object
        if (Zend_Registry::isRegistered("config")) {
            $config = Zend_Registry::get("config");
            $this->rels_ext->addContentModel($config->contentModels->etdfile);
        } else {
            trigger_error("Config is not in registry, cannot retrieve contentModel for etdfile");
        }      
      
      // assume new etdFile is the first of its type in a given ETD
      $this->rels_ext->addRelation("rel:sequenceNumber", "1");
    }

    // if initialized by etd, that object is passed in - store for convenience
    if (!is_null($parent)) {
      $this->_etd = $parent;
    }

    // configure relations to other objects - parent etd
    // NOTE: because the relation depends on the file type, this has to be configured here and not earlier
    /*if ($relation = $this->getRelType()) {
      $this->relconfig["etd"] = array("relation" => $relation, "class_name" => "etd");
    } elseif ($this->init_mode == "pid") {
      // not an error if it is a new document
      trigger_error("Could not determine relation to etd for " . $this->pid, E_USER_WARNING);
    }*/

  }


  // add datastreams here 
  protected function configure() {
    parent::configure();
    // add to template for new foxml

    // add mappings for xmlobject
    $this->xmlconfig["file"] = array("xpath" => "foxml:datastream[@ID='FILE']",
             "class_name" => "fileDatastream", "dsID" => "FILE");

    // xacml policy
    $this->addNamespace("x", "urn:oasis:names:tc:xacml:1.0:policy");
    $this->xmlconfig["policy"] = array("xpath" => "//foxml:xmlContent/x:Policy",
               "class_name" => "EtdFileXacmlPolicy", "dsID" => "POLICY");

    
    // use custom rels-ext class
    $this->xmlconfig["rels_ext"]["class_name"] = "etd_rels";
    // use custom DC class
    $this->xmlconfig["dc"]["class_name"] = "etd_dc";
  }


  // set the type of this object (if not already set), and return the relation to the parent etd
  protected function _get_type() {
    if ($this->_type == null) {
        if ($this->init_mode != "pid" && $this->init_mode != "dom") {
          $this->_type = false;   // not applicable if not in one of these modes
        } else {
          try {                  
              if ($this->rels_ext) {
                  // determine what type of etd file this is based on what is in the rels-ext
                  if (isset($this->rels_ext->pdfOf)) {
                    $this->_type = "pdf";
                    $this->etd_pid = $this->rels_ext->pdfOf;
                  } elseif (isset($this->rels_ext->originalOf)) {
                    $this->_type = "original";
                    $this->etd_pid = $this->rels_ext->originalOf;
                  } elseif (isset($this->rels_ext->supplementOf)) {
                    $this->_type = "supplement";
                    $this->etd_pid = $this->rels_ext->supplementOf;
                  } else {
                    trigger_error("etdFile object " . $this->pid . " is not related to an etd object", E_USER_WARNING);
                  }
              }
           } catch  (FedoraAccessDenied $e) {
              // if the current user doesn't have access to RELS-EXT, they don't have full access to this object
              throw new FoxmlException("Access Denied to " . $this->pid);
              //  trigger_error("Access Denied to rels-ext for " . $this->pid, E_USER_WARNING);
           }


        if ($this->_type == "pdf") $this->reltype = "isPDFOf";
        else $this->reltype = "is" . ucfirst($this->_type) . "Of";
        }
    }
    return $this->_type;
  }
  protected function _set_type($val) {
    $this->_type = $val;
  }
  protected function _get_etd() {
    if ($this->_etd == null) {
        $this->type;
        if ($this->etd_pid != null) {
            $this->_etd = new etd($this->etd_pid);
        } else {
            $this->_etd = '';
        }

    }
    return $this->_etd;
  }

  /*** handle special values - "magic" properties that set multiple values ***/

  /**
   * add author/owner to policy and set as rels-ext author,
   * then cascade to parent owner logic
   * @param string $value
   */
  protected function _set_owner($value) {
      // add author's username to the appropriate rules
      if (isset($this->policy) && isset($this->policy->draft))
        $this->policy->draft->condition->user = $value;
      parent::_set_owner($value);
  }

      
  /**
   * initialize an etdfile object from a file and user
   * @param string $filename full path to file
   * @param string $reltype type of file in relation to etd (pdf, original, supplement)
   * @param esdPerson $author user this file belongs to (sets creator & owner)
   * @param string $label optional, label for fedora object; defaults to basename of the file
   */
  public function initializeFromFile($filename, $reltype, esdPerson $author, $label = null) {
    $this->type = $reltype;
    if (!is_null($label)) $this->label = $label;
    else $this->label = basename($filename);
    $this->owner = $author->netid;

    // set reasonable defaults for author, description
    $this->dc->creator = $author->fullname; // most likely the case...
    $this->dc->type = "Text";     // most likely; can be overridden based on mimetype below


    // store original filename, mimetype, filesize, and number of pages (when appropriate)
    $this->setFileInfo($filename, $label);

    // set a default document type
    $doctype = "Dissertation/Thesis";
    if ($this->etd) { // if associated etd is available, set doctype to something more accurate
      if ($this->etd->isHonors()) {
        $doctype = "Honors Thesis";
      } elseif ($this->etd->document_type() != "") {
        // ONLY override doctype if this is set, do NOT set it to blank!
        $doctype = $this->etd->document_type();
      }
    }
    if ($this->type == "pdf") {
      $this->dc->title = $doctype;
      $this->dc->description = "Access copy of " . $doctype;
    } elseif ($this->type == "original") {
      $this->dc->title = "Original Document";
      $this->dc->description = "Archival copy of " . $doctype;
    } else {  // supplemental files
      // make a "best guess" at the type of content based on mimetype  (other than text)
      list($major, $minor) = split('/', $this->dc->mimetype);
      switch ($major) { 
      case "image": $this->dc->type = "StillImage"; break;
      case "audio": $this->dc->type = "Sound"; break;
      case "video": $this->dc->type = "MovingImage"; break;
      case "application":
        switch ($minor) {
        case "vnc.ms-excel":
        case "vnd.openxmlformats-officedocument.spreadsheetml.sheet":
        case "vnd.oasis.opendocument.spreadsheet":
          $this->dc->type = "Dataset"; break; // spreadsheets
          
        }
      }
      $this->dc->description = "supplemental file for $doctype";
    }
    // now actually upload the file and set ingest url to upload id
    $this->setFile($filename);   
  }

  /**
   * store information about a newly uploaded file
   * @param string $tmpfile location of the temporary file
   * @param string $userfilename (optional) user's file name (instead of php temporary name)
   */
  public function setFileInfo($tmpfile, $userfilename = null) {
    
    // note: using fileinfo because mimetype reported by the browser is unreliable
    $finfo = finfo_open(FILEINFO_MIME); 
    $filetype = finfo_file($finfo, $tmpfile);

    if (isset($userfilename)) $filename = $userfilename;
    else $filename = $tmpfile;
    
    // FIXME: this logic should be pulled out into a helper or library...

    // NOTE: certain versions of php fileinfo return mimetypes like this: application/pdf; charset=binary
    // for now, just throwing away the additional information
    $filetype = preg_replace("/;.*$/", "", $filetype);
    
    // several things get reported as zip that we want to recognize
    if ($filetype == "application/zip" || $filetype == "application/x-zip") {
      $parts = explode(".", $filename);
      
      $ext = $parts[count($parts)-1];
      switch ($ext) {
  // Microsoft Office 2007 formats
      case "docx":
  $filetype = "application/vnd.openxmlformats-officedocument.wordprocessingml.document"; break;
      case "xslx":
  $filetype = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"; break;
      case "pptx":
  $filetype = "application/vnd.openxmlformats-officedocument.presentationml.presentation"; break;

  // OpenOffice.org formats
      case "odt":    $filetype = "application/vnd.oasis.opendocument.text"; break;
      case "ods":    $filetype = "application/vnd.oasis.opendocument.spreadsheet"; break;
      case "odp":    $filetype = "application/vnd.oasis.opendocument.presentation"; break;
      }
    }
    
    $this->dc->setMimetype($filetype);  // mimetype
    if (isset($this->file)) // new record, not yet ingested into Fedora
      $this->file->mimetype = $filetype;

    $this->dc->setFilename(basename($filename));  // temporary file, so directory doesn't matter

    /* if this is a PDF, we can get the number of pages
       - this is especially important for pdf of dissertation,
       but doesn't hurt for any pdf supplements
    */
    if ($filetype == "application/pdf") {
      $pagecalc = new Etd_Controller_Action_Helper_PdfPageTotal();
      $this->dc->setPages($pagecalc->pagetotal($tmpfile));
    }
      
    // since mimetype from upload info is not reliable, don't rely on that for size either
    $this->dc->setFilesize(filesize($tmpfile)); // file size in bytes
    
  }

  /**
   * set file information for ingest; uploads to Fedora and sets upload id in ingest foxml
   * @param string $filename full path to file 
   */
  public function setFile($filename) {
    $this->file->filename = $filename;
    // calculate and store datastream mimetype here
    $finfo = finfo_open(FILEINFO_MIME); 
    $filetype = finfo_file($finfo, $filename);
    $this->file->mimetype = $filetype;
    // calculate and set checksum
    $this->file->checksum = md5_file($filename);          
  } 
  
  /**
   * get the binary file datastream as storeed in fedora
   * @return binary
   */
  public function getFile() {
    return $this->fedora->getDatastream($this->pid, "FILE");
  }

  /**
   * return the md5 checksum for the binary file datastream in Fedora
   * @return string
   */
  public function getFileChecksum() {
    return $this->fedora->compareDatastreamChecksum($this->pid, "FILE");
  }
  
  /**
   * return dc:description
   * (wrapper to description - to simplify unit testing)
   * @return string
   */
  public function description() {
    return $this->dc->description;
  }

  /**
   * generate a nice, human-readable filename based on file type and etd information
   * @return string
   */
  public function prettyFilename() {
    // build a nice, user-friendly filename
    $filename = strtolower($this->etd->mods->author->last) . "_";
    $nonfilechars = array("'", ",");  // what other characters are likely to occur in names?
    $replace = array(); // replace all with empty strings 
    $filename =  str_replace($nonfilechars, $replace, $filename);

    switch ($this->type) {
    case "pdf": $filename .= "dissertation"; break;
    case "original":  $filename .= "original";  break;
    case "supplement": $filename .= "supplement"; break;
    }

    // if there is more than one of this type of file, add a number
    if (count($this->etd->{$this->type . "s"}) > 1) $filename .= $this->rels_ext->sequence;

    // determine file extension based on mimetype for common/expected files
    switch ($this->dc->mimetype) {
    case "application/pdf":  $ext = "pdf"; break;
    case "application/msword":  $ext = "doc"; break;
    default:
      if (isset($this->dc->filename)) {   // stored original filename
  $parts = explode(".", $this->dc->filename);
  $ext = $parts[count($parts)-1]; // trust user's extension from original file
      }
    }
    if (isset($ext)) $filename .= "." . $ext;
    return $filename;
  }


  /**
   * override default foxml ingest function to use arks for object pids
   * @param string $message
   * @return string pid on successful ingest
   */
  public function ingest($message) {
    // mint a new pid if the pid is not already set
    if ($this->pid == "") {
        // could generate service unavailable exception - should be caught in the controller
        $persis = new Emory_Service_Persis(Zend_Registry::get('persis-config'));

        // FIXME: is there any way to use view/controller helper to build this url?
        $ark = $persis->generateArk("http://etd.library.emory.edu/file/view/pid/emory:{%PID%}",
            $this->etd->label . " : " . $this->label . " (" . $this->type . ")");
        $pid = $persis->pidfromArk($ark);
        $this->pid = $pid;

        // store the full ark as an additional identifier
        $this->dc->setArk($ark);
    }    
    // use parent ingest logic to construct new foxml & datastreams appropriately
    return parent::ingest($message);    
    }



  /**
   * purge an etd file object from Fedora  
   * - removes relation from parent etd record, THEN purges from fedora
   * @param string $message reason for change
   * @return string timestamp on success
   */
  public function purge($message) {
    $rel = "rel:has" . ucfirst($this->type);  
    if ($this->type == "pdf")
      $rel = "rel:hasPDF";
    // maybe add removePdf, removeSupplement, etc. functions for etd_rels ?

    if ($this->etd) {
      // remove relation stored in parent etd record, save parent etd
      $this->etd->rels_ext->removeRelation($rel, $this->pid);
      $this->etd->save("removed relation $rel to " . $this->pid);
    }

    // run default foxml purge
    return parent::purge($message);
  }

  /**
   * Mark a record as deleted 
   * (does NOT actually purge, but makes inaccessible to non-admin users)
   * First removes relation to parent ETD record, then sets object status.
   * 
   * @param string $message
   * @return string date modified
   */
  public function delete($message) {
    if ($this->etd) {
      $this->etd->removeFile($this);
      $this->etd->save("removed relation to etdFile " . $this->pid . "; $message");
    }
    $this->setObjectState(FedoraConnection::STATE_DELETED);
    return $this->save($message);
  }


  /**
   * update the binary file datastream
   * @param string $filename full path to new version of the file
   * @param string $message message string for change
   * @return string timestamp on success
   */
  public function updateFile($filename, $message) {
    $this->setFileInfo($filename);   // update mimetype, filesize, and pages if appropriate       
    $upload_id = $this->fedora->upload($filename);
    return $this->fedora->modifyBinaryDatastream($this->pid, "FILE", "Binary File", $this->dc->mimetype,
             $upload_id, $message);
  }


  /**
   * return information for last modification of file datastream in fedora
   * (wrapper for foxml lastModified function - abstract datastream name)
   * @see foxml::lastModified
   */
  public function fileLastModified() {
    return $this->lastModified("FILE");
  }



  /**
   * allow etd_file to act as a Zend ACL Resource
   * @return string
   */ 
  public function getResourceId() {
    // check for various types
    
    if ($this->etd->status() == "draft")
      return "draft file";

    if ($this->type == "original")
      return "original file";
    
    if ($this->etd->isEmbargoed()) 
      return "embargoed file";
    
    // these are the only statuses that are relevant
    if ($this->etd->status() == "published")
      return "published file";
    return "file";
  }


}  


class masterDatastream extends fileDatastream {
    public $label = 'Binary File (archival copy)';
    public $mimetype = 'application/pdf';   // default, should be updated if different
    public $checksum_type = 'MD5';
}
