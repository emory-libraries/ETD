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
class foxmlPrograms extends foxmlSkosCollection {
  public function __construct($id=null) {
    // initialize with a pid specified in the config - complain if it is not available
    if (! Zend_Registry::isRegistered("config")) {
      throw new FoxmlException("Configuration not registered, cannot retrieve pid");
    }
    $config = Zend_Registry::get("config");
    if (! isset($config->programs_collection->pid) || $config->programs_collection->pid == "") {
      throw new FoxmlException("Configuration does not contain program pid, cannot initialize");
    }
     
     // the default parameter setting will not allow '#' character, so set it here.
    if (!isset($id)) $id = "#programs";

    parent::__construct($config->programs_collection->pid);
    
    // initializing SKOS datastream here in order to pass a collection id
    $dom = new DOMDocument();
    $ds = "skos";     
    $fedora_object_exists = true;
    try {
      $xml = $this->fedora->getDatastream($this->pid, $this->xmlconfig[$ds]['dsID']);     
      if ($xml) {
        $dom->loadXML($xml);       
        $this->map[$ds] = new $this->xmlconfig[$ds]['class_name']($dom, $id);
      }
     
      // set datastream info on datastream object so it can be saved correctly
      $info = $this->fedora->getDatastreamInfo($this->pid, $this->xmlconfig[$ds]['dsID']);
      $this->map[$ds]->setDatastreamInfo($info, $this);
    } 
    catch (Exception $err) {  // Error detected when fedora object does not exist.
      $fedora_object_exists = false;    
    }
    
    if (!$fedora_object_exists) { // Create a new fedora object.
      parent::__construct(); 

      // Set the properties for a newly create fedora object.
      $this->pid = $config->programs_collection->pid;
      $this->label = $config->programs_collection->label;
      $this->skos->dslabel = $config->programs_collection->skos_label;       
      $this->owner = $config->etdOwner;
      
      // Add a model in the RELS-EXT datastream (Subject/Predicate/Object)
      $this->setContentModel($config->programs_collection->model_object);      
    }
  }
  protected function configure() {
    parent::configure();
    $this->xmlconfig["skos"]["class_name"] = "programs";
  }  
}

/**
 * custom version of collectionHierarchy for programs/departments
 */
class programs extends collectionHierarchy  {
  protected $collection_class = "programCollection";

  public function __construct($dom=null, $id=null) {   
    // The # char is not acceptable in parameter assignment     
    if (is_null($id)) $id = "#programs"; 
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

  // this template is used when creating a new fedora object to ingest.
  public function construct_from_template(){    
    $base = '<rdf:RDF
      xmlns:dc="http://purl.org/dc/elements/1.1/"
      xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
      xmlns:skos="http://www.w3.org/2004/02/skos/core#"
      xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
      <skos:Collection rdf:about="#programs">
      <rdfs:label>Programs</rdfs:label>
      </skos:Collection></rdf:RDF>';        
    $dom = new DOMDocument();      
    $dom->loadXML($base);     
    return $dom;
  }

}

/* Using custom program collection & member classes in order to
   customize indexed function used to retrieve all indexed fields for
   generating Solr query.
*/

class programCollection extends skosCollection {
  protected $member_class = "programMember";

  // indexed on id instead of label
  protected function getIndexedData() {
    return (string)$this->getId();
  }
}

class programMember extends skosMember {
  protected $collection_class = "programCollection";

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
