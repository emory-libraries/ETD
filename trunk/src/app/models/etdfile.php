<?php

require_once("XmlObject.class.php");
require_once("models/foxml.php");
require_once("api/fedora.php");

// Persistent ID server - generate arks for fedora pids
require_once("Etd/Service/Persis.php");

require_once("etd.php");
require_once("etd_rels.php");
require_once("etd_dc.php");
require_once("file_policy.php");

// helper for number of pages in a pdf
require_once("Etd/Controller/Action/Helper/PdfPageTotal.php");

class etd_file extends foxml implements Zend_Acl_Resource_Interface {

  public $type;
  
  public function __construct($pid = null, etd $parent = null) {
    parent::__construct($pid);

    if ($this->init_mode == "pid") {
      // anything here?
      $this->getRelType();
    } elseif ($this->init_mode == "dom") {
      // anything here?
      $this->getRelType();
    } else {
      // new etd objects
      $this->cmodel = "etdFile";
      
      // assume new etdFile is the first of its type in a given ETD
      $this->rels_ext->addRelation("rel:sequenceNumber", "1");
    }

    // if initialized by etd, that object is passed in - store for convenience
    if (!is_null($parent)) {
      $this->related_objects["etd"] = $parent;
    }

    // configure relations to other objects - parent etd
    // NOTE: because the relation depends on the file type, this has to be configured here and not earlier
    if ($relation = $this->getRelType()) {
      $this->relconfig["etd"] = array("relation" => $relation, "class_name" => "etd");
    } elseif ($this->init_mode == "pid") {
      // not an error if it is a new document
      trigger_error("Could not determine relation to etd for " . $this->pid, E_USER_WARNING);
    }

  }


  // add datastreams here 
  protected function configure() {
    parent::configure();
    // add to template for new foxml
    $this->datastreams[] = "file_for_ingest";

    // add mappings for xmlobject
    $this->xmlconfig["file"] = array("xpath" => "foxml:datastream[@ID='FILE']",
				     "class_name" => "file_for_ingest");

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
  private function getRelType() {
    if ($this->init_mode != "pid" && $this->init_mode != "dom") {
      return false;		// not applicable if not in one of these modes
    }

    if (!isset($this->type)) {		// if not set, configure
      try {
	$this->rels_ext != null;
      } catch  (FedoraAccessDenied $e) {
	// if the current user doesn't have access to RELS-EXT, they don't have full access to this object
	throw new FoxmlException("Access Denied to " . $this->pid);
	//	trigger_error("Access Denied to rels-ext for " . $this->pid, E_USER_WARNING);
      }
      
      if ($this->rels_ext) {
	// determine what type of etd file this is based on what is in the rels-ext
	if (isset($this->rels_ext->pdfOf)) {
	  $this->type = "pdf";
	} elseif (isset($this->rels_ext->originalOf)) {
	  $this->type = "original";
	} elseif (isset($this->rels_ext->supplementOf)) {
	  $this->type = "supplement";
	} else {
	  trigger_error("etdFile object " . $this->pid . " is not related to an etd object", E_USER_WARNING);
	}
      }
    }

    if ($this->type == "pdf") $reltype = "isPDFOf";
    else $reltype = "is" . ucfirst($this->type) . "Of";
    
    return $reltype;
  }

  // handle special values
  public function __set($name, $value) {
    switch ($name) {

    case "owner":
      // add author's username to the appropriate rules
      if (isset($this->policy) && isset($this->policy->draft))
	$this->policy->draft->condition->user = $value;

      // set ownerId property
      parent::__set($name, $value);
      break;
    default:
      parent::__set($name, $value);
    }
  }
      


  public function initializeFromFile($filename, $reltype, esdPerson $author, $label = null) {
    $this->type = $reltype;
    if (!is_null($label)) $this->label = $label;
    else $this->label = basename($filename);
    $this->owner = $author->netid;

    // set reasonable defaults for author, description
    $this->dc->creator = $author->fullname;	// most likely the case...
    $this->dc->type = "Text";			// most likely; can be overridden based on mimetype below
    
    $this->setFileInfo($filename);	// set mimetype, filesize, and pages if appropriate       

    $doctype = "Dissertation/Thesis";
    if (isset($this->etd)) $doctype = $this->etd->document_type();

    if ($this->type == "pdf") {
      $this->dc->title = $doctype;
      $this->dc->description = "Access copy of " . $doctype;
    } elseif ($this->type == "original") {
      $this->dc->title = "Original Document";
      $this->dc->description = "Archival copy of " . $doctype;
    } else {	// supplemental files
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
	  $this->dc->type = "Dataset"; break;	// spreadsheets
	  
	}
      }
    }

