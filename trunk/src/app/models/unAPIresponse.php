<?php

require_once("xml-utilities/XmlObject.class.php");

/**
 * XmlObject to generate response for an unAPI query
 * @category Etd
 * @package Etd_Models
 *
 * @property string $id
 * @property array $format array of unAPIformat
 */
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

  /**
   * set response id
   * @param string $id
   */
  public function setId($id) {
    $this->domnode->setAttribute("id", $id);
    $this->update();      
  }
  
  /**
   * add a format option
   * @param string $name
   * @param string $type
   * @param string $docs optional
   */
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

/**
 * XmlObject for format portion of unAPI response
 * @package Etd_Models
 *
 * @property string $name
 * @property string $docs
 * @property string $type
 */
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