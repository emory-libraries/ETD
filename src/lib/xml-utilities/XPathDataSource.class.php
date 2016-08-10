<?php

interface XPathDataSource {
  
  /**
   * Loads a string of XML into the datasource.
   *
   * @param string $xml An XML string.
   */
  public function loadXML($xml);
  
  /**
   * Executes an xpath on the datasource and returns 
   * the resulting XML as an array of strings.
   *
   * @param string $xpath An XPath expression to execute on the datasource.
   * @return array An array of XML strings.
   */
  public function query($xpath);
}

?>