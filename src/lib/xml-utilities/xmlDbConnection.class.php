<?php 

include "taminoConnection.class.php";
include "existConnection.class.php";

/**
 * XML database related functions, such as xquery & XSLT
 * 
 * This class handles xml database related functions, including running
 * xqueries & running xslt translations.  It uses either a tamino or an
 * eXist class, depending on the mode specified when it is instantiated,
 * to process xqueries.
 * 
 * @author Rebecca Sutton Koeser rebecca.s.koeser@emory.edu
 */

class xmlDbConnection {

  // connection parameters
  
  /** @var string database hostname */
  private $host;
  /** @var string database server port (exist) */
  private $port;
  /** @var string database name */
  private $db;
  /** @var string database collection name (tamino) */
  private $coll;
  /** @var string database type: tamino or exist */
  private $dbtype; 	// tamino,exist
  // whether or not to display debugging information
  /** @var boolean display/hide debugging output */
  private $debug;
  
  // these variables used internally
  /** @var object $xmldb tamino or exist object (internal) */
  private $xmldb;	// tamino or exist class object
  /** @var object $xsl_result XML DOM object generated by xsl transformation */
  private $xsl_result;

  // xml/xpath variables - references to equivalent variables in tamino or exist class
  /** @var object $xml XML DOM generated from database response*/
  private $xml;
  /** @var object $xpath XML DOM xpath object */
  private $xpath;

  /** @var array xslt paramters to be used in XSL transform */
  private $xslt;

  // variables for return codes/messages?

  // variables for highlighting search terms
  /** @var array string for highlighting search terms (e.g., <span class="term1">) */
  private $begin_hi;
  /** @var string end of search term highlighting (</span>) */
  private $end_hi;

  /* @var int count of xml debugging output sections (used for javascript hide/display */
  private $debug_count;
  
  /**
   * Sanitizes user input data for use in an XQuery.
   * 
   * After being sanitized, it has been properly espaced to prevent injection attacks.
   *
   * @param string $input The user-given input to sanitize.
   */
  public static function sanitize($input) {
    $s = $input;
    
    # Replace " with "";
    $s = str_replace('"', '""', $s);
    
    return $s;
  }


  /**
   * Initialize xmlDbConnection object according to user specified values
   * 
   * Options for the class are passed in via an associative array.  
   * For those settings that have defaults, the default option is marked with +.
   * 
   * @param array $argArray options: host, db, coll, debug (true/false+), dbtype (tamino+/exist), any tamino/exist params
   */
  public function xmlDbConnection($argArray) {
    $this->host = $argArray['host'];
    $this->db = $argArray['db'];
    if (isset($argArray['coll'])) {
      $this->coll = $argArray['coll'];
    }
    $this->debug = $argArray['debug'];

    $this->dbtype = $argArray['dbtype'];

    if (!($this->host || $this->db || $this->dbtype)) {
      print "xmlDbConnection initialize error: required parameters are missing!<br>
		Hostname, database, and dbtype (exist or tamino) are all required.<br>";
    }
    
    if ($this->dbtype == "exist") {
      // create an exist object, pass on parameters
      $this->xmldb = new existConnection($argArray);
    } else {	// for backwards compatibility, make tamino default
      // create a tamino object, pass on parameters
     $this->xmldb = new taminoConnection($argArray);
    }

    // xmlDb count is the same as tamino or exist count 
    $this->count =& $this->xmldb->count;
    $this->quantity =& $this->xmldb->quantity;	// # returned in current set
    $this->position =& $this->xmldb->position;  // beginning of current set
    // xpath just points to tamino xpath object
    $this->xml =& $this->xmldb->xml;
    $this->xpath =& $this->xmldb->xpath;


    // array of xsl & parameters to use in xsl transform
    // structure: xslt[]["xsl"] = xslt filename
    //		  xslt[]["param"] = array of parameters
    $this->xslt = array();

    // variables for highlighting search terms
    // begin highlighting variables are now defined when needed, according to number of terms
    $this->end_hi = "</span>";

    $this->debug_count = 0;

    /** @name DATABASE_XML xml returned from the database */
    if(!defined("DATABASE_XML")) {
      define("DATABASE_XML", 0);
    }
    /** @name TRANSFORMED_XML xml resulting from last XSL transform */
    if(!defined("TRANSFORMED_XML")) {
      define("TRANSFORMED_XML", 1);
    }
  }

