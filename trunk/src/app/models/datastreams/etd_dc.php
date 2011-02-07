<?php

require_once("fedora/models/dublin_core.php");

/**
 * ETD customization of Dublin Core XmlObject / Fedora Foxml datastream
 * @category Etd
 * @package Etd_Models
 * @subpackage Etd
 *
 * @property array $identifier
 * @property array $subject
 */
class etd_dc extends dublin_core {
  
  // In order for the ETD and AuthorInfo to not be versioned, and workaround the fedora bug:
  // https://jira.duraspace.org/browse/FCREPO-849
  // the control_group MUST be set to Inline/XML_DATASTREAM for the DC datastream, 
  // when versionable is set to false.
  public $versionable = false;

  protected function configure() {
    parent::configure();

    // adjustments a few stock dc fields
    // TODO: update etd code to use base class mappings of identifiers and subjects
    $this->xmlconfig['identifier']['is_series'] = true;
  }

}
