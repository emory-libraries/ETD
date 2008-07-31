<?

require_once("skosCollection.php");

class programs extends collectionHierarchy  {
  protected $collection_class = "programCollection";
  
  public function __construct($id = "#programs") {
    
    $xml = file_get_contents("programs.xml", FILE_USE_INCLUDE_PATH); 
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    
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
