<?

/* minimal object interface to country list
   - used to set PQ country codes for ProQuest submissions
 */

class countries {

  private $dom;
  private $xpath;
  
  public function __construct() {
    $this->dom = new DOMDocument();
    $this->dom->loadXML(file_get_contents("countries.xml", FILE_USE_INCLUDE_PATH));

    $this->xpath = new DOMXpath($this->dom);
  }


  public function codeByName($name) {
    $nodelist = $this->xpath->query("/countries/country[. = '$name']");
    if ($nodelist->length == 1) {	// we want one and only one match
      $country = $nodelist->item(0);
      return $country->getAttribute("code");
    } else {
      // country not found
      trigger_error("Error! No country code found for $name", E_USER_WARNING);
    }
  }

  
}

?>