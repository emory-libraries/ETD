<?php

require_once("XmlObject.class.php");
require_once("models/foxml.php");
require_once("api/fedora.php");

class etd_file extends foxml {

  public function __construct($pid = null) {
    parent::__construct($pid);

    if (is_null($pid)) 
      $this->cmodel = "etdFile";
  }


  // add datastreams here 
  protected function configure() {
    parent::configure();
    // add to template for new foxml
    $this->datastreams[] = "file_for_ingest";

    // add mappings for xmlobject
    $this->xmlconfig["file"] = array("xpath" => "foxml:datastream[@ID='FILE']",
				     "class_name" => "file_for_ingest");
  }

}  


class file_for_ingest extends foxmlIngestStream {
  public static function getFedoraTemplate(){
    return foxml::ingestDatastreamTemplate("FILE", "Binary File", "application/pdf", "M");
    // note: pdf is just a guess, should be updated by script that initializes this
  }
}
