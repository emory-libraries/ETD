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
  private $collection_id;
 
  public function __construct($id = "#programs", $collection = "#programs") {
   
    $create_collection = false;

    // initialize with a pid specified in the config - complain if it is not available
    if (! Zend_Registry::isRegistered("config")) {
      throw new FoxmlException("Configuration not registered, cannot retrieve pid");
    }
    $config = Zend_Registry::get("config"); 
       
    $this->collection_id = preg_replace("/^#/", '', $collection);
    $config_collection = $this->collection_id . "_collection";
               
    if (! isset($config->{$config_collection}->pid) || $config->{$config_collection}->pid == "") {
      throw new FoxmlException("Configuration does not contain " . $this->collection_id . " pid, cannot initialize");
    }
   
    try {    
      parent::__construct($config->{$config_collection}->pid);
      // initializing SKOS datastream here in order to pass a collection id
      $ds = "skos";
      $dom = new DOMDocument();
      $xml = $this->fedora->getDatastream($this->pid, $this->xmlconfig[$ds]['dsID']);
      if ($xml) {
        $dom->loadXML($xml);
        $this->map[$ds] = new $this->xmlconfig[$ds]['class_name']($dom, $id);
      }      
    }
    catch (FedoraObjectNotFound $e) { // Collection does not exist in fedora.  
      $create_collection = true;      
    }
   
    if ($create_collection) {
      $this->createCollection(
          $config->{$config_collection}->pid,
          $config->{$config_collection}->label,
          $config->etdOwner,
          $config->{$config_collection}->model_object
      );
    }    
  }
  protected function configure() {  
    parent::configure();
    $this->xmlconfig["skos"]["class_name"] = $this->collection_id;
  }

  public function createCollection($pid, $label, $owner, $model) {
    $col = new foxmlSkosCollection();
   
    // Add a model in the RELS-EXT datastream (Subject/Predicate/Object)
    $col->setContentModel($model);    
           
    $col->pid = $pid;      
    $col->label = $label;
    $col->owner = $owner;  
    $col->ingest("creating ETD foxmlSkosCollection object for [$pid] collection hierarchy");  
    return $col;
  }    

}

/**
 * custom version of collectionHierarchy for programs/departments
 */
class programs extends collectionHierarchy  {
  protected $collection_class = "programCollection";

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

/**
 * custom version of collectionHierarchy for partnering agencies
 */
class vocabularies extends collectionHierarchy  {

  public function __construct($dom, $id = "#vocabularies") {
    parent::__construct($dom, $id);
  }

}

/**
 * custom version of collectionHierarchy for partnering agencies
 */
class test extends collectionHierarchy  {

  public function __construct($dom, $id = "#test") {
    parent::__construct($dom, $id);
  }

}
