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
class foxmlVocabularies extends foxmlSkosCollection {
  public function __construct($id = "#vocabularies") {
    // initialize with a pid specified in the config - complain if it is not available
    if (! Zend_Registry::isRegistered("config")) {
      throw new FoxmlException("Configuration not registered, cannot retrieve pid");
    }
    $config = Zend_Registry::get("config");
    if (! isset($config->vocabularies_pid) || $config->vocabularies_pid == "") {
      throw new FoxmlException("Configuration does not contain vocabularies pid, cannot initialize");
    }
    parent::__construct($config->vocabularies_pid);

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
    $this->xmlconfig["skos"]["class_name"] = "vocabularies";
  }  

}

/**
 * custom version of collectionHierarchy for programs/departments
 */
class vocabularies extends collectionHierarchy  {

  public function __construct($dom, $id = "#vocabularies") {
    parent::__construct($dom, $id);
  }

}
