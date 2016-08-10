<?php

require_once("xml-utilities/XmlObject.class.php");
require_once("foxml.php");
require_once("foxmlDatastreamAbstract.php");

/**
 *  magic variables
 *
 * @property string $title
 * @property string $identifier
 * @property string $leader
 * @property marc856Block[] $volume856
 * @property string $datafield008 encoded with Year and  Place of publication
 * @property string $controlfield008 encoded with Year and  Place of publication
 * @property string $altPubYear alternate MARC location of Year of publication
 * @property string $publicDomain Analysis by technical services staff
 */
class marc_xml extends foxmlDatastreamAbstract {
  protected $xmlconfig;

  /**
   * schema location for validating marc xml
   * canonical schema location is here: http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd
   * @var string
   */
  protected $schema = "https://larson.library.emory.edu/schemas/MARC21slim.xsd";
  const namespace = "http://www.loc.gov/MARC21/slim";

  public $dslabel = "MARC21 metadata";
  public $control_group = FedoraConnection::MANAGED_DATASTREAM;
  public $state = FedoraConnection::STATE_ACTIVE;
  public $versionable = true;
  public $mimetype = 'text/xml';
  // format_uri ?

  public $pubPlace;
  public $pubYear;

  public function __construct($xml = null) {
    if (is_null($xml)) {
      $xml = $this->construct_from_template();
    }
    $this->addNamespace("marc", marc_xml::namespace);
    $this->configure();
    $config = $this->config($this->xmlconfig);
    parent::__construct($xml, $config);

    // All aleph records will have this tag: <info source="aleph"/>
    // If this is an ALEPH marc record, then parse identifier from 035 field array.
    if (isset($this->info_source_aleph))	{
      if (isset($this->identifiers) && sizeof($this->identifiers) > 0) {
        foreach($this->identifiers as $id) {
          if (preg_match('/^oc[mn]/', (string)trim($id))) {
            $this->identifier = (string)trim($id);
            break;
          }
        }
      }
    }

    // initial calculation of place & year of publication
    $this->setPubPlaceYear();
  }

    protected function update() {
        parent::update();
        // calculate place & year of publication based on latest contents
        $this->setPubPlaceYear();
    }

    /**
     * find place & year of publication
     * (meant to be called when created OR when updated)
     */
    protected function setPubPlaceYear() {
      // calculate place & year of publication - needed for public domain check
      if(isset($this->datafield008)) {
        // place of publication : 008 character position 15-17
        $this->pubPlace = trim(substr($this->datafield008, 15, 3));
        // year of publication : 008 character position 7-10
        $this->pubYear  = trim(substr($this->datafield008, 7, 4));
      }

      // NOTE: publication year may also be in 260c, but it is NOT reliably
      // machine parsable, as it can be in any number of formats, for
      // example: 1905., 1908-14.
    }

  // define xml mappings
  protected function configure() {
    // NOTE: using // in paths so mappings work for either collection/record/.. or just record/..

    // Identify if this is an ALEPH or SIRSI marc record.
    // All aleph records will have this tag: <info source="aleph"/>
    $this->xmlconfig["info_source_aleph"] = array("xpath" => "marc:record/marc:info[@source='aleph']");

    $this->xmlconfig["volume583"] = array("xpath" => "marc:record/marc:datafield[@tag='583']",
    "is_series" => true, "class_name" => "marc583Block");
    $this->xmlconfig["volume856"] = array("xpath" => "//marc:datafield[@tag='856']",
    "is_series" => true, "class_name" => "marc856Block");
    $this->xmlconfig["title"] = array("xpath" => "//marc:datafield[@tag='245']/marc:subfield[@code='a']");

    // The oclc number is found in the 001 controlfield for SIRSI marc records.
    $this->xmlconfig["identifier"] = array("xpath" => "//marc:controlfield[@tag='001']");
    // The oclc number is found in the 035 datafield series for ALEPH marc records.
    $this->xmlconfig["identifiers"] = array("xpath" => "//marc:datafield[@tag='035']/marc:subfield[@code='a']",
    "is_series" => true);

    $this->xmlconfig["leader"] = array("xpath" => "//marc:leader");
    $this->xmlconfig["volumeNbr"] = array("xpath" => "//marc:datafield[@tag='856' and @ind2='0']/marc:subfield[@code='y']");

    // fields used to check that book is public domain
    $this->xmlconfig["datafield008"] = array("xpath" => "//marc:controlfield[@tag='008']");
    // FIXME: should datafield be mapped to *controlfield* or *datafield* 008?
    $this->xmlconfig["controlfield008"] = array("xpath" => "//marc:controlfield[@tag='008']");
    $this->xmlconfig["altPubYear"] = array("xpath" => "//marc:datafield[@tag='260']/marc:subfield[@code='c']");
    $this->xmlconfig["publicDomain"] = array("xpath" => "//marc:datafield[@tag='583']/marc:subfield[@code='x']");
  }


