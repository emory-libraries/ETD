<?

/* minimal object interface to degree name & level configuration
   - used to set PQ degree codes for ProQuest submissions
 */

class degrees {

  private $dom;
  private $xpath;
  
  public function __construct() {
    $this->dom = new DOMDocument();
    $this->dom->loadXML(file_get_contents("degrees.xml", FILE_USE_INCLUDE_PATH));

    $this->xpath = new DOMXpath($this->dom);
  }


  public function codeByAbbreviation($name) {
    $nodelist = $this->xpath->query("/degrees/level/degree[@name = '$name']");
    if ($nodelist->length == 1) {	// we want one and only one match
      $degree = $nodelist->item(0);
      return $degree->getAttribute("pq_code");
    } else {
      // degree not found
      trigger_error("Error! No degree code found for $name", E_USER_WARNING);
    }
  }

  
}

?>