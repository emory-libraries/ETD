<?

require_once("skosCollection.php");

class programs extends collectionHierarchy  {
  
  public function __construct($dom, $id = "#programs") {
    parent::__construct($dom, $id);

    $this->index_field = "program";
  }
}