  /**
   * Send an xquery and get an XML result
   *
   * @param string $query xquery
   * @param int $position (optional) starting point of results to return, if cursoring is used
   * @param int $maxdisplay (optional) size of result set to return, if cursoring is used
   */
  public function xquery ($query, $position = NULL, $maxdisplay = NULL) {
    // pass along xquery & parameters to specified xml db
    $this->xmldb->xquery($this->encode_xquery($query), $position, $maxdisplay);
    if ($this->debug) {
      // exist does not return query in result, so print out query in debug mode
      if ($this->dbtype == "exist")
	print "xquery is:<pre>" . htmlentities($query) . "</pre>";
      $this->displayXML();
    }
  }

 
  /**
   * Send an x-query (xql) and get an XML result (should only be used with tamino) 
   *
   * @param string $query xquery
   * @param int $position (optional) 
   * @param int $maxdisplay (optional)
   */
  public function xql ($query, $position = NULL, $maxdisplay = NULL) {
    // pass along xql & parameters to specified xml db
    $this->xmldb->xql($this->encode_xquery($query), $position, $maxdisplay);
    if ($this->debug) {
      $this->displayXML();
    }
  }

  /**
   * retrieve cursor & get total count for result set (should only be used after xquery)
   */
  public function getCursor () {
    $this->xmldb->getCursor();
  }

  /**
   * Get xquery-style cursor (only relevant for tamino). Deprecated.
   */
  public function getXqueryCursor () {
    $this->xmldb->getCursor();
  }

  /**
   * Get x-query (xql) style cursor (only relevant for tamino). Deprecated.
   */
  public function getXqlCursor () {
    $this->xmldb->getXqlCursor();
  }

/**
 * Bind an xslt to parameters; use to run multiple transforms in succession with transform()
 *
 * @param string $file xslt filename
 * @param array $params (optional) associative array of xslt parameters
 */
  public function xslBind ($file, $params = NULL) {
    $i = count($this->xslt);
    $this->xslt[$i]["xsl"] = $file;
    $this->xslt[$i]["param"] = $params;
  }

  /**
   * Successively transform all bound xslts, in the order that they were bound
   */
  public function transform() {
    $input = DATABASE_XML;	// default initial input
    for ($i = 0; $i < count($this->xslt); $i++) {
      $this->xslTransform($this->xslt[$i]["xsl"], $this->xslt[$i]["param"], $input);
      $input = TRANSFORMED_XML;	// use result of last xsl transform for all subsequent inputs
      // if debugging is turned on, display transform information & output result
      // (particularly important for debugging more than one transform in a row)
      if ($this->debug) {
	print "XSLT transform with stylesheet " . $this->xslt[$i]["xsl"];
	if (count($this->xslt[$i]["param"])) {
	  print "<br>\nParameters: \n";
	  foreach ($this->xslt[$i]["param"] as $key => $val) print "$key => $val \n";
	}
	print "<br>\n";
	print "Result of transformation:<br>\n";
	print $this->displayXML(TRANSFORMED_XML);
      }
    }
    // unbind used xslts
    unset($this->xslt);
    $this->xslt = array();
  }
  
