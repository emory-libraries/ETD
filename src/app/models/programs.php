<?

require_once("skosCollection.php");

class programs extends collectionHierarchy  {
  
  public function __construct($id = "#programs") {
    
    $xml = file_get_contents("programs.xml", FILE_USE_INCLUDE_PATH); 
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    
    parent::__construct($dom, $id);

    $this->index_field = "program_facet";
  }
}
