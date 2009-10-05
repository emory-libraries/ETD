<?

require_once("skosCollection.php");

class researchfields extends collectionHierarchy  {
  protected $collection_class = "researchfieldCollection";
    
  public function __construct($id = "#researchfields") {
    $xml = file_get_contents("umi-researchfields.xml", FILE_USE_INCLUDE_PATH); 
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    parent::__construct($dom, $id);

    $this->index_field = "subject_facet";
  }
}

/* Using custom researchfield collection & member classes in order to
   customize indexed function used to retrieve all indexed fields for
   generating Solr query
*/

class researchfieldCollection extends skosCollection {
  protected $member_class = "researchfieldMember";
}

class researchfieldMember extends skosMember {
  protected $collection_class = "researchfieldCollection";

  protected function isIndexed() {
    // only actual UMI fields with numeric codes are indexed
    if (preg_match("/#[0-9]{4}/", $this->id)) return true;
    
    return false; 
  }

}
