<?php

/**
 * Very simple xml object for a result returned by eXist.
 * Should be extended and customized to map actual results
 * to meaningful user objects.
 *
 * 
 * @category EmoryZF
 * @package Emory_Xml
 */
class Emory_Xml_eXist extends XmlObject {

  public function __construct($xml, XmlObjectPropertyCollection $subconfig = null,
			      DOMXpath $xpath = null) {
    $this->addNamespace("exist", "http://exist.sourceforge.net/NS/exist");
    $config = $this->config(array(
	"total" => array("xpath" => "@hits"),
	"start" => array("xpath" => "@start"),
	"end" => array("xpath" => "@count")
	));
    
    if ($subconfig != null) $config->mergeProperties($subconfig);
    
    parent::__construct($xml, $config);
  }
}
