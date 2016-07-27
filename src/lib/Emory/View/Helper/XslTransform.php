<?
/**
 * view helper to run an xsl transform and return the result
 *
 * @todo FIXME: how to make xforms namespace configurable?
 * 
 * @category EmoryZF
 * @package Emory_View
 * @subpackage Emory_View_Helpers
 */
class Emory_View_Helper_XslTransform {

  /**
   * @param string $xml
   * @param string $xsl filename to xslt - currently must be located in ../app/views/xslt
   * @param array $params optional parameters to pass to stylesheet
   * @return string result of xsl transform
   *
   * @todo base path should be configurable!
   */
  public function xslTransform($xml, $xsl, $params = array()) {

    /* load xsl & xml as DOM documents */
    $xmldom = new DOMDocument();
    $xmldom->loadXML($xml);	// FIXME: error handling?

    $xsldom = new DOMDocument();
    $rval = $xsldom->load("../app/views/xslt/" . $xsl);	// FIXME: how to specify path for xslt files??
    // NOTE: throwing exception means you don't get to see the xslt errors...
    //    if (!($rval)) throw new Exception("Unable to load xsl file $xsl");
    
    /* create processor & import stylesheet */
    $proc = new XsltProcessor();
    $xsl = $proc->importStylesheet($xsldom);
    if ($params) {
      foreach ($params as $name => $val) {
	$proc->setParameter(null, $name, $val);
      }
    }
    /* transform the xml document  */
    return $proc->transformToXML($xmldom);
    
  } 
  
}

