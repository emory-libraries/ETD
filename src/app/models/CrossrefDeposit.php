<?php
/**
* @category Etd
* @package Etd_Models
* @subpackage CrossrefDeposit
*/

require_once("xml-utilities/XmlObject.class.php");
require_once("models/countries.php");
require_once("models/languages.php");
require_once("models/degrees.php");


class CrossrefDeposit extends XmlObject {
  protected $schema = "http://www.crossref.org/schema/4.3.7";

  protected $xmlconfig;

  /* reference to the etd object used to initialize this submission record */
  public $etd;

  private $schema_validation_errors;
  private $dtd_validation_errors;

  public function __construct($dom = null) {
    if (is_null($dom)) {    // by default, initialize from template xml with Emory defaults
      $xml = file_get_contents("crossref_deposit.xml", FILE_USE_INCLUDE_PATH);
      print($xml);
      $this->dom = new DOMDocument();
      $this->dom->loadXML($xml);
    } else {
      $this->dom = $dom;
    }

    $this->configure();
    $config = $this->config($this->xmlconfig);

    parent::__construct($this->dom, $config);

  }

  // define xml mappings
  protected function configure() {

    $this->xmlconfig =  array(
      "dissertation" => array("xpath" => "dissertation", "class_name" => "dissertation")
    );
  }

  public function initializeFromEtd($etd) {
    $this->etd = $etd;        // store reference (will be needed for file export)

    print('!!!!!!!!!!!!!' . $etd->mods->issued . '!!!!!!!!!!!!!!');
    $author = explode(', ', $etd->mods->author);
    $this->dissertation->given = $author[1];
    $this->surname = $author[0];
    $this->title = $etd->mods->title;

    // 2016-08-31
    $date = explode('-', $etd->mods->issued);
    $this->month = $date[1];
    $this->day = $date[2];
    $this->year = $date[0];

    $this->department = $etd->mods->department;
    $this->degree = $etd->mods->degree;
 }


  public function isValid() {
    // suppress errors so they can be handled and displayed in a more controlled manner
    libxml_use_internal_errors(true);

    // validate against PQ DTD
    $dtd_valid = $this->dom->validate();
    $this->dtd_validation_errors = libxml_get_errors();
    libxml_clear_errors();

    // also validate against customized & stricter schema, which should
    // be a better indication that this is the data PQ actually wants
    if (isset($this->schema) && $this->schema != '')
     $schema_valid = $this->dom->schemaValidate($this->schema);

    $this->schema_validation_errors = libxml_get_errors();
    // is only valid if both of the validations pass
    return ($dtd_valid && $schema_valid);
  }

  public function dtdValidationErrors() {
    return $this->dtd_validation_errors;
  }
  public function schemaValidationErrors() {
    return $this->schema_validation_errors;
  }
}

class dissertation extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
      "given_name" => array("xpath" => "person_name/given_name"),
      "surname" => array("xpath" => "person_name/surname"),
      "title" => array("xpath" => "titles/title"),
      "month" => array("xpath" => "approval_date/month"),
      "day" => array("xpath" => "approval_date/day"),
      "year" => array("xpath" => "approval_date/year"),
      "department" => array("xpath" => "institution/institution_department"),
      "degree" => array("xpath" => "degree")
    ));
    parent::__construct($xml, $config, $xpath);
  }

  // public function set(mods $value) {
  //   $this->given_name = $this->etd->mods->given);
  //   $this->surname.set($this->etd->mods->family);
  //   $this->title.set($this->etd->mods->title);
  //
  //   // 2016-08-31
  //   $date = explode('-', $this->etd->mods->dateIssued);
  //   $this->month.set($date[1]);
  //   $this->day.set($date[2]);
  //   $this->year.set($date[0]);
  //
  //   $this->department.set($this->etd->mods->department);
  //   $this->degree.set($this->etd->mods->degreen);
  // }
}
