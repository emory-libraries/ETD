<?php

require_once("models/rels_ext.php");

/**
 * extension of rels-ext foxml datastream object with relations used in ETDs
 * @category Etd
 * @package Etd_Models
 * @subpackage Etd
 *
 * @property string $status etd status
 * @property string $sequence sequenceNumber (for etd files)
 * @property array $pdf array of pids for pdfs that belong to an etd
 * @property array $original array of pids for original files that belong to an etd 
 * @property array $supplement array of pids for supplemental files that belong to an etd
 * @property string $hasAuthorInfo pid for author info object that belongs to an etd
 * @property string $author author username
 * @property string $advisor advisor username
 * @property array $committee committee member usernames
 * @property string $program program id
 * @property string $subfield program subfield id
 * @property string $pdfOf related etd pid (from pdf etd_file)
 * @property string $originalOf related etd pid (from original etd_file)
 * @property string $supplementOf related etd pid (from supplement etd_file)
 * @property string $etd related etd pid (from author info object user)
 */
class etd_rels extends rels_ext {

  // FIXME: does this need to be a real url?
  protected $emoryrelns = "http://www.library.emory.edu/emory-relations#";
  /**
   * etd statuses
   * @var array 
   */
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

    // NOTE: for legacy reasons, these xpaths are all written to match either rdf:Description
    // OR the incorrect rdf:description (present in older revisions of some rels-ext datastreams)
    
    // etd status
    $this->xmlconfig["status"] = array("xpath" => "rdf:*[local-name() = 'Description' or local-name() = 'description']/rel:etdStatus");


    // order within a set
    $this->xmlconfig["sequence"] = array("xpath" => "rdf:*[local-name() = 'Description' or local-name() = 'description']/rel:sequenceNumber");
    
    // rels from etd to etd files
    $this->xmlconfig["pdf"] = array("xpath" => "rdf:*[local-name() = 'Description' or local-name() = 'description']/rel:hasPDF/@rdf:resource",
				    "is_series" => true);
    $this->xmlconfig["original"] = array("xpath" => "rdf:*[local-name() = 'Description' or local-name() = 'description']/rel:hasOriginal/@rdf:resource",
					 "is_series" => true);
    $this->xmlconfig["supplement"] = array("xpath" => "rdf:*[local-name() = 'Description' or local-name() = 'description']/rel:hasSupplement/@rdf:resource",
    "is_series" => true);


    // user information object
    $this->xmlconfig["hasAuthorInfo"] = array("xpath" => "rdf:*[local-name() = 'Description' or local-name() = 'description']/rel:hasAuthorInfo/@rdf:resource");

    
    // relationships to users
    $this->xmlconfig["author"] = array("xpath" => "rdf:*[local-name() = 'Description' or local-name() = 'description']/rel:author");
    $this->xmlconfig["advisor"] = array("xpath" => "rdf:*[local-name() = 'Description' or local-name() = 'description']/rel:advisor");
    $this->xmlconfig["committee"] = array("xpath" => "rdf:*[local-name() = 'Description' or local-name() = 'description']/rel:committee",
					    "is_series" => true);

    // rels to program & subfield (by id)
    $this->xmlconfig["program"] = array("xpath" => "rdf:*[local-name() = 'Description' or local-name() = 'description']/rel:program");
    $this->xmlconfig["subfield"] = array("xpath" => "rdf:*[local-name() = 'Description' or local-name() = 'description']/rel:subfield");
    
    // rels from etd file to etd
    $this->xmlconfig["pdfOf"] = array("xpath" => "rdf:*[local-name() = 'Description' or local-name() = 'description']/rel:isPDFOf/@rdf:resource");
    $this->xmlconfig["originalOf"] = array("xpath" => "rdf:*[local-name() = 'Description' or local-name() = 'description']/rel:isOriginalOf/@rdf:resource");
    $this->xmlconfig["supplementOf"] = array("xpath" => "rdf:*[local-name() = 'Description' or local-name() = 'description']/rel:isSupplementOf/@rdf:resource");

    // rels from user to etd
    $this->xmlconfig["etd"] = array("xpath" => "rdf:*[local-name() = 'Description' or local-name() = 'description']/rel:authorInfoFor/@rdf:resource");
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


  /**
   * set all committee members rels  from an array of ids
   * @param array $ids
   */
  public function setCommittee(array $ids) {
    $this->clearCommittee();	// clear the old
    foreach ($ids as $id) 	// add the new
      $this->addRelation("rel:committee", $id);
    $this->update();
  }

  /**
   * remove all committee members rels  (use before re-adding them)
   */
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
   * @static
   */
  public static function getStatusList() {
    // class must be initialized with some kind of DOM; load an empty one to get the status array
    $emptydom = new DOMDocument();
    $emptydom->loadXML("<empty/>");
    $rels = new etd_rels($emptydom);
    return $rels->status_list;
  }



}