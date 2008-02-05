<?

require_once("skosCollection.php");

class researchfields extends collectionHierarchy  {
  public function __construct($dom, $id = "#researchfields") {
    parent::__construct($dom, $id);

    $this->index_field = "subject_facet";
  }
}