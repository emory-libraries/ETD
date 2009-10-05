<?php

require_once("xml-utilities/XmlObject.class.php");

class unAPIresponse extends XmlObject {
  
  public function __construct() {
    $config = $this->config(array(
     "id" => array("xpath" => "@id"),
     "format" => array("xpath" => "format", "class_name" => "unAPIformat",
		       "is_series" => true)
     ));
    $dom = new DOMDocument();
    $dom->loadXML($this->getTemplate());
    parent::__construct($dom, $config);
  }

  public function setId($id) {
    $this->domnode->setAttribute("id", $id);
    $this->update();      
  }
  public function addFormat($name, $type, $docs = null) {
    $newformat = $this->dom->createElement("format");
    $newformat->setAttribute("name", $name);
    $newformat->setAttribute("type", $type);
    if ($docs) $newformat->setAttribute("docs", $docs);
    $this->domnode->appendChild($newformat);
    $this->update();
  }


  public function getTemplate() {
    return '<formats/>';
  }
}

class unAPIformat extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
     "name" => array("xpath" => "@name"),
     "docs" => array("xpath" => "@docs"),
     "type" => array("xpath" => "@type"),
     ));
    parent::__construct($xml, $config, $xpath);
  }
}

?>