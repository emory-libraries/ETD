<?
/**
 * @category Etd
 * @package Etd_Models
 * @subpackage Skos_Hierarchy
 */

require_once("skosCollection.php");


/**
 * Foxml object with programs/SKOS datastream.
 * Extending from foxml class in order to inherit existing
 * functionality for interacting with Fedora.
 * 
 */
class foxmlCollection extends foxmlSkosCollection {
  public function __construct($id = "#programs") {
    // initialize with a pid specified in the config - complain if it is not available
    if (! Zend_Registry::isRegistered("config")) {
      throw new FoxmlException("Configuration not registered, cannot retrieve pid");
    }
    $config = Zend_Registry::get("config");
    if (! isset($config->programs_collection->pid) || $config->programs_collection->pid == "") {
      throw new FoxmlException("Configuration does not contain program pid, cannot initialize");
    }
    parent::__construct($config->programs_collection->pid);

    // initializing SKOS datastream here in order to pass a collection id
    $ds = "skos";
    $dom = new DOMDocument();
    $xml = $this->fedora->getDatastream($this->pid, $this->xmlconfig[$ds]['dsID']);
    if ($xml) {
      $dom->loadXML($xml);
      $this->map[$ds] = new $this->xmlconfig[$ds]['class_name']($dom, $id);
    }
  }
  protected function configure() {
    parent::configure();
    $this->xmlconfig["skos"]["class_name"] = "gencoll";
  }  

}

/**
 * custom version of collectionHierarchy for programs/departments
 */
class gencoll extends collectionHierarchy  {
  protected $collection_class = "genCollection";

  public function __construct($dom, $id = "#programs") {
    parent::__construct($dom, $id);
    $this->index_field = "program_facet";
  }

  public function getFields($mode) {
    $fields = array();

    array_push($fields, (string)$this->getId());

    foreach ($this->members as $member)
      $fields = array_merge($fields, $member->getFields($mode));

    return $fields;
  }



}

/* Using custom program collection & member classes in order to
   customize indexed function used to retrieve all indexed fields for
   generating Solr query.
*/


class genCollection extends skosCollection {
  protected $member_class = "genMember";

  // indexed on id instead of label
  protected function getIndexedData() {
    return (string)$this->getId();
  }
  
}

class genMember extends skosMember {
  protected $collection_class = "genCollection";

  protected function isIndexed() {
    // bottom level element - field with no subfields or subfield
    if (count($this->collection->members) == 0) return true;

    /* There is no good way to detect fields with subfields, since
       member has no access upwards in the hierarchy; looking for ids
       of known fields with subfields.
    */
    if (in_array($this->id, array("#religion", "#biosci", "#psychology"))) return true;

    // otherwise, this item is not indexed
    return false; 
  }

  // indexed on id instead of label
  protected function getIndexedData() {
    return (string)$this->getId();
  }
    

}
