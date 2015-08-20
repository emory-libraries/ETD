<?
/**
 * minimal object interface to languages
 *  - used to set PQ language codes for ProQuest submissions
 * @category Etd
 * @package Etd_Models
 * @subpackage ProQuest_Submission
 */

class languages {

  private $dom;
  private $xpath;

  public function __construct() {
    $this->dom = new DOMDocument();
    $this->dom->loadXML(file_get_contents("languages.xml", FILE_USE_INCLUDE_PATH));

    $this->xpath = new DOMXpath($this->dom);
  }


  public function codeByName($name) {
    $nodelist = $this->xpath->query("/languages/language[@display = '$name']");
    if ($nodelist->length == 1) {	// we want one and only one match
      $lang = $nodelist->item(0);
      return $lang->getAttribute("pq_code");
    } else {
      // language not found
      trigger_error("Error! No language code found for $name", E_USER_WARNING);
    }
  }

  /**
   * Associative array of language options for use in an edit form.
   * Key is language code, value is display form.
   */
  public function edit_options() {
    $nodelist = $this->xpath->query("/languages/language");
    $opts = array();
    for ($i = 0; $i < $nodelist->length; $i++) {
      $lang = $nodelist->item($i);
      $opts[$lang->getAttribute('code')] = $lang->getAttribute('display');
    }
    return $opts;
  }


}
