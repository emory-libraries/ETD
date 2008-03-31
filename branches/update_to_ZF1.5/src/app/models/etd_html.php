<?php

require_once("models/foxmlDatastreamAbstract.php");

class etd_html extends foxmlDatastreamAbstract {
  const id = "XHTML";
  const dslabel = "Formatted ETD Fields";


  
  public function __construct($dom) {
    $config = $this->config(array(
				  "title" => array("xpath" => "div[@id='title']"),
				  "abstract" => array("xpath" => "div[@id='abstract']"),
				  "contents" => array("xpath" => "div[@id='contents']"),
				  ));
    parent::__construct($dom, $config);
  }

  /* FIXME: does this need to be valid html? or can we just use tags
   from the namespace in a different container?
  */
  public static function getTemplate() {
    return '<html>
	    <div id="title">test</div>
	    <div id="abstract"/>
	    <div id="contents"/>
	</html>';
  }

  public static function getFedoraTemplate(){
    return foxml::xmlDatastreamTemplate("XHTML", etd_html::dslabel, self::getTemplate());
  }

  public function datastream_label() {
    return etd_html::dslabel;
  }


  public function __set($name, $value) {
    switch ($name) {
    case "title":	
      /* don't wrap title in a paragraph tag (added by FCKeditor) */
      $value = preg_replace("|^\s*<p>\s*(.*)\s*</p>\s*$|", "$1", $value);
    case "abstract":
    case "contents":
      parent::__set($name, etd_html::cleanEntities($value));
      return $this->map{$name} = etd_html::tags_to_nodes($this->map{$name});
    default:
      return parent::__set($name, $value);
    }
  }


  /**
   * function for conversion from Fez - certain table of contents should have whitespace preserved
   * for formatting reasons
   */
  public function setContentsWithWhitespace($value) {
    // kind of a hack? convert &nbsp to numeric entity, which is not currently being replaced
   $value = str_replace("&amp;", "&", $value);
   $value = str_replace("&nbsp;", "&#xA0;", $value);
   return $this->contents = $value;
  }


  // return these fields *with* formatting, not just as plain text
  public function __get($name) {
    switch ($name) {
    case "title":
    case "abstract":
    case "contents":
      $div = $this->map{$name}->ownerDocument->saveXML($this->map{$name});
      $div = preg_replace("|<div[^>]*>(.*)</div>$|s", "$1", $div);	// treat as a single line
      return $div;
    default:
      return parent::__get($name);
    }
  }

  // utility functions for handling html-related of tasks
  
  public static function cleanEntities($string, $clean_whitespace = true) {
    // convert tags to a more easily matchable form, remove unneeded formatting

    /* FIXME: make better use of htmlentities ?
     * can specify quote_style, character set (UTF-8), double_encode...
     */

    
    // convert any non-entities into entities so they can be cleaned up
    //  $string = htmlentities($string);	 // is this needed?
    
    $string = str_replace("&amp;", "&", $string);
    
    $search = array("&lt;", "&gt;", "&rsquo;", "&ldquo;", "&rdquo;", "&quot;", "&ndash;", "&mdash;", "&shy;");
    $replace = array("<", ">", "'", '"', '"', '"', '-', '--','-',);
    $string = str_replace($search, $replace, $string);

    if ($clean_whitespace) {
      $string = str_replace("&nbsp;", " ", $string);
    }
    

    // replace unicode characters 
    $search = array("&alpha;", "&beta;", "&gamma;", "&Gamma;","&delta;","&Delta;","&zeta;","&eta;",
		    "&iota;","&kappa;", "&lambda;","&Lambda;","&mu;","&nu;","&xi;","&Xi;","&pi;","&Pi;",
		    "&rho;","&sigma;","&Sigma;","&tau;","&Phi;","&chi;","&psi;","&Psi;","&omega;","&Omega;",
		    "&szlig;","&ccedil;",);
    	 
    
    $replace = array("&#x03b1;", "&#x03b2;", "&#x03B3;", "&#x0393;", "&#x03B4;","&#x0394;","&#x03B6;",
		     "&#x03B7;","&#x03B9;","&#x03BA;","&#x03BB;","&#x039B;","&#x03BC;","&#x03BD;","&#x03BE;",
		     "&#x039E;","&#x03C0;","&#x03A0;","&#x03C1;","&#x03C3;","&#x03A3;","&#x03C4;",
		     "&#x03A6;","&#x03C7;","&#x03C8;","&#x03A8;","&#x03C9;","&#x03A9;",
		     "&#x00DF;","&#x00E7;",);
    
    $string = str_replace($search, $replace, $string);

    // remove classnames (MSONORMAL, etc.) or any other formatting inside of tags
    $string = preg_replace("|<([a-z]+)\s+[^>/]+(/?)>|", "<$1$2>", $string);	// (don't mess up empty tags)

    return $string;
  }


  
  // utility functions for handling html-related of tasks
  public static function cleanTags($string, $keep_breaks = false) {
    // convert tags to a more easily matchable form, remove unneeded formatting
    $string = etd_html::cleanEntities($string);
    
    // remove extra spaces, unused tags, clean up break tags; also remove empty tags
    $search = array("|<br\s+/?>|", "|</?i/?>|", "|</?b/?>|", "|</?em/?>|", "|</?strong/?>|",
		    "|</?sup/?>|", "|</?sub/?>|", "|</?span/?>|", "|</?st1:[^>]+/?>|", "|</?o:p/?>|", "|</?p>|");
    $replace = array("<br/>");  // standardize break tags to simplify split pattern later
    $string = preg_replace($search, $replace, $string);

    if ($keep_breaks)
      return $string;
    else
      return str_replace("<br/>", "", $string);
  }