  /**
   * Transform XML with a specified XSLT stylesheet
   *
   * @param string $xsl_file XSLT filename
   * @param array $xsl_params associative array of parameters for XSLT
   * @param int $input xml to use for input : DATABASE_XML (default) or TRANSFORMED_XML
   */
   // transform the database returned xml with a specified stylesheet
  public function xslTransform ($xsl_file, $xsl_params = NULL, $input = DATABASE_XML) {
     /* load xsl & xml as DOM documents */
     $xsl = new DomDocument();
     $rval = $xsl->load($xsl_file);
     if (!($rval)) {
       print "Error! unable to load xsl file $xsl_file.<br>";
     } 

     /* create processor & import stylesheet */
     $proc = new XsltProcessor();
     $xsl = $proc->importStylesheet($xsl);
     if ($xsl_params) {
       foreach ($xsl_params as $name => $val) {
         $proc->setParameter(null, $name, $val);
       }
     }
     /* transform the xml document and store the result */

     // transform xml retrieved from the database (default)
     if ($input == DATABASE_XML && $this->xmldb->xml) 	 // make sure data exists
       $this->xsl_result = $proc->transformToDoc($this->xmldb->xml);
     // transform xml resulting from prior xsl transform (make sure input data exists)
     else if ($input == TRANSFORMED_XML && $this->xsl_result)
       $this->xsl_result = $proc->transformToDoc($this->xsl_result);
   }

   /**
    * Transform the xml created by the previous xsl transformation.  Deprecated.
    *
    * @param string $xsl_file XSLT filename
    * @param array $xsl_params associative array of XSL parameters
    */
   public function xslTransformResult ($xsl_file, $xsl_params = NULL) {
     // call default xslTransform with option to specify xml input
     $this->xslTransform($xsl_file, $xsl_params, TRANSFORMED_XML);
   }

   /**
    * Print the result xml from the last xslt transformation; highlight search terms if specified.
    *
    * @param array $term (optional) array of search terms to highlight
    */
   public function printResult ($term = NULL) {
     print $this->saveResult($term);

   }

   /**
    * Return the result xml from the last xslt transformation; highlight search terms if specified.
    *
    * @param array $term (optional) array of search terms to highlight
    * @return string transformed xml in string format
    */
   public function saveResult ($term = NULL) {
     if ($this->xsl_result) {
       if ($term[0] != NULL) {
         $this->highlightXML($term);
	 return $this->xsl_result->saveXML();
       } else {
         return $this->xsl_result->saveXML();
       }
     }

   }

   public function resultXML () {
     if ($this->xsl_result) {
       return $this->xsl_result;
     }

   }

   /**
    * Save xml to a file.
    *
    * @param string $filename filename for xml file output
    * @param int $output xml to use for output : TRANSFORMED_XML (default) or DATABASE_XML 
    */
   public function save ($filename, $output = TRANSFORMED_XML) {
     if ($output == TRANSFORMED_XML) {
       if ($this->xsl_result) {
	 $this->xsl_result->save($filename);
       }
     } else {
       if ($this->xmldb->xml) {
	 $this->xmldb->xml->save($filename);
       }
     }
   }


   /**
    * Return the database xml
    *
    * @return string xml in string format
    */
   public function getXML () {
     if ($this->xmldb->xml) {
       return $this->xmldb->xml->saveXML();
     }
   }
   
   /**
    * Return the results of a query as an array.
    * 
    * This function assumes that the query will result
    * in an array-like xml structure with a root element and a series of
    * zero or more sub-elements that each contain only a single child
    * text node.
    * 
    * @param string $element The sequence elements to look for.  These should be the
    * only children of the root node.
    * @return array An array of strings of the values from the query result.
    *
    */
   public function getAsArray() {
     if($this->xmldb->xml) {
       $vals = array();
       $nodeList = $this->xmldb->xml->documentElement->childNodes;
       foreach ($nodeList as $node) {
         if (is_a($node, "DOMElement")) {
           #extract the child text value from each node
           $vals[] = $node->firstChild->nodeValue;
         }
       }
       
       return $vals;
     }
   }

   
   /**
    * Get the content of an xml node by name when the path is unknown
    *
    * Finds the first instance of the named node within the XML tree. 
    * 
    * @param string $name xml node name to search for
    * @param object $node (optional) starting point in the xml DOM tree
    * @return string content of node found
    */
   public function findNode ($name, $node = NULL) {
     $rval = 0;
     // this function is for backwards compatibility... 
     if (isset($this->xpath)) {     // only use the xpath object if it has been defined
       //for ILN; if we need namespace beyond TEI, will need to rewrite this. AH 02-01-2011
       $n = $this->xpath->registerNamespace('tei','http://www.tei-c.org/ns/1.0'); 
       $n = $this->xpath->query("//$name");
       // return only the value of the first one
       if ($n->length > 0) {		// returns DOM NodeList; check nodelist is not empty
	 $rval = $n->item(0)->textContent;
       }
     }
     return $rval;
   }


