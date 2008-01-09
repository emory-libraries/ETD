<?php

require_once("models/rels_ext.php");

// extension of rels-ext object with relations used in ETDs

class etd_rels extends rels_ext {

  // FIXME: does this need to be a real url?
  protected $emoryrelns = "http://www.library.emory.edu/emory-relations#";
  public $status_list;
  
  // add etd-specific mods mappings
  protected function configure() {
    parent::configure();
    //    $this->namespaces['emoryrel'] = $this->emoryrelns;
    //    $this->addNamespace("emoryrel", $this->emoryrelns);


    // allowable values for status
    $this->status_list = array("draft", "submitted", "approved", "reviewed", "published");
    
    // etd status
    $this->xmlconfig["status"] = array("xpath" => "rdf:description/rel:etdStatus");
    
    // rels from etd to etd files
    $this->xmlconfig["pdf"] = array("xpath" => "rdf:description/rel:hasPDF/@rdf:resource",
				    "is_series" => true);
    $this->xmlconfig["original"] = array("xpath" => "rdf:description/rel:hasOriginal/@rdf:resource",
					 "is_series" => true);
    $this->xmlconfig["supplement"] = array("xpath" => "rdf:description/rel:hasSupplement/@rdf:resource",
    "is_series" => true);

    /*    $this->xmlconfig["pdf"] = array("xpath" => "rdf:description/emoryrel:hasPDF/@rdf:resource",
				    "is_series" => true);
    $this->xmlconfig["original"] = array("xpath" => "rdf:description/emoryrel:hasOriginal/@rdf:resource",
					 "is_series" => true);
    $this->xmlconfig["supplement"] = array("xpath" => "rdf:description/emoryrel:hasSupplement/@rdf:resource",
				    "is_series" => true);
    */
    
    // rels from etd file to etd
    $this->xmlconfig["pdfOf"] = array("xpath" => "rdf:description/rel:isPDFOf/@rdf:resource");
    $this->xmlconfig["originalOf"] = array("xpath" => "rdf:description/rel:isOriginalOf/@rdf:resource");
    $this->xmlconfig["supplementOf"] = array("xpath" => "rdf:description/rel:isSupplementOf/@rdf:resource");
  }

    // handle special values
  public function __set($name, $value) {
    switch ($name) {
    case "status":
      if (in_array($value, $this->status_list)) {
	// if value is in the list of known statuses, set normally
	parent::__set($name, $value);
      } else {
	//otherwise: warn user
	throw new XmlObjectException("'$value' is not a recognized etd status");
      }
      break;
    default:
      parent::__set($name, $value);
    }
  }



}