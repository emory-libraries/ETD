<?php

require_once("datastreams/etd_dc.php");

/**
 * ETD customization of Dublin Core XmlObject / Fedora Foxml datastream
 * @category Etd
 * @package Etd_Models
 * @subpackage Etd
 *
 * @property array $format
 * @property array $identifier 
 * @property string $ark dc:identifier that contains ark
 * @property string $fedora_pid dc:identifier that contains info:fedora
 * @property string $filesize dc:format that does not contain '/' or 'p.'
 * @property string $pages dc:format that contains 'p.'
 */
class etdfile_dc extends etd_dc {
  protected $additional_fields;
  
  // In order for the ETD and AuthorInfo to not be versioned, and workaround the fedora bug:
  // https://jira.duraspace.org/browse/FCREPO-849
  // the control_group MUST be set to Inline/XML_DATASTREAM for the DC datastream, 
  // when versionable is set to false.
  public $versionable = true;

  protected function configure() {
    parent::configure();

    // a few special cases 
    $this->xmlconfig['identifier']['is_series'] = false;  // override etd_dc where is_series is true.  
    $this->xmlconfig["ark"] = array("xpath" => "dc:identifier[contains(., 'ark')]");
    $this->xmlconfig["fedora_pid"] = array("xpath" => "dc:identifier[contains(., 'info:fedora')]");

    // FIXME: better xpath for filesize?
    $this->xmlconfig["filesize"] = array("xpath" => "dc:format[not(contains(., '/')) and not(contains(., 'p.'))]");
    $this->xmlconfig["pages"] = array("xpath" => "dc:format[contains(., ' p.')]");

    $this->additional_fields = array_merge($this->additional_fields,
             array("ark", "filesize", "pages", "filename"));
  }

  /**
   * set number of pages (adds to xml if not already present)
   * @param string $num
   */
  public function setPages($num) {
    $this->update();
    $value = "$num p.";
    if (isset($this->pages)) {
      $this->pages = "$num p.";
    } else if (! count($this->formats)) {
      $this->format = "$num p.";
    } else {
      $this->formats->append("$num p.");
    }
    $this->update();
  }

  /**
   * set filesize (adds to xml if not already present)
   * @param string $num
   */
  public function setFilesize($num) {
    $this->update();
    if (isset($this->filesize)) {
      $this->filesize = $num;
    } else if (! count($this->formats)) {
      $this->format = $num;
    } else {
      $this->formats->append($num);
    }
    $this->update();
  }

  public function __get($name) {
    $value = parent::__get($name);
    switch ($name) {
    case "pages":
      $value = str_replace(" p.", "", $value);  // return just the number
      break;
    }
    return $value;
  }


}
