<?

require_once("xml-utilities/XmlObject.class.php");
require_once("fedora/models/foxml.php");

/**
 * xml object mapping for a collection defined with skos and rdf
 */
class collectionHierarchy extends foxmlDatastreamAbstract {
  const RDF = "http://www.w3.org/1999/02/22-rdf-syntax-ns#";
  const RDFS = "http://www.w3.org/2000/01/rdf-schema#";
  const SKOS = "http://www.w3.org/2004/02/skos/core#";

  const dslabel = "Collection Hierarchy";
    
  protected $rdf_namespace = "http://www.w3.org/1999/02/22-rdf-syntax-ns#";
  protected $rdfs_namespace = "http://www.w3.org/2000/01/rdf-schema#";
  protected $skos_namespace = "http://www.w3.org/2004/02/skos/core#";

  public $id;
  protected $collection_class = "skosCollection";

  protected $index_field;

  // reference to parent collection (if not at top level)
  public $parent;
  
  public function __construct($dom, $id) {
    $this->id = $id;
    $this->addNamespace("rdf", $this->rdf_namespace);
    $this->addNamespace("rdfs", $this->rdfs_namespace);
    $this->addNamespace("skos", $this->skos_namespace);
    
       $config = $this->config(array(
	     "collection" => array("xpath" => "skos:Collection[@rdf:about = '" . $this->id . "']",
				   "class_name" => $this->collection_class),
	     "parent_id" => array("xpath" => "skos:Collection[skos:member/@rdf:resource = '" . $id . "']/@rdf:about"),
				       ));
    parent::__construct($dom, $config, null);	// no xpath

    if (! isset($this->collection)) {
      // collection not found - bad initialization
      throw new XmlObjectException("Error in constructor: collection id '" . $id . "' not found");
    }
	  
    // if this collection has a parent initialize parent as another collection object
    if (isset($this->parent_id)) {
      $this->parent = new collectionHierarchy($this->dom, (string)$this->parent_id);
    } else {
      $this->parent = null;
    }
  }

  // shortcuts to fields that are really attributes of the collection 
  public function &__get($name) {
    // label, members, count, and members by id
    if (isset($this->collection->{$name}))
      return $this->collection->{$name};
    
    switch($name) {
    case "label":
      if (isset($this->collection->label)) return $this->collection->label;      
    case "members":
      if (isset($this->collection->members)) return $this->collection->members;
    case "count":
      return $this->collection->count;
      default:
      return parent::__get($name);
    }
  }

  public function __set($name, $value) {
    switch($name) {
    case "label":
      return $this->collection->label = $value;
    default:
      return parent::__set($name, $value);
    }
  }

  
  public function getFields($mode) {
    $fields = array();

    array_push($fields, (string)$this->label);

    foreach ($this->members as $member)
      $fields = array_merge($fields, $member->getFields($mode));

    return $fields;
  }

  public function getAllFields() {
    return $this->getFields("all");
  }
  
  public function getIndexedFields() {
    return $this->getFields("indexed");
  }


  // find the label for a matching word (may need improvement) - used to map departments to our hierarchy
  public function findLabel($string) {
    $xpath = "//rdfs:label[. = '$string' or contains(., '$string')]";
    $nodeList = $this->xpath->query($xpath, $this->domnode);
    if ($nodeList->length == 1) {
      return $nodeList->item(0)->nodeValue;
    } else {
      return null;
    }
   
  }

  public function findIdbyLabel($string) {
    // look for an exact match first
    $xpath = "//skos:Collection[rdfs:label = '$string']"; 
    $nodeList = $this->xpath->query($xpath, $this->domnode);
    if ($nodeList->length >= 1) {
      // NOTE: if multiple matches are found, returns the first only (not ideal)
      return $nodeList->item(0)->getAttributeNS($this->rdf_namespace, "about");
    } 

    // if exact match fails, find a partial match
    $xpath = "//skos:Collection[contains(rdfs:label, '$string')]";
    if ($nodeList->length == 1) {
      return $nodeList->item(0)->getAttributeNS($this->rdf_namespace, "about");
    } else {
      return null;
    }
   
  }

