<?php

require_once("xml-utilities/XmlObject.class.php");
require_once("TeiCollection.php");

/**
 * xml object for interacting with TEI xml
 * 
 * @category EmoryZF
 * @package Emory_Xml
 */
class Emory_Xml_Tei extends XmlObject { 

  /**
   * class name for initializing Tei Text XmlObject
   * @var string
   */
  protected $teiText_class = "Emory_Xml_TeiText";


  /**
   * class name for initializing a collection of Tei XmlObjects
   * @var string
   */
  protected $teiCollection_class = "Emory_Xml_TeiCollection";

  /**
   * class to use when initializing Tei navigation XmlObjects
   * @var string
   */
  protected $teiNav_class = "Emory_Xml_TeiNav";

  
  public function __construct($dom = null, DOMXpath $xpath = null,
			      XmlObjectPropertyCollection $subconfig = null) {

    // make it possible to initialize an empty Tei object
    if ($dom == null) {
      $dom = new DOMDocument();
      $dom->loadXML("<TEI.2/>");
    }
    
    // define xml mappings
    $config = $this->config(array(
	  "id" => array("xpath" => "@id"),		       //  in use?
	  "docname" => array("xpath" => "document-name"),
	  "title" => array("xpath" => "teiHeader/fileDesc/titleStmt/title"),
	  "short_title" => array("xpath" => "teiHeader/fileDesc/titleStmt/title/seg[@type='short-title']"),
	  "author" => array("xpath" => "teiHeader/fileDesc/titleStmt/author"),
	  "summary" => array("xpath" => "teiHeader//note[@type='summary']"),
	  
	  // profile description
	  "collection" => array("xpath" => "teiHeader/profileDesc/creation/rs[@type='collection']"),
	  "subset" => array("xpath" => "teiHeader/profileDesc/creation/rs[@type='subset']"),
	  "object" => array("xpath" => "teiHeader/profileDesc/creation/rs[@type='object']"),
				  // will need more mappings...
				  
	  "text" => array("xpath" => "text", "class_name" => $this->teiText_class),

	  // nodes for next/prev navigation generated & returned by xquery
	  "nav" => array("xpath" => "//nav", "class_name" => $this->teiNav_class),

	  ));

    if ($subconfig != null) $config->mergeProperties($subconfig);
    parent::__construct($dom, $config, $xpath);
  }
	
  /**
   * Check if is document complicated enough that a ToC should be displayed.
   * Checks for more than one div directly under body, or more than
   * one div directly under first div under body.
   *
   * @return boolean
   */
  public function showTableOfContents() {
    return (isset($this->text->div) && (count($this->text->div) > 1 ||
	     (isset($this->text->div[0]->div) && count($this->text->div[0]->div) > 1)));
  }

  public function &__get($name) {
    $value = parent::__get($name);
    switch ($name) {
    case "docname":
      $value = str_replace(".xml", "", $value);	// return document name without .xml extension
      break;
    }
    return $value;
  }


  public function findByName($name) {
    $exist = Zend_Registry::get("exist-db");
    $xml = $exist->getDocument($name);
    $this->updateXML($xml);
    return $this;
  }


