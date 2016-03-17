<?
/**
 * @category Etd
 * @package Etd_Models
 * @subpackage Skos_Hierarchy
 */

require_once("skosCollection.php");

/**
 * Foxml object with vocabulary SKOS datastream.
 * Extending from foxml class in order to inherit existing
 * functionality for interacting with Fedora.
 * 
 */
class foxmlVocabularies extends foxmlSkosCollection {
  public function __construct($id=null) {
    // initialize with a pid specified in the config - complain if it is not available
    if (! Zend_Registry::isRegistered("config")) {
      throw new FoxmlException("Configuration not registered, cannot retrieve pid");
    }
    $config = Zend_Registry::get("config");
    if (! isset($config->vocabularies_collection->pid) || $config->vocabularies_collection->pid == "") {
      throw new FoxmlException("Configuration does not contain vocabularies pid, cannot initialize");
    }  
    
    // the default parameter setting will not allow '#' character, so set it here.
    if (!isset($id)) $id = "#vocabularies";
    
    parent::__construct($config->vocabularies_collection->pid);

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
      $this->pid = $config->vocabularies_collection->pid;
      $this->label = $config->vocabularies_collection->label;
      $this->skos->dslabel = $config->vocabularies_collection->skos_label;       
      $this->owner = $config->etdOwner;
      
      // Add a model in the RELS-EXT datastream (Subject/Predicate/Object)
      $this->setContentModel($config->vocabularies_collection->model_object);         
    }
          
  }
  protected function configure() {
    parent::configure();
    $this->xmlconfig["skos"]["class_name"] = "vocabularies";
  }  

}

/**
 * custom version of collectionHierarchy for vocabularies (partnering agencies)
 */
class vocabularies extends collectionHierarchy  {

  public function __construct($dom=null, $id=null) {
    // The # char is not acceptable in parameter assignment     
    if (is_null($id))    $id = "#vocabularies";     
    parent::__construct($dom, $id);   
  }

  // this template is used when creating a new fedora object to ingest.
  public function construct_from_template(){       
    $base = '<rdf:RDF
      xmlns:dc="http://purl.org/dc/elements/1.1/"
      xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
      xmlns:skos="http://www.w3.org/2004/02/skos/core#"
      xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
      <skos:Collection rdf:about="#vocabularies">
      <rdfs:label>Vocabularies</rdfs:label>
      </skos:Collection></rdf:RDF>';     
    $dom = new DOMDocument(); 
    $dom->loadXML($base); 
    return $dom;
  }

}
