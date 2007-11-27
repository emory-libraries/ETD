<?php

//require_once("foxmlds.php");

class etd_html extends XmlObject {
  const id = "XHTML";
  const label = "Formatted ETD fields";


  
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
    return foxml::xmlDatastreamTemplate("XHTML", "Formatted ETD Fields", self::getTemplate());
  }




  public function __set($name, $value) {
    switch ($name) {
    case "title":
    case "abstract":
    case "contents":
      parent::__set($name, $value);
      return $this->map{$name} = etd_html::tags_to_nodes($this->map{$name});
    default:
      return parent::__set($name, $value);
    }
  }


  // return these fields *with* formatting, not just as plain text
  public function __get($name) {
    switch ($name) {
    case "title":
    case "abstract":
    case "contents":
      $div = $this->map{$name}->ownerDocument->saveXML($this->map{$name});
      $div = preg_replace("|<div[^>]+>(.*)</div>$|", "$1", $div);
      return $div;
    default:
      return parent::__get($name);
    }
  }


  // utility functions for handling html-related of tasks
  public static function cleanTags($string, $keep_breaks = false) {
    // convert tags to a more easily matchable form, remove unneeded formatting
    $search = array("&lt;", "&gt;", "&rsquo;", "&ldquo;", "&rdquo;", "&ndash;", "&nbsp;",);
    $replace = array("<", ">", "'", '"', '"', '-', ' ');
    $string = str_replace($search, $replace, $string);

    // replace unicode characters 
    $search = array("&beta;");
    $replace = array("&#x03b2;");
    $string = str_replace($search, $replace, $string);

    // remove classnames (MSONORMAL, etc.) or any other formatting inside of tags
    $string = preg_replace("|<([a-z]+)\s+[^>/]+>|", "<$1>", $string);	// (don't mess up empty tags)
  
    // remove extra spaces, unused tags, clean up break tags
    $search = array("|\s+|", "|<br\s+/?>|", "|</?i>|", "|</?b>|", "|</?em>|", "|</?strong>|",
		    "|</?sup>|", "|</?sub>|", "|</?span>|", "|</?st1:[^>]+>|", "|</?o:p>|", "|</?p>|");
    $replace = array(" ", "<br/>");  // standardize break tags to simplify split pattern later
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
    return preg_replace("|</?[a-z]>|", "", $string);
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

    //* minimal clean-up to remove formatting that shouldn't be even in the HTML

      
    // remove classnames (MSONORMAL, etc.) or any other formatting inside of tags
    $nodetext = preg_replace("|<([a-z]+)\s+[^>/]+>|", "<$1>", $nodetext);
    	// (don't mess up empty tags)
  
    // remove unwanted tags
    $search = array("|</?span>|", "|</?st1:[^>]+>|", "|</?o:p>|");
    $replace = array();  // standardize break tags to simplify split pattern later  
    $nodetext = preg_replace($search, $replace, $nodetext);

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
	$text = $chunks[++$j];
	$newnode = $node->ownerDocument->createElement($tagname, $text);
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


