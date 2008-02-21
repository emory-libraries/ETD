<?php

require_once("fedora/models/dublin_core.php");

class etd_dc extends dublin_core {
  private $additional_fields;

  protected function configure() {
    parent::configure();

    // adjustments a few stock dc fields
    $this->xmlconfig['identifier']['is_series'] = true;
    $this->xmlconfig['subject']['is_series'] = true;	 // ?
    $this->xmlconfig['format']['is_series'] = true;	 // ?


    // a few special cases 
    $this->xmlconfig["ark"] = array("xpath" => "dc:identifier[contains(., 'ark')]");
    $this->xmlconfig["fedora_pid"] = array("xpath" => "dc:identifier[contains(., 'info:fedora')]");

    // fixme: will these xpaths work?
    $this->xmlconfig["mimetype"] = array("xpath" => "dc:format[contains(., '/')]");	
    $this->xmlconfig["filesize"] = array("xpath" => "dc:format[not(contains(., '/'))]");

    

    $this->additional_fields = array("ark", "mimetype", "filesize");
  }

  // simple check - should only be dc terms
  protected function validDCname($name) {
    // may need to extend...
    return (in_array($name, $this->fields) || in_array($name, $this->additional_fields));
  }

  


}