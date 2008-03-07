<?

require_once("skosCollection.php");

class researchfields extends collectionHierarchy  {
  public function __construct($id = "#researchfields") {
    $xml = file_get_contents("umi-researchfields.xml", FILE_USE_INCLUDE_PATH); 
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    parent::__construct($dom, $id);

    $this->index_field = "subject_facet";
  }
}