<?php

require_once("Exist.php");


/**
 * group of TEI documents (e.g., grouped in a result set returned by eXist)
 *
 * @category EmoryZF
 * @package Emory_Xml
 */
class Emory_Xml_TeiCollection extends Emory_Xml_eXist {
  /**
   * eXist db connection object
   * @var Emory_Db_Adapter_Exist
   */
  protected $exist;
  
  /**
   * class to use when initializing Tei XmlObjects
   * @var string
   */
  protected $tei_class = "Emory_Xml_Tei";
  
  public function __construct($dom = null, DOMXpath $xpath = null,
			      XmlObjectPropertyCollection $subconfig = null ) {

    // make it possible to initialize an empty TeiCollection object
    if ($dom == null) {
      $dom = new DOMDocument();
      $dom->loadXML("<exist/>");
    }
    
    $config = $this->config(array(
	"tei" => array("xpath" => "TEI.2",
			"is_series" => true, "class_name" => $this->tei_class),
	));
    if ($subconfig != null) $config->mergeProperties($subconfig);
    parent::__construct($dom, $config, $xpath);

    // store a reference to exist connection object, if available (should be stored in registry)
    if (Zend_Registry::isRegistered("exist-db")) {
      $this->exist = Zend_Registry::get("exist-db");
    } else {
      trigger_error("No registry entry found for 'exist-db'", E_USER_NOTICE);
    }
  }

  
  /*** functions to retrieve TEI documents from eXist ***/
  protected function find_result($teidoc = null){
	$output_tei = '
  	<TEI.2>{' . $teidoc . '/@id}
	  <document-name>{util:document-name(' . $teidoc . ')}</document-name>
        <teiHeader>
 	  <fileDesc>
          {' . $teidoc . '/teiHeader/fileDesc/titleStmt}
	  {' . $teidoc . '/teiHeader/fileDesc/notesStmt}
 	  </fileDesc>
	  {' . $teidoc . '/teiHeader/profileDesc}
        </teiHeader>
        </TEI.2>';
	return $output_tei;
  }
  
  public function find($options = null) {
    $path = $this->exist->getDbPath();	// currently configured base db path
    if (isset($options["section"])) $path .= "/" . $options["section"];

    // FIXME: use TEI configured properties to get xpaths for filters
    
    $filter = "";
    if (isset($options["subset"]))
      //      $filter .= "[.//rs[@type='subset'] = '" . $options["subset"] . "']";
      $filter .= "[contains(.//rs[@type='subset'], '" . $options["subset"] . "')]";
    if (isset($options["object"]))
      $filter .= "[.//rs[@type='object'] = '" . $options["object"] . "']";


    $sort = isset($options["sort"]) ? $options["sort"] : "";

    switch ($sort) {
    case "document_name":
      $order = 'util:document-name($a)'; break;
    case "title":		// setting title as default sort order for now
    default:
      $order = '$a/teiHeader/fileDesc/titleStmt/title';
    }

    $find_query = 'for $a in collection("' . $path . '")/TEI.2' . $filter . '
 	order by ' . $order . '
	return ' . $this->find_result('$a');

    $xml = $this->exist->query($find_query, 50, 1);
    $this->updateXML($xml);
    return $this->tei;
  }

}
