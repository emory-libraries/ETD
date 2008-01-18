<?php

require_once("XmlObject.class.php");
require_once("models/foxml.php");
require_once("api/fedora.php");
require_once("etd_rels.php");

class etd_file extends foxml {

  public $parent;
  protected $type;
  
  public function __construct($pid = null, etd $parent = null) {
    parent::__construct($pid);

    if (is_null($pid)) 
      $this->cmodel = "etdFile";


    // determine what type of etd file this is
    if (isset($this->rels_ext->pdfOf)) {
      $this->type = "pdf";
    } elseif (isset($this->rels_ext->originalOf)) {
      $this->type = "original";
    } elseif (isset($this->rels_ext->supplementOf)) {
      $this->type = "supplement";
    }

    // based on file type, get parent pid and initialize parent object (if not set and if not a new object)
    if (is_null($parent) && !is_null($pid)) {
      $parent_rel = $this->type . "Of";
      $this->parent = new etd($this->rels_ext->$parent_rel);
    } else {
      $this->parent = $parent;
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

    // just override one portion of the rels configuration
    $this->xmlconfig["rels_ext"]["class_name"] = "etd_rels";
  }


  /**  override default foxml ingest function to use arks for object pids
   */
  public function ingest($message ) {
    $persis = Zend_Registry::get("persis");
    // FIXME: use view/controller to build this url?
    $ark = $persis->generateArk("http://etd/file/view/pid/emory:{%PID%}", $this->label);
    $pid = $persis->pidfromArk($ark);

    $this->pid = $pid;
    return fedora::ingest($this->saveXML(), $message);
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
    $upload_id = fedora::upload($filename);
    return fedora::modifyBinaryDatastream($this->pid, "FILE", "Binary File", $mimetype, $upload_id, $message);
  }

}  


class file_for_ingest extends foxmlIngestStream {
  public static function getFedoraTemplate(){
    return foxml::ingestDatastreamTemplate("FILE", "Binary File", "application/pdf", "M");
    // note: pdf is just a guess, should be updated by script that initializes this
  }
}
