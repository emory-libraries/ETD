<?php

require_once("fedora/models/dublin_core.php");

class etd_dc extends dublin_core {
  protected $additional_fields;

  protected function configure() {
    parent::configure();

    // adjustments a few stock dc fields
    $this->xmlconfig['identifier']['is_series'] = true;
    $this->xmlconfig['subject']['is_series'] = true;	 
    $this->xmlconfig['format']['is_series'] = true;	 // ?

    // a few special cases 
    $this->xmlconfig["ark"] = array("xpath" => "dc:identifier[contains(., 'ark')]");
    $this->xmlconfig["fedora_pid"] = array("xpath" => "dc:identifier[contains(., 'info:fedora')]");

    // FIXME: will these xpaths work?
    $this->xmlconfig["mimetype"] = array("xpath" => "dc:format[contains(., '/')]");
    // FIXME: better xpath for filesize?
    $this->xmlconfig["filesize"] = array("xpath" => "dc:format[not(contains(., '/')) and not(contains(., 'p.'))]");
    $this->xmlconfig["pages"] = array("xpath" => "dc:format[contains(., ' p.')]");
    $this->xmlconfig["filename"] = array("xpath" => "dc:source[contains(., 'filename:')]");

    $this->additional_fields = array_merge($this->additional_fields,
					   array("ark", "mimetype", "filesize", "pages", "filename"));
  }

  public function setMimetype($type) {
    $this->update();
    if (isset($this->mimetype)) {
      $this->mimetype = $type;
    } else {
      $this->format->append($type);
      $this->update();
    }
  }

  public function setPages($num) {
    $this->update();
    if (isset($this->pages)) {
      $this->pages = "$num p.";
    } else {
      $this->format->append("$num p.");
      $this->update();
    }
  }

  public function setFilesize($num) {
    $this->update();
    if (isset($this->filesize)) {
      $this->filesize = $num;
    } else {
      $this->format->append($num);
      $this->update();
    }
  }

  public function setFilename($name) {
    $this->update();
    if (isset($this->filename)) {
      $this->filename = $name;
    } else {
      $this->source = "filename:$name";		// not currently using source for anything else 
      $this->update();
    }
  }

  public function __get($name) {
    $value = parent::__get($name);
    switch ($name) {
    case "pages":
      $value = str_replace(" p.", "", $value);	// return just the number
      break;
    case "filename":
      $value = str_replace("filename:", "", $value);	// return just the name
      break;
    }
    return $value;
  }


}