    // now actually upload the file and set ingest url to upload id
    $this->setFile($filename);
  }


  
  public function setFileInfo($filename) {
    // note: using fileinfo because mimetype reported by the browser is unreliable
    $finfo = finfo_open(FILEINFO_MIME);	
    $filetype = finfo_file($finfo, $filename);

    // FIXME: this logic should be pulled out into a helper or library...
    
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
    
    $this->dc->setMimetype($filetype);	// mimetype
    if (isset($this->file))	// new record, not yet ingested into Fedora
      $this->file->mimetype = $filetype;

    $this->dc->setFilename(basename($filename));	// temporary file, so directory doesn't matter

    /* if this is a PDF, we can get the number of pages
       - this is especially important for pdf of dissertation,
       but doesn't hurt for any pdf supplements
    */
    if ($filetype == "application/pdf") {
      $pagecalc = new Etd_Controller_Action_Helper_PdfPageTotal();
      $this->dc->setPages($pagecalc->pagetotal($filename));
    }
    
    // since mimetype from upload info is not reliable, don't rely on that for size either
    $this->dc->setFilesize(filesize($filename));	// file size in bytes
  }

  public function setFile($filename) {
    $upload_id = $this->fedora->upload($filename);
    $this->file->url = $upload_id;
  }

  public function getFile() {
    return $this->fedora->getDatastream($this->pid, "FILE");
  }

  // wrapper to description - to simplify unit testing
  public function description() {
    return $this->dc->description;
  }
  
  public function prettyFilename() {
    // build a nice, user-friendly filename
    $filename = strtolower($this->etd->mods->author->last) . "_";
    $nonfilechars = array("'", ",");	// what other characters are likely to occur in names?
    $replace = array();	// replace all with empty strings 
    $filename =  str_replace($nonfilechars, $replace, $filename);

    switch ($this->type) {
    case "pdf":	$filename .= "dissertation"; break;
    case "original":	$filename .= "original";  break;
    case "supplement": $filename .= "supplement"; break;
    }

    // if there is more than one of this type of file, add a number
    if (count($this->etd->{$this->type . "s"}) > 1) $filename .= $this->rels_ext->sequence;

    // determine file extension based on mimetype for common/expected files
    switch ($this->dc->mimetype) {
    case "application/pdf":  $ext = "pdf"; break;
    case "application/msword":  $ext = "doc"; break;
    default:
      if (isset($this->dc->filename)) {		// stored original filename
	$parts = explode(".", $this->dc->filename);
	$ext = $parts[count($parts)-1];	// trust user's extension from original file
      }
    }
    if (isset($ext)) $filename .= "." . $ext;
    return $filename;
  }


  /**  override default foxml ingest function to use arks for object pids
   */
  public function ingest($message ) {
    // could generate service unavailable exception - should be caught in the controller
    $persis = new Etd_Service_Persis();
    
    // FIXME: is there any way to use view/controller helper to build this url?
    $ark = $persis->generateArk("http://etd.library.emory.edu/file/view/pid/emory:{%PID%}",
				$this->etd->label . " : " . $this->label . " (" . $this->type . ")");
    $pid = $persis->pidfromArk($ark);
    $this->pid = $pid;

    // store the full ark as an additional identifier
    $this->dc->setArk($ark);
    
    return $this->fedora->ingest($this->saveXML(), $message);
    }



  // remove from parent etd record, THEN purge from fedora
  public function purge($message) {
    $rel = "rel:has" . ucfirst($this->type);	
    if ($this->type == "pdf")
      $rel = "rel:hasPDF";
    // maybe add removePdf, removeSupplement, etc. functions for etd_rels ?

    if (isset($this->etd)) {
      // remove relation stored in parent etd record, save parent etd
      $this->etd->rels_ext->removeRelation($rel, $this->pid);
      $this->etd->save("removed relation $rel to " . $this->pid);
    }

    // run default foxml purge
    return parent::purge($message);
  }

  public function updateFile($filename, $message) {
    $this->setFileInfo($filename);   // update mimetype, filesize, and pages if appropriate       
    $upload_id = $this->fedora->upload($filename);
    return $this->fedora->modifyBinaryDatastream($this->pid, "FILE", "Binary File", $this->dc->mimetype,
						 $upload_id, $message);
  }


  // wrapper for foxml lastModified function - abstract datastream name
  public function fileLastModified() {
    return $this->lastModified("FILE");
  }



  // for Zend ACL Resource
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


class file_for_ingest extends foxmlIngestStream {
  public static function getFedoraTemplate(){
    return foxml::ingestDatastreamTemplate("FILE", "Binary File", "application/pdf", "M");
    // note: pdf is just a guess, should be updated by script that initializes this
  }
}
