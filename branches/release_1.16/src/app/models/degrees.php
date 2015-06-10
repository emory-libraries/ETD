<?
/**
 * minimal object interface to degree name & level configuration
 * - used to set PQ degree codes for ProQuest submissions
 * @category Etd
 * @package Etd_Models
 * @subpackage ProQuest_Submission
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

  /**
   * Associative array of degree options for use in an edit form.
   * First-level key is genre, value is an associative array of degree
   * names.
   */
  public function edit_options() {
    $nodelist = $this->xpath->query("/degrees/level");
    $opts = array('' => '----');  // empty value first
    for ($i = 0; $i < $nodelist->length; $i++) {
      $level = $nodelist->item($i);
      $level_opts = array();
      $degreelist = $level->getElementsByTagName('degree');
      for ($j = 0; $j < $degreelist->length; $j++) {
        $degree = $degreelist->item($j);
        $level_opts[$degree->getAttribute('name')] = $degree->getAttribute('name');
      }
      $opts[$level->getAttribute('genre')] = $level_opts;
    }
    return $opts;
  }

  /**
   * Associative array of information for each degree, keyed on degree
   * name.  Each degree has a "level" and a genre.
   */
  public function degree_info() {
    $nodelist = $this->xpath->query("/degrees/level");
    $degrees = array();
    for ($i = 0; $i < $nodelist->length; $i++) {
      $level = $nodelist->item($i);
      $degreelist = $level->getElementsByTagName('degree');
      for ($j = 0; $j < $degreelist->length; $j++) {
        $degree = $degreelist->item($j);
        $degrees[$degree->getAttribute('name')] = array('level' => $level->getAttribute('name'),
                                                        'genre' => $level->getAttribute('genre'));
      }
    }
    return $degrees;
  }

}

?>