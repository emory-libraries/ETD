<?php

require_once("datastreams/etd_dc.php");

/**
 * EtdFile customization of Dublin Core XmlObject / Fedora Foxml datastream
 * @category EtdFile
 * @package Etd_Models
 * @subpackage Etd
 *
 */
class etd_dc_versioned extends etd_dc {

  // In order for the ETD to not be versioned, and workaround the fedora bug:
  // https://jira.duraspace.org/browse/FCREPO-849
  // the control_group MUST be set to Inline/XML_DATASTREAM for the DC datastream, 
  // when versionable is set to false.
  public $versionable = true;
  
}
