<?

require_once("skosCollection.php");


/**
 * Foxml object with programs/SKOS datastream.
 * Extending from foxml class in order to inherit existing
 * functionality for interacting with Fedora.
 * 
 */
class foxmlPrograms extends foxmlSkosCollection {
  public function __construct($id = "#programs") {
    // initialize with a pid specified in the config - complain if it is not available
    if (! Zend_Registry::isRegistered("config")) {
      throw new FoxmlException("Configuration not registered, cannot retrieve pid");
    }
    $config = Zend_Registry::get("config");
    if (! isset($config->programs_pid) || $config->programs_pid == "") {
      throw new FoxmlException("Configuration does not contain program pid, cannot initialize");
    }
    parent::__construct($config->programs_pid);

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
    $this->xmlconfig["skos"]["class_name"] = "programs";
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

}

/* Using custom program collection & member classes in order to
   customize indexed function used to retrieve all indexed fields for
   generating Solr query.
*/


class programCollection extends skosCollection {
  protected $member_class = "programMember";
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

}