  public function findLabelbyId($id) {
    $xpath = "//skos:Collection[@rdf:about = '$id']/rdfs:label"; 
    $nodeList = $this->xpath->query($xpath, $this->domnode);
    if ($nodeList->length >= 1) {
      // NOTE: if multiple matches are found, returns the first only (not ideal)
      return $nodeList->item(0)->nodeValue;
    }
    else return null;
  }

  


  
  public function findEtds($options = array()) {
     // get all fields of this collection and its members (all the way down)
     $all_fields = $this->getIndexedFields();
     
     // construct a query that will find any of these
     $queryparts = array();
     foreach ($all_fields as $field) {
       if (strpos($field, '&')) $field = urlencode($field);
       if (strpos($field, ' ')) $searchfield = '"' . $field . '"';
       else $searchfield = $field;
       array_push($queryparts, $this->index_field .':' . $searchfield);
     }

     $query = "(" . join($queryparts, " OR ") . ")"; 

     /*     foreach ($options as $filter => $value) {
      $query .= "AND $filter:($value)";	
      }*/

     $options["query"] = $query;
     
     /* don't retrieve etd records at top level of hierarchy */ 
     if (! isset($this->parent)) $options["max"] = 0;  // NOTE: setting to no returns speeds things up substantially


     
     //     $solr->setFacetLimit(-1);		// no limit - return all facets
     //     $results = $solr->queryPublished($query, $start, $return_num);

     // return minimal solrEtd for quicker browse results
     $options["return_type"] = "solrEtd";

     $etdSet = new EtdSet();
     $etdSet->findPublished($options);
     $totals = $etdSet->facets->{$this->index_field};

     // sum up totals recursively
     $this->collection->calculateTotal($totals);

     return $etdSet;
  }


  public static function getNamespaces() {
    return array("rdf" => collectionHierarchy::RDF,
		 "rdfs" => collectionHierarchy::RDFS,
		 "skos" => collectionHierarchy::SKOS);
  }



  /** required to extend foxmlDatastreamAbstract - but not currently used **/
  public static function getFedoraTemplate(){
    return foxml::xmlDatastreamTemplate("SKOS", collectionHierarchy::dslabel,
					'<rdf:RDF
  xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
  xmlns:skos="http://www.w3.org/2004/02/skos/core#"
  xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"/>');
  }
  
  public function datastream_label() {
    return collectionHierarchy::dslabel;
  }

  public function getId() {
    if (isset($this->id)) return preg_replace("/^#/", '', $this->id);
  }

  public function setMembers($ids) {
    if (isset($this->collection))
      $this->collection->setMembers($ids);
  }
  
}



class skosCollection extends XmlObject {
  protected $members_by_id;
  public $count;

  protected $member_class = "skosMember";
  
  public function __construct($dom, $xpath) {
    $id = $dom->getAttributeNS("http://www.w3.org/1999/02/22-rdf-syntax-ns#", "about");
    $config = $this->config(array(
	"label" => array("xpath" => "rdfs:label"), 
	"members" => array("xpath" => "skos:member", "is_series" => true,
			   "class_name" => $this->member_class),
      
      ));
    parent::__construct($dom, $config, $xpath);
    $this->set_members_by_id();
  }
  
  protected function set_members_by_id() {
    $this->members_by_id = array();
    foreach ($this->members as $mem) {
      $id = str_replace("#", "", $mem->id);
      $this->members_by_id[$id] = $mem;
    }
  }

  // after updating in memory map, re-initialize members by id map
  protected function update() {
    parent::update();
    $this->set_members_by_id();
  }

  // shortcuts to fields that are really attributes of the collection 
  public function &__get($name) {
    if (isset($this->members_by_id[$name]))
      return $this->members_by_id[$name];

    return parent::__get($name);
  }

  public function __isset($name) {
    if (isset($this->members_by_id[$name]))
      return true;
    else return parent::__isset($name);
  }


  public function calculateTotal($totals) {
    $sum = isset($totals[$this->label]) ? $totals[$this->label] : 0;
    foreach ($this->members as $mem) {
      $sum += $mem->calculateTotal($totals);
    }
    $this->count = $sum;
    return $this->count;
  }

  public function getId() {
    if (isset($this->id)) return preg_replace("/^#/", '', $this->id);
  }

