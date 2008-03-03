<?

require_once("xml-utilities/XmlObject.class.php");


class collectionHierarchy extends XmlObject {
  protected $rdf_namespace = "http://www.w3.org/1999/02/22-rdf-syntax-ns#";
  protected $rdfs_namespace = "http://www.w3.org/2000/01/rdf-schema#";
  protected $skos_namespace = "http://www.w3.org/2004/02/skos/core#";

  public $id;
  protected $members_by_id;


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
				   "class_name" => "skosCollection"),
	     "parent_id" => array("xpath" => "skos:Collection[skos:member/@rdf:resource = '" . $id . "']/@rdf:about"),
				       ));
    parent::__construct($dom, $config, null);	// no xpath

    $this->members_by_id = array();
    if (isset($this->collection)) {
      foreach ($this->collection->members as $mem) {
	$id = str_replace("#", "", $mem->id);
	$this->members_by_id[$id] = $mem;
      }
    }
	  
    // if this collection has a parent initialize parent as another collection object
    if (isset($this->parent_id)) {
      $this->parent = new collectionHierarchy($this->dom, (string)$this->parent_id);
    } else {
      $this->parent = null;
    }
  }

  // shortcuts to fields that are really attributes of the collection 
  public function __get($name) {
    if (isset($this->members_by_id[$name]))
	return $this->members_by_id[$name];
    
    switch($name) {
    case "label":
      return $this->collection->label;
    case "members":
      return $this->collection->members;
    case "count":
      return $this->collection->count;
    default:
      return parent::__get($name);
    }
  }

  public function getAllFields() {
    $fields = array();
    
    array_push($fields, (string)$this->label);

    foreach ($this->members as $member)
      $fields = array_merge($fields, $member->getAllFields());

    return $fields;
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
    if ($nodeList->length == 1) {
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


  
  public function findEtds() {

     $solr = Zend_Registry::get('solr');
     // get all fields of this collection and its members (all the way down)
     $all_fields = $this->getAllFields();
     
     // construct a query that will find any of these
     $queryparts = array();
     foreach ($all_fields as $field) {
       if (strpos($field, '&')) $field = urlencode($field);
       if (strpos($field, ' ')) $searchfield = '"' . $field . '"';
       else $searchfield = $field;
       array_push($queryparts, $this->index_field .':' . $searchfield);
     }

     $query = join($queryparts, " OR ");

     //     print "query is: " . $query . "<br/>\n";


     /* don't retrieve etd records at top level of hierarchy */ 
     if (isset($this->parent)) $return_num = null;		// use defaults
     else $return_num = 0;	     // NOTE: setting to no returns speeds things up substantially
     $results = $solr->query($query, $return_num, null, -1);
     $totals = $results['facet_counts']['facet_fields'][$this->index_field];

     // sum up totals recursively
     $this->collection->calculateTotal($totals);

     return $results;
  }

  
}




class skosCollection extends XmlObject {
  protected $members_by_id;
  public $count;
  
  public function __construct($dom, $xpath) {
    $id = $dom->getAttributeNS("http://www.w3.org/1999/02/22-rdf-syntax-ns#", "about");
    $config = $this->config(array(
	"label" => array("xpath" => "rdfs:label"), 
      //      "label" => array("xpath" => "skos:prefLabel"),
	//	"collections" => array("xpath" => "//skos:Collection[@rdf:about = ./skos:member/@rdf:resource]",
	//			  "is_series" => true, "class_name" => "skosCollection"),
	"members" => array("xpath" => "skos:member", "is_series" => true, "class_name" => "skosMember"),
      
      ));
    parent::__construct($dom, $config, $xpath);

    $this->members_by_id = array();
    foreach ($this->members as $mem) {
      $id = str_replace("#", "", $mem->id);
      $this->members_by_id[$id] = $mem;
    }

 }

  // shortcuts to fields that are really attributes of the collection 
  public function __get($name) {
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
    //    print "DEBUG: calculating total for " . $this->label . ", starts as $sum<br/>\n";
    foreach ($this->members as $mem) {
      //      print "DEBUG: adding " . $mem->label . "<br/>\n";
      $sum += $mem->calculateTotal($totals);
    }
    $this->count = $sum;
    //    print "DEBUG: total for {$this->label} is {$this->count}<br/>\n";
    return $this->count;
  }

  
}

class skosMember extends XmlObject {

  public function __construct($dom, $xpath) {
    $id = $dom->getAttributeNS("http://www.w3.org/1999/02/22-rdf-syntax-ns#", "resource");
    //    print "in skosMember constructor, id is $id\n";
    $config = $this->config(array(
	 "id" =>  array("xpath" => "@rdf:resource"),
	 "collection" => array("xpath" => "//skos:Collection[@rdf:about='" . $id . "']",
			       "class_name" => "skosCollection"),
	 ));
    parent::__construct($dom, $config, $xpath);
  }

  // shortcuts to fields that are really attributes of the collection 
  public function __get($name) {
    if (isset($this->collection->$name))
      return $this->collection->$name;
      return parent::__get($name);
  }

  public function getAllFields() {
    $fields = array();

    // add current element    
    array_push($fields, (string)$this->label);

    // add any collection members
    if ($this->collection)
      foreach ($this->collection->members as $member)
	$fields = array_merge($fields, $member->getAllFields());
    return $fields;
  }

  public function calculateTotal($totals) {
    return $this->collection->calculateTotal($totals);
  }

}