  /**
   * Return a section of a TEI document by id, with relative table of
   * contents and navigation.
   * NOTE: depends on TEI xquery-module being loaded to eXist
   * 2nd NOTE: if xpath with user-specified filter does not match anything,
   * will fall back to xpath without the filter (e.g., highlighting specified for a section without search terms)
   *
   * @param string $document xml document name in exist (with path relative to configured db)
   * @param string $id node id for content element (currently looks for div, back, and front)
   * @param string $filter optional xpath filter to append to main query object (for highlighting)
   * @return Tei object
   */
  public function getContent($document, $id, $filter = "") {
    $exist = Zend_Registry::get("exist-db");

    // shortcut to include common xqueries for tei, phrase searching, and hdot
    $teixq = 'import module namespace teixq="http://www.library.emory.edu/xquery/teixq" at
	"xmldb:exist:///db/xquery-modules/tei.xqm"; ';
	
  //This line is added to the each xquery to ensure search terms are highlighted and context is displayed
  $existopts = 'declare option exist:serialize "highlight-matches=all";';

    $path = $exist->getDbPath() . "/" . $document;
    $node = "div";	// default for now (TODO: add others later)

    // xpath to the main element for this query
    $element = "document('$path')//(div|back|front)[@id='$id']";
    
    /** Special handling for xpath when a filter is specified.
        Filter will most frequently be used for text highlighting;
        because search terms will not always be present in every section of the document,
	fall back to the xpath without the filter if nothing is found with it.    */
    if ($filter != "") {
      $element = "(if (document('$path')//(div|back|front)[@id='$id']$filter) then
	document('$path')//(div|back|front)[@id='$id']$filter
	else document('$path')//(div|back|front)[@id='$id'])";
    }

    $query = $teixq . $existopts . " let \$a := $element
 	let \$doc := substring-before(util:document-name(\$a), '.xml')
	let \$root := root(\$a)
	let \$contentnode := teixq:contentnode(\$a)
	let \$content :=  <content>";

 // return the immediately preceding pb (for relevant page #s not inside the section)
// don't return preceding pb if main item is itself a pb
// FIXME: this is a workaround for a bug in the sibling axis in eXist; should be fixed in future
//$query .=   ($node != "pb") ? "{\$a/preceding-sibling::*[1][name() = 'pb']}" : "";

    $query .= "  
	  {\$contentnode}  
	  </content>
	return <TEI.2>
	  {\$root/@id}
	  <document-name>{\$doc}</document-name>
	  {\$root//teiHeader}
	  <text>
  	    <body>
	      <relative-toc> {teixq:relative-toc(\$a)} </relative-toc>
      	      <toc type='{\$a/@type}'> {teixq:toc(\$a)}  </toc>
       	      {teixq:relative-nav(\$contentnode)}
              {\$content}
	    </body>
	  </text>
	</TEI.2>
	";

    //print "DEBUG: query = <pre>" . htmlentities($query) . "</pre>";
    
    $xml = $exist->query($query, null, null, array("nowrap" => true));
    $this->updateXML($xml);
    return $this;
  }

}

/** sub-objects used within Emory_Xml_Tei **/


/**
 * mappings for TEI.2/text
 * @package Emory_Xml
 */
class Emory_Xml_TeiText extends XmlObject {
  /**
   * class to use when initializing Tei text/front XmlObjects
   * @var string
   */
  protected $teiFront_class = "Emory_Xml_TeiFront";

  /**
   * class to use when initializing Tei text/back XmlObjects
   * @var string
   */
  protected $teiBack_class = "Emory_Xml_TeiBack";

  /**
   * class to use when initializing Tei div XmlObjects
   * @var string
   */
  protected $teiDiv_class = "Emory_Xml_TeiDiv";

  public function __construct($xml,  DOMXpath $xpath = null,
			      XmlObjectPropertyCollection $subconfig = null) {
    $config = $this->config(array(
	"front" => array("xpath" => "front", "class_name" => $this->teiFront_class),
	"div" => array("xpath" => "body/div",
		       "is_series" => true,
		       "class_name" => $this->teiDiv_class),
	"back" => array("xpath" => "back", "class_name" => $this->teiBack_class),
	));
    if ($subconfig != null) $config->mergeProperties($subconfig);
    parent::__construct($xml, $config, $xpath);
  }
}

/**
 * TEI front matter
 * @package Emory_Xml
 */ 
class Emory_Xml_TeiFront extends XmlObject {
  /**
   * class to use when initializing Tei div XmlObjects
   * @var string
   */
  protected $teiDiv_class = "Emory_Xml_TeiDiv";
  