  public function hasMember($id) {
    if (in_array($id, array_keys($this->members_by_id))) return true;
    else return false;
  }
  
  public function setMembers($ids) {
    // convert ids to the expected format if they are not already that way
    $ids = preg_replace("/^([^#])/", "#$1", $ids);
    
    for ($i = 0; $i < count($ids); $i++) {
      // FIXME: warn if id does not correspond to a rdf:resource somewhere in the DOM
      if (isset($this->members[$i])) {
	// update id in existing member nodes
	$this->members[$i]->id = $ids[$i];
      } else {
	// add new member node with new order
	$this->addMember($ids[$i]);
      }
    }

    // remove any current members that are beyond the list of ids
    $rm_ids = array();
    for ($i = count($ids); $i < count($this->members); $i++) {
      $rm_ids[] = $this->members[$i]->id;
    }
    // NOTE: removing after getting ids to avoid iterating over a disappearing list
    foreach ($rm_ids as $id) $this->removeMember($id);
    // update memory mappings, etc.
    $this->update();
  }

  public function removeMember($id) {
    // find requested member under this node
    $nodelist = $this->xpath->query("//skos:member[@rdf:resource='$id']", $this->domnode);
    // FIXME: warn if length != 1 ?
    for ($i = 0; $i < $nodelist->length; $i++) {
      $node = $nodelist->item($i);      
      $node->parentNode->removeChild($node);
    }
    $this->update();
  }
    
  public function addMember($id) {
    $newnode = $this->dom->createElementNS(collectionHierarchy::SKOS, "skos:member");
    $newnode->setAttributeNS(collectionHierarchy::RDF, "rdf:resource", $id);
    $this->domnode->appendChild($newnode);
    $this->update();
  }

}

class skosMember extends XmlObject {
  protected $collection_class = "skosCollection";
  
  public function __construct($dom, $xpath) {
    $id = $dom->getAttributeNS("http://www.w3.org/1999/02/22-rdf-syntax-ns#", "resource");
    $config = $this->config(array(
	 "id" =>  array("xpath" => "@rdf:resource"),
	 "collection" => array("xpath" => "//skos:Collection[@rdf:about='" . $id . "']",
			       "class_name" => $this->collection_class),
	 ));
    parent::__construct($dom, $config, $xpath);
  }

  // shortcuts to fields that are really attributes of the collection 
  public function &__get($name) {   
    if (isset($this->collection->$name)) {
      return $this->collection->$name;
    }
    return parent::__get($name);
  } 

  public function __set($name, $value) {
    if (isset($this->collection->$name))
      return $this->collection->$name = $value;
    return parent::__set($name, $value);
  }

  public function getFields($mode) {
    $fields = array();

    // add current element if appropriate
    if ($mode == "all" ||
	($mode == "indexed" && $this->isIndexed())) {
	array_push($fields, (string)$this->label);
    }

    // add any collection members
    if ($this->collection)
      foreach ($this->collection->members as $member)
	$fields = array_merge($fields, $member->getFields($mode));
    return $fields;
  }

  // by default, assume indexed-- override this function only when needed
  protected function isIndexed() {
    return true;
  }

  public function calculateTotal($totals) {
    return $this->collection->calculateTotal($totals);
  }

  public function hasChildren() {
    
  }
  public function getId() {
    if (isset($this->id)) return preg_replace("/^#/", '', $this->id);
  }
  public function setMembers($ids) {
    if (isset($this->collection))
      $this->collection->setMembers($ids);
  }


}


/**
 * minimal fedora object with collectionHierarchy SKOS datastream 
 */
class foxmlSkosCollection extends foxml {

  // configure additional datastreams here 
  protected function configure() {
    parent::configure();

    $this->addNamespace("rdf", collectionHierarchy::RDF);
    $this->addNamespace("rdfs", collectionHierarchy::RDFS);
    $this->addNamespace("skos", collectionHierarchy::SKOS);
    
    // add mappings for xmlobject
    $this->xmlconfig["skos"] = array("xpath" => "//foxml:datastream[@ID='SKOS']/foxml:datastreamVersion/foxml:xmlContent/rdf:RDF",
				     "class_name" => "collectionHierarchy", "dsID" => "SKOS");
  }
}
