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
    $this->status_list = array("published",
			       "approved",
			       "reviewed",
			       "submitted",
			       "draft",
			       "inactive");
    
    // etd status
    $this->xmlconfig["status"] = array("xpath" => "rdf:Description/rel:etdStatus");


    // order within a set
    $this->xmlconfig["sequence"] = array("xpath" => "rdf:Description/rel:sequenceNumber");
    
    // rels from etd to etd files
    $this->xmlconfig["pdf"] = array("xpath" => "rdf:Description/rel:hasPDF/@rdf:resource",
				    "is_series" => true);
    $this->xmlconfig["original"] = array("xpath" => "rdf:Description/rel:hasOriginal/@rdf:resource",
					 "is_series" => true);
    $this->xmlconfig["supplement"] = array("xpath" => "rdf:Description/rel:hasSupplement/@rdf:resource",
    "is_series" => true);


    // user information object
    $this->xmlconfig["hasAuthorInfo"] = array("xpath" => "rdf:Description/rel:hasAuthorInfo/@rdf:resource");

    
    // relationships to users
    $this->xmlconfig["author"] = array("xpath" => "rdf:Description/rel:author");
    $this->xmlconfig["advisor"] = array("xpath" => "rdf:Description/rel:advisor");
    $this->xmlconfig["committee"] = array("xpath" => "rdf:Description/rel:committee",
					    "is_series" => true);

    // rels to program & subfield (by id)
    $this->xmlconfig["program"] = array("xpath" => "rdf:Description/rel:program");
    $this->xmlconfig["subfield"] = array("xpath" => "rdf:Description/rel:subfield");
    
    // rels from etd file to etd
    $this->xmlconfig["pdfOf"] = array("xpath" => "rdf:Description/rel:isPDFOf/@rdf:resource");
    $this->xmlconfig["originalOf"] = array("xpath" => "rdf:Description/rel:isOriginalOf/@rdf:resource");
    $this->xmlconfig["supplementOf"] = array("xpath" => "rdf:Description/rel:isSupplementOf/@rdf:resource");

    // rels from user to etd
    $this->xmlconfig["etd"] = array("xpath" => "rdf:Description/rel:authorInfoFor/@rdf:resource");
    //					      "is_series" => true);
  }

    // handle special values
  public function __set($name, $value) {
    switch ($name) {
    case "status":
      if (in_array($value, $this->status_list)) {
	// if value is in the list of known statuses, set normally
	return parent::__set($name, $value);
      } else {
	//otherwise: warn user
	throw new XmlObjectException("'$value' is not a recognized etd status");
      }
      break;
    case "program":
    case "subfield":
      // if value begins with #, strip the # off; otherwise leave as is
      $value = preg_replace("/^#/", '', $value);
      if (!isset($this->{$name})) {
	$this->addRelation("rel:$name", $value);
	$this->update();
      } else { parent::__set($name, $value); }
      break;
    default:
      return parent::__set($name, $value);
    }
  }


  // set all committee members rels  from an array of ids
  public function setCommittee(array $ids) {
    $this->clearCommittee();	// clear the old
    foreach ($ids as $id) 	// add the new
      $this->addRelation("rel:committee", $id);
    $this->update();
  }

  // remove all committee members rels  (use before re-adding them)
  public function clearCommittee() {
    $nodelist = $this->xpath->query("//rel:committee", $this->domnode);
    for ($i = 0; $i < $nodelist->length; $i++) {
      $node = $nodelist->item($i);
      $node->parentNode->removeChild($node);
    }
    $this->update();
  }



  /**
   * static function to retrieve etd status list
   * @return array of status
   */
  public static function getStatusList() {
    // class must be initialized with some kind of DOM; load an empty one to get the status array
    $emptydom = new DOMDocument();
    $emptydom->loadXML("<empty/>");
    $rels = new etd_rels($emptydom);
    return $rels->status_list;
  }



}