   /**
    * Highlight search terms in text within the XML object
    *
    * @param array $term search terms to highlight
    */
   private function highlightXML ($term) {
     // if span terms are not defined, define them now
     if (!(isset($this->begin_hi))) { $this->defineHighlight(count($term)); }
     $this->highlight_node($this->xsl_result, $term);
   }

   /**
    * Recursive function to highlight search terms in xml nodes (called by highlightXML)
    *
    * @param object $n xml node to highlight
    * @param array $term search terms 
    */
   private function highlight_node ($n, $term) {
     // build a regular expression of the form /(term1)|(term2)/i 
     $regexp = "/"; 
     for ($i = 0; $i < count($term); $i++) {
       if ($i != 0) { $regexp .= "|"; }
         $regterm[$i] = str_replace("*", "\w*", $term[$i]); 
         $regexp .= "($regterm[$i])";
       }
     $regexp .= "/i";	// end of regular expression

     $children = $n->childNodes;
     foreach ($children as $i => $c) {
       if ($c instanceof domElement) {		
	 $this->highlight_node($c, $term);	// if a generic domElement, recurse 
       } else if ($c instanceof domText) {	// this is a text node; now separate out search terms

         if (preg_match($regexp, $c->nodeValue)) {
           // if the text node matches the search term(s), split it on the search term(s) and return search term(s) also
           $split = preg_split($regexp, $c->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

           // loop through the array of split text and create text nodes or span elements, as appropriate
           foreach ($split as $s) {
	     if (preg_match($regexp, $s)) {	// if it matches, this is one of the terms to highlight
	       for ($i = 0; $regterm[$i] != ''; $i++) {
	         if (preg_match("/$regterm[$i]/i", $s)) { 	// find which term it matches
                   $newnode = $this->xsl_result->createElement("span", htmlentities($s));
	           $newnode->setAttribute("class", "term" . ($i+1));	// use term index for span class (begins at 1 instead of 0)
	         }
	       }
             } else {	// text between search terms - regular text node
	       $newnode = $this->xsl_result->createTextNode($s);
	     }
	    // add newly created element (text or span) to parent node, using old text node as reference point
	    $n->insertBefore($newnode, $c);
           }
           // remove the old text node now that we have added all the new pieces
           $n->removeChild($c);
	 }
       }   // end of processing domText element
     }	
   }

   /**
    * Display search terms that are highlighted in the text.
    * 
    * Print out specified terms with the same highlighting used within the xml.
    *
    * @param array $term highlighted search terms
    */
   public function highlightInfo ($term) {
     if (!(isset($this->begin_hi))) { $this->defineHighlight(count($term)); }
     if (isset($term[0])) {
       print "<p align='center'>The following search terms have been highlighted: ";
       for ($i = 0; isset($term[$i]); $i++) {
	 print "&nbsp; " . $this->begin_hi[$i] . htmlentities($term[$i]) . "$this->end_hi &nbsp;";
       }
       print "</p>";
     }
   }

   /**
    * Create span tags for highlighting based on the number of terms (called by highlightXML)
    *
    * @param int $num number of search terms
    */
   private function defineHighlight ($num) {
     $this->begin_hi = array();
    // strings for highlighting search terms 
    for ($i = 0; $i < $num; $i++) {
      $this->begin_hi[$i]  = "<span class='term" . ($i + 1) . "'>";
    }
   }

   /**
    * Generate links to different result sets, based on cursor
    * 
    * Based on the current url & position within the result set (which
    * is determined according to cursor information), return an HTML
    * string with links to navigate within the results.  Link are
    * returned in a div with class="resultlink", and each link is a
    * list entry (<li>) with class="resultlink"; any styling (e.g.,
    * horizontal lists) should be handled with css.
    *
    * @param string $url base url to add position & max parameters to
    * @param int $position current starting position within results
    * @param string $max current maximum # of results to display per page
    * @return string html formatted string with links to other result sets
    */
   public function resultLinks ($url, $position, $max) {
     //FIXME: at least in exist, we can get a default maximum from result set itself...
     $result = "<div class='resultlink'>";
	if ($this->count > $max) {
	  $result .= "<li class='first resultlink'>More results:</li>";
	  for ($i = 1; $i <= $this->count; $i += $max) {
	    if ($i == 1) {
	      $result .= '<li class="first resultlink">';
	    } else { 
	      $result .= '<li class="resultlink">';
	    }
            // url should be based on current search url, with new position defined
	    $myurl = $url .  "&pos=$i&max=$max";
            if ($i != $position) {
	      $result .= "<a href='$myurl'>";
	    }
    	    $j = min($this->count, ($i + $max - 1));
    	    // special case-- last set only has one result
    	    if ($i == $j) {
      	      $result .= "$i";
    	    } else {
      	      $result .= "$i - $j";
    	    }
	    if ($i != $position) {
      	      $result .= "</a>";
    	    }
    	    $result .= "</li>";
	  }
	}
	$result .= "</div>"; 
	return $result;
   }

  /**
   * Convert a readable xquery into a clean url to submit over http to tamino or exist
   *
   * @param string $string xquery 
   * @return string encoded xquery
   */
  private function encode_xquery ($string) {
    // convert multiple consecutive white spaces (of any kind) to a single space
    // convert escaped quotes & apostrophes into their tamino entities
    $pattern = Array("/\s+/", "/\\\'/", '/\\\"/');
    $replace = Array(" ",     "&apos;", '&quot;');
    $string = preg_replace($pattern, $replace, $string);
    // convert all characters into url %## equivalent
    return urlencode($string);
  }

  /**
   * Print out xml for debugging purposes
   * 
   * Print the xml inside &lt;pre&gt; tags so it can be viewed on a web page.  
   * Adds a javascript toggle to hide/display xml output, to simplify debugging & viewing results.
   *
   * @param int $input (optional) xml to display: DATABASE_XML (default) or TRANSFORMED_XML
   */
  public function displayXML ($input = DATABASE_XML) {	// by default, display xml returned from db query

    if ($this->debug_count == 0) {	// first time outputting xml - also output javascript for show/hide functionality
      print "<script language='Javascript' type='text/javascript'>
function toggle (id) {
  var a = document.getElementById(id); 
  if (a.style.display == '') a.style.display = 'block';	//default 
  a.style.display = (a.style.display != 'none') ? 'none':'block';
}
</script>\n";
    }

    print "<a href='javascript:toggle(\"debugxml$this->debug_count\")'>+ show/hide xml</a>\n";
    print "<div id='debugxml$this->debug_count'>\n";
    print "<pre>";
    
    switch ($input) {
    case DATABASE_XML:
      if ($this->xml) {
      	$this->xml->formatOutput = true;
	print htmlentities($this->xml->saveXML());
      }
      break;
    case TRANSFORMED_XML:
      if ($this->xsl_result) {
	$this->xsl_result->formatOutput = true;
	print htmlentities($this->xsl_result->saveXML());
      }
      break;
    }
    print "</pre>";
    print "</div>";

    $this->debug_count++;
   }


   /**
    * Load xml from a file (instead of getting xml from a query)
    *
    * @param sring $file filename of xml to load
    */
  public function loadxml ($file) {
    $this->xml = new domDocument();
    $rval = $this->xml->load($file);
    if (!($rval)) {
      print "Error! could not load XML file $file.<br>";
    }
  }


}
