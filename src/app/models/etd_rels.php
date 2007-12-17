<?php

require_once("models/rels_ext.php");

// extension of rels-ext object with relations used in ETDs

class etd_rels extends rels_ext {

  // add etd-specific mods mappings
  protected function configure() {
    parent::configure();

    // rels from etd to etd files
    $this->xmlconfig["pdf"] = array("xpath" => "rdf:description/rel:hasPDF/@rdf:resource",
				    "is_series" => true);
    $this->xmlconfig["original"] = array("xpath" => "rdf:description/rel:hasOriginal/@rdf:resource",
					 "is_series" => true);
    $this->xmlconfig["supplement"] = array("xpath" => "rdf:description/rel:hasSupplement/@rdf:resource",
				    "is_series" => true);

    
    // rels from etd file to etd
    $this->xmlconfig["pdfOf"] = array("xpath" => "rdf:description/rel:isPDFOf/@rdf:resource");
    $this->xmlconfig["originalOf"] = array("xpath" => "rdf:description/rel:isOriginalOf/@rdf:resource");
    $this->xmlconfig["supplementOf"] = array("xpath" => "rdf:description/rel:isSupplementOf/@rdf:resource");
  }

}