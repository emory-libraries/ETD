<?

require_once("xml-utilities/XmlObject.class.php");


class collectionHierarchy extends XmlObject {
  protected $rdf_namespace = "http://www.w3.org/1999/02/22-rdf-syntax-ns#";
  protected $rdfs_namespace = "http://www.w3.org/2000/01/rdf-schema#";
  protected $skos_namespace = "http://www.w3.org/2004/02/skos/core#";

  public $id;
  protected $members_by_id;

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
	  
    // if this collection has a parent initialize parent as another program object
    if (isset($this->parent_id)) {
      $this->parent = new programs($this->dom, (string)$this->parent_id);
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
      break;
    case "members":
      return $this->collection->members;
      break;
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
    //    $xpath = "//skos:Collection[rdfs:label = '$string' or contains(rdfs:label, '$string')]/rdf:about";
    $xpath = "//skos:Collection[rdfs:label[. = '$string' or contains(., '$string')]]";
    $nodeList = $this->xpath->query($xpath, $this->domnode);
    if ($nodeList->length == 1) {
      return $nodeList->item(0)->getAttributeNS($this->rdf_namespace, "about");
    } else {
      return null;
    }
   
  }

}




class skosCollection extends XmlObject {
  protected $members_by_id;
    
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

}