  public function __construct($xml,  DOMXpath $xpath = null,
			      XmlObjectPropertyCollection $subconfig = null ) {
    $config = $this->config(array(
	"id" => array("xpath" => "@id"),
	"title" => array("xpath" => "titlePage/docTitle/titlePart[@type='main']"),
	"div" => array("xpath" => "div",
		       "is_series" => true,
		       "class_name" => $this->teiDiv_class),

	));
    if ($subconfig != null) $config->mergeProperties($subconfig);
    parent::__construct($xml, $config, $xpath);
  }
}
/**
 * tei generic div
 * @package Emory_Xml
 */ 
class Emory_Xml_TeiDiv extends XmlObject {
  public function __construct($xml,  DOMXpath $xpath = null,
			      XmlObjectPropertyCollection $subconfig = null ) {
    $config = $this->config(array(
	"id" => array("xpath" => "@id"),
        "n" => array("xpath" => "@n"),
        "type" => array("xpath" => "@type"),
	"title" => array("xpath" => "head"),
	
	/* the number of p elements is used to determine whether or not to include bottom page navigation */
	"p" => array("xpath"=> "p", "is_series"=>true),

  	"div" => array("xpath" => "div",
		       "is_series" => true,
		       // initialize any sub divs as instances of the same div class as this one
		       "class_name" => __CLASS__),
	// FIXME: is it okay to map this here?
	"context" => array("xpath" => "context", "class_name" => __CLASS__),
	));
    if ($subconfig != null) $config->mergeProperties($subconfig);
    parent::__construct($xml, $config, $xpath);
  }

 public function pCount() {
   return count($this->p);
 }

}

/**
 * TEI back matter
 * @package Emory_Xml
 */ 
class Emory_Xml_TeiBack extends XmlObject {
  /**
   * class to use when initializing Tei div XmlObjects
   * @var string
   */
  protected $teiDiv_class = "Emory_Xml_TeiDiv";
  
  /**
   * title for all TeiBack elements set to 'back matter'
   * @var string
   */
	protected $title="back matter";
  public function __construct($xml,  DOMXpath $xpath = null,
			      XmlObjectPropertyCollection $subconfig = null) {
    $config = $this->config(array(
				"id" => array("xpath" => "@id"),
				  "div" => array("xpath" => "div",
						 "is_series" => true,
						 "class_name" => $this->teiDiv_class),
				  ));
    if ($subconfig != null) $config->mergeProperties($subconfig);
    parent::__construct($xml, $config, $xpath);
  }
  
}


/**
 * mapping for navigation constructed and returned via xquery, for Document Navigation
 * @package Emory_Xml
 */
class Emory_Xml_TeiNav extends XmlObject {
  /**
   * class to use when initializing Tei div XmlObjects
   * @var string
   */
  protected $navElement_class = "Emory_Xml_NavElement";
  
  public function __construct($xml,  DOMXpath $xpath = null,
			      XmlObjectPropertyCollection $subconfig = null ) {
    $config = $this->config(array(
				  "first" => array("xpath" => "first",
						   "class_name" => $this->navElement_class),
				  "last" => array("xpath" => "last",
						  "class_name" => $this->navElement_class),
				  "next" => array("xpath" => "next",
						  "class_name" => $this->navElement_class),
				  "prev" => array("xpath" => "prev",
						  "class_name" => $this->navElement_class),
				  ));
    if ($subconfig != null) $config->mergeProperties($subconfig);
    parent::__construct($xml, $config, $xpath);
  }
}

/**
 * generic mapping for a navigation element
 * @package Emory_Xml
 */
class Emory_Xml_NavElement extends XmlObject {
  public function __construct($xml,  DOMXpath $xpath = null,
			      XmlObjectPropertyCollection $subconfig = null ) {
    $config = $this->config(array(
		"name" => array("xpath" => "@n"),
		"id" => array("xpath" => "@id"),
		"type" => array("xpath" => "@type"),
		));
    if ($subconfig != null) $config->mergeProperties($subconfig);
    parent::__construct($xml, $config, $xpath);
  }
}