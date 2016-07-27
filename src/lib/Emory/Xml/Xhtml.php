<?php

require_once("xml-utilities/XmlObject.class.php");

/** 
 * very simple/incomplete xml object for xhtml
 *
 * @todo this seems very HDOT-specific and probably should be in HDOT code and not here
 * 
 * @category EmoryZF
 * @package Emory_Xml
 */
class Emory_Xml_Xhtml extends XmlObject { 
  
  /**
   * array of XmlObjectProperty used to configure XmlObject 
   * @var array
   */
  protected $xmlconfig;

  public function __construct($xml) {
    $this->configure();
    $config = $this->config($this->xmlconfig);
    parent::__construct($xml, $config);

  }

  /**
   * define xml mappings
   * (override this function to customize class mappings)
   */
  protected function configure() {
    $this->xmlconfig = array(
	  "docname" => array("xpath" => "document-name"),
	  "title" => array("xpath" => "title"),
	  "collection" => array("xpath" => "rs[@type='collection']"),
	  );
  }

  /**
   * find a document in eXist by name
   * @param string $name
   */
  public static function findByName($name) {
    $exist = Zend_Registry::get("exist-db");
    $xml = $exist->getDocument($name);
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    return new Xhtml($dom);
  }
  
}
