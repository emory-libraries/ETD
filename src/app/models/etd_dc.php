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
    $this->xmlconfig["pages"] = array("xpath" => "dc:format[contains(., ' p.')]");

    

    $this->additional_fields = array("ark", "mimetype", "filesize", "pages");
  }

  // simple check - should only be dc terms
  protected function validDCname($name) {
    // may need to extend...
    return (in_array($name, $this->fields) || in_array($name, $this->additional_fields));
  }


  public function setArk($ark) {
    if (isset($this->ark)) {
      // is this an error? ark shouldn't change
      $this->ark = $ark;
    } else {
      $this->identifier->append($ark);
      $this->update();	// update so the 'ark' mapping will get picked up
    }
  }

  public function setPages($num) {
    if (isset($this->pages)) {
      $this->pages = "$num p.";
    } else {
      $this->format->append("$num p.");
      $this->update();
    }
  }


  public function __get($name) {
    $value = parent::__get($name);
    switch ($name) {
    case "pages":
      $value = str_replace(" p.", "", $value);	// return just the number
      break;
    }
    return $value;
  }


}