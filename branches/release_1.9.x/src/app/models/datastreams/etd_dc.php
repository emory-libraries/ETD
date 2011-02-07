<?php

require_once("fedora/models/dublin_core.php");

/**
 * EtdFile customization of Dublin Core XmlObject / Fedora Foxml datastream
 * @category EtdFile
 * @package Etd_Models
 * @subpackage Etd
 *
 */
class etd_dc extends dublin_core {

  // In order for the ETD to not be versioned, and workaround the fedora bug:
  // https://jira.duraspace.org/browse/FCREPO-849
  // the control_group MUST be set to Inline/XML_DATASTREAM for the DC datastream, 
  // when versionable is set to false.
  public $versionable = false;
  
  protected function configure() {
    parent::configure();

    $this->xmlconfig['identifier']['is_series'] = true;
    $this->xmlconfig['subject']['is_series'] = true;   
  }  
  
}
