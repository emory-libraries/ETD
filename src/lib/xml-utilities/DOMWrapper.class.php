<?php

require_once('XPathDataSource.class.php');

class DOMWrapper implements XPathDataSource {
  
  /**
   * The DOMDocument object backing this node.
   *
   * @var DOMDocument
   */
  private $dom;
  
  function __construct() {
    $this->dom = new DOMDocument();
  }
  
  function loadXML($xml) {
    $this->dom->loadXML($xml);
  }
  
  function query($xpath) {
    $domxpath = new DOMXPath($this->dom);
    $nodeList = $domxpath->query($xpath);
    
    $xmlStrings = array();
    foreach ($nodeList as $node) {
      $doc = new DOMDocument();
      $newNode = $doc->importNode($node, true);
      $doc->appendChild($newNode);
      $xmlStrings[] = $doc->saveXML();
    }
    
    return $xmlStrings;
  }
}