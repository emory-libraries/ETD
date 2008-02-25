<?php

require_once("XmlObject.class.php");
require_once("models/foxml.php");
require_once("api/fedora.php");

require_once("persis.php");

require_once("etd.php");
require_once("etd_rels.php");
require_once("etd_dc.php");
require_once("policy.php");


class etd_file extends foxml {

  public $parent;
  public $type;
  
  public function __construct($pid = null, etd $parent = null) {
    parent::__construct($pid);

    if ($this->init_mode == "pid") {
      // anything here?
    } elseif ($this->init_mode == "dom") {
      // anything here?
    } else {
      // new etd objects
      $this->cmodel = "etdFile";
      
      // assume new etdFile is the first of its type in a given ETD
      $this->rels_ext->addRelation("rel:sequenceNumber", "1");
    }


    if ($this->init_mode == "pid" || $this->init_mode == "dom") {
      try {
	$this->rels_ext != null;
      } catch  (FedoraAccessDenied $e) {
	// if the current user doesn't have access to RELS-EXT, they don't have full access to this object
	throw new FoxmlException("Access Denied to " . $this->pid); 
      }
      
      if ($this->rels_ext) {
	
	// determine what type of etd file this is
	if (isset($this->rels_ext->pdfOf)) {
	  $this->type = "pdf";
	} elseif (isset($this->rels_ext->originalOf)) {
	  $this->type = "original";
	} elseif (isset($this->rels_ext->supplementOf)) {
	  $this->type = "supplement";
	} else {
	  trigger_error("etdFile object " . $this->pid . " is not related to an etd object", E_USER_WARNING);
	}
	
	// based on file type, get parent pid and initialize parent object (if not set and if not a new object)
	if (isset($this->type) && is_null($parent) && !is_null($pid)) {
	  $parent_rel = $this->type . "Of";
	  if ($this->rels_ext->$parent_rel)	// don't try to load if pid is blank
	    $this->parent = new etd($this->rels_ext->$parent_rel);
	} else {
	  $this->parent = $parent;
	}
      }
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
				       "class_name" => "XacmlPolicy", "dsID" => "POLICY");

    
    // just override one portion of the rels configuration
    $this->xmlconfig["rels_ext"]["class_name"] = "etd_rels";

    // use custom DC
    $this->xmlconfig["dc"]["class_name"] = "etd_dc";

    
  }

  public function setFile($filename) {
    $upload_id = $this->fedora->upload($filename);
    $this->file->url = $upload_id;
  }

  public function getFile() {
    return $this->fedora->getDatastream($this->pid, "FILE");
  }


  public function prettyFilename() {
    // build a nice, user-friendly filename
    $filename = strtolower($this->parent->mods->author->last) . "_";
    switch ($this->type) {
    case "pdf":	$filename .= "dissertation"; break;
    case "original":	$filename .= "original";  break;
    case "supplement": $filename .= "supplement"; break;
    }

    // if there is more than one of this type of file, add a number
    if (count($this->parent->{$this->type . "s"}) > 1) $filename .= $this->rels_ext->sequence;

    // add second half of mime-type as file extension (FIXME: will this always work?
    $filename .= "." . preg_replace("|^.*/|", "", $this->dc->mimetype);

    return $filename;
  }


  /**  override default foxml ingest function to use arks for object pids
   */
  public function ingest($message ) {
    $persis = new etd_persis();
    
    // FIXME: use view/controller to build this url?
    $ark = $persis->generateArk("http://etd/file/view/pid/emory:{%PID%}", $this->label);
    $pid = $persis->pidfromArk($ark);
    $this->pid = $pid;

    // store the full ark as an additional identifier
    $this->dc->setArk($ark);
    
    return $this->fedora->ingest($this->saveXML(), $message);
    }



  // remove from parent etd record, THEN purge from fedora
  public function purge($message) {
    $rel = "rel:has" . ucfirst($this->type);	// FIXME: won't work for pdf...
    if ($this->type == "pdf")
      $rel = "rel:hasPDF";
    // maybe add removePdf, removeSupplement, etc. functions for etd_rels ?

    // remove relation stored in parent etd record, save parent etd
    $this->parent->rels_ext->removeRelation($rel, $this->pid);
    $this->parent->save("removed relation $rel to " . $this->pid);

    // run default foxml purge
    return parent::purge($message);
  }

  public function updateFile($filename, $mimetype, $message) {
    $upload_id = $this->fedora->upload($filename);
    return $this->fedora->modifyBinaryDatastream($this->pid, "FILE", "Binary File", $mimetype, $upload_id, $message);
  }

}  


class file_for_ingest extends foxmlIngestStream {
  public static function getFedoraTemplate(){
    return foxml::ingestDatastreamTemplate("FILE", "Binary File", "application/pdf", "M");
    // note: pdf is just a guess, should be updated by script that initializes this
  }
}
