<?

require_once("skosCollection.php");

class programs extends collectionHierarchy  {
  public function __construct($dom, $id = "#researchfields") {
    parent::__construct($dom, $id);
  }
}