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
    
    if (isset($id)) { // We attempt to open an existing fedora object.     
      parent::__construct($config->vocabularies_collection->pid);

      // initializing SKOS datastream here in order to pass a collection id
      $ds = "skos";
      $dom = new DOMDocument();
      $xml = $this->fedora->getDatastream($this->pid, $this->xmlconfig[$ds]['dsID']);
      if ($xml) {
        $dom->loadXML($xml);
        $this->map[$ds] = new $this->xmlconfig[$ds]['class_name']($dom, $id);
      }
    }
    else {  // Create a new fedora object using contruct_by_template
      parent::__construct();      
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

  public function __construct($dom, $id = "#vocabularies") {
    parent::__construct($dom, $id);
  }

  // this template is used when creating a new fedora object to ingest.
  public static function getFedoraTemplate(){   
    return foxml::xmlDatastreamTemplate("SKOS", collectionHierarchy::dslabel,
          '<rdf:RDF
          xmlns:dc="http://purl.org/dc/elements/1.1/"
          xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
          xmlns:skos="http://www.w3.org/2004/02/skos/core#"
          xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
          <skos:Collection rdf:about="#vocabularies">
          <rdfs:label>Vocabularies Hierarchy</rdfs:label>
          </skos:Collection></rdf:RDF>');
  } 
}