    /**
     * check if a country code corresponds to a location in the US
     * expects 2 or 3 letter country code, as found in 008
     * @param string $country_code
     * @return bool
     */
    public function inUS($country_code) {
      // pattern for most US codes is abu where ab is (generally) 2-letter state code
      // list of special cases that do not meet this pattern (US islands/territories, etc)
      // note: trimming country code and ignoring whitespace (2-letter code should be left-aligned)
      $us_codes = array("uc", "up", "vi", "pr", "us");
      return preg_match("/^([a-z]{2}u|" . join("|", $us_codes) . ")$/", trim($country_code));

    }

    public function getTypeOfDate() {
      if (isset($this->datafield008))
        return substr($this->datafield008, 6, 1);
      else
        return null;
    }

    public function getVolume856() {
      return isset($this->volume856) ? $this->volume856 : null;
    }

    public function getVolume583() {
      return isset($this->volume583) ? $this->volume583 : null;
    }

    protected function construct_from_template() {
      $dom = new DOMDocument();
      /* empty marc template is used as the file will be updated */
      $dom->loadXML(file_get_contents("marc.xml", FILE_USE_INCLUDE_PATH));
      return $dom;
    }

}//class

/**
 * @property string $action       subfield a - Action
 * @property string $actionDate   subfield c - Time/Date of Action
 * @property string $course       subfield 2 - Source of Term
 * @property string $institution  subfield 5 - Library Identifier
 * @property string $publicDomain subfield x - Nonpublic Note (Public Domain)
 * @property string $material     subfield 3 - Materials Specified (Volume, as noted on spine)
 */
class marc583Block extends XmlObject {
  /**
   * initialises single marc 583 block in a given marc xml
   *
   * @param DOMnode $xml domnode for marc:datafield[@tag='583']
   * @param DOMxpath $xpath shared xpath object (marc namespace already added)
   */
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
      "action" => array("xpath" => "marc:subfield[@code='a']"),
      "actionDate" => array("xpath" => "marc:subfield[@code='c']"),
      "source" => array("xpath" => "marc:subfield[@code = '2']"),
      "institution" => array("xpath" => "marc:subfield[@code = '5']"),
      "publicDomain" => array("xpath" => "marc:subfield[@code = 'x']"),
      "material" => array("xpath" => "marc:subfield[@code = '3']"),
    ));
    parent::__construct($xml, $config, $xpath);
  }
}

/**
 * @property string $volumeNumber   subfield y - volume designation, e.g. V.1
 * @property string $url    subfield u
 */
class marc856Block extends XmlObject {
  /**
   * initialises single marc 856 block in a given marc xml
   *
   * @param DOMnode $xml domnode for marc:datafield[@tag='856']
   * @param DOMxpath $xpath shared xpath object (marc namespace already added)
   */
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
        "volumeNumber" => array("xpath" => "marc:subfield[@code='y']"),
        "url" => array("xpath" => "marc:subfield[@code='u']"),
        "version" => array("xpath" => "marc:subfield[@code = '3']") // pdf or pod version
   ));
   parent::__construct($xml, $config, $xpath);
  }
}