  public static function removeTags($string) {
    $search = array("&lt;", "&gt;");
    $replace = array("<", ">");
    $string = str_replace($search, $replace, $string);
    return preg_replace("|</?[a-z]+/?>|", "", $string);		// also remove empty tags
  }


  // convert formatted table of contents to text
  public static function formattedTOCtoText($string) {
    // remove tags, convert line breaks to --
      $text = etd_html::cleanTags($string, true);

      /* split on:
         - breaks
	 - divs
	 - li tags (<ul> tags in pattern so they will not be in output)
       */
      $unfiltered_toc_lines = preg_split('{(<ul>\s*)?</?(li|div|br/)>(\s*</ul>)?}', $text);
      
      $toc_lines = array();
      foreach ($unfiltered_toc_lines as $line) {
	// filter out blank lines
	if (preg_match("/^\s*$/", $line)) continue;

	// remove unnecessary whitespace at beginning and end of lines
	$line = trim($line);
	
	array_push($toc_lines, $line);
      }

      // reassemble split lines, marking sections with -- 
      $text = implode(" -- ", $toc_lines);
      // in case any tags were missed
      return etd_html::removeTags($text);
  }



  // convert text tags into proper xml nodes (so < & > won't be escaped as characters)
  public static function tags_to_nodes($node) {
    if (! $node instanceof DOMNode)
      print "Error! tags_to_nodes argument should be a DOMNode\n";
    $nodetext = $node->nodeValue;
    // blank out current text so it can be replaced with text & new nodes
    $node->nodeValue = "";	

    //* do some minimal clean-up to remove formatting that shouldn't be even in the HTML

    // remove classnames (MSONORMAL, etc.) or any other formatting inside of tags
    $nodetext = preg_replace("|<([a-z]+)\s+[^>/]+>|", "<$1>", $nodetext);
    	// (don't mess up empty tags)
  
    // remove unwanted tags
    $search = array("|</?span>|", "|</?st1:[^>]+>|", "|</?o:p>|");
    $replace = array();  // standardize break tags to simplify split pattern later
    $nodetext = preg_replace($search, $replace, $nodetext);
   
    // remove unwanted empty tags (sometimes made empty by removing unwanted tags)
    $search = array("|<i/>|", "|<i>\s*</i>|", "|<b/>|", "|<b>\s*</b>|",
		    "|<em/>|", "|<em>\s*</em>|", "|<strong/>|", "|<strong>\s*</strong>|",
		    "|<sup/>|","|<sup>\s*</sup>|", "|<sub/>|", "|<sub>\s*</sub>|",
		    "|<span/>|", "|<span>\s*</span>|", "|<p/>|", "|<p>\s*</p>|",);
    $nodetext = preg_replace($search, array(), $nodetext);


    // stack of nodes so new elements & text nodes can be added at the appropriate level
    $current_node = array();
    array_unshift($current_node, $node);
    
    // split the text on any tags 
    $chunks = preg_split("|(</?[a-z]+\s*/?>)|", $nodetext, null,
			 PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    for ($j = 0; $j < count($chunks); $j++) {
      if (preg_match("|<([a-z]+)>|", $chunks[$j], $matches)) {
	// open tag -- create node with the next section as text content
	$tagname = $matches[1];
	// don't add text to node here-- next chunk could be another tag
	$newnode = $node->ownerDocument->createElement($tagname);
	$current_node[0]->appendChild($newnode);
	// make new node the current one that subsequent elements & text are added to
	array_unshift($current_node, $newnode);
      } elseif (preg_match("|<([a-z]+)\s*/>|", $chunks[$j], $matches)) {
	// empty tag -- add to document with no text content
	$tagname = $matches[1];
	$current_node[0]->appendChild($node->ownerDocument->createElement($tagname));
      } elseif (preg_match("|</([a-z]+)>|", $chunks[$j], $matches)) {
	$tagname = $matches[1];
	// close tag -- remove node from current node stack, but ONLY if tagname matches
	if ($tagname == $current_node[0]->nodeName)
	  array_shift($current_node);
      } else {
	// regular text
	$current_node[0]->appendChild($node->ownerDocument->createTextNode($chunks[$j]));
      }
    }
    return $node;
  }

}


