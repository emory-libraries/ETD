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

  /* Note: this is not a full, valid html document with head & body;
     simply using html tags for formatting.
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
    case "abstract":
    case "contents":
      $this->map{$name} = etd_html::tags_to_nodes($this->map{$name}, $value);
      $this->update();
      return $this->map{$name};
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
      return etd_html::getContentXML($this->map{$name});
    default:
      return parent::__get($name);
    }
  }

  // return the content of the node *without* the containing node tag 
  public static function getContentXml($node) {
    $content = "";
    if ($node->hasChildNodes()) {
      for ($i = 0; $i < $node->childNodes->length; $i++) {
	$child = $node->childNodes->item($i);
	$content .= $node->ownerDocument->saveXML($child);
      }
    } else {
      $content = $node->nodeValue;
    }
    return $content;
  }

  // utility functions for handling html-related of tasks

  // clean up entities, convert named entities into numeric ones that fedora can handle
  public static function cleanEntities($string, $clean_whitespace = true) {
    // convert tags to a more easily matchable form, remove unneeded formatting
    //    $string = str_replace("&amp;", "&", $string);
    
    //    $search = array("&lt;", "&gt;", "&rsquo;", "&ldquo;", "&rdquo;", "&quot;", "&ndash;", "&mdash;", "&shy;");
    //    $replace = array("<", ">", "'", '"', '"', '"', '-', '--','-',);
    $search = array("&rsquo;", "&ldquo;", "&rdquo;", "&quot;", "&ndash;", "&mdash;", "&shy;");
    $replace = array("'", '"', '"', '"', '-', '--','-',);
    $string = str_replace($search, $replace, $string);

    if ($clean_whitespace) {
      $string = str_replace("&nbsp;", " ", $string);
    }
    
    // use Tidy to clean up the formatting, tags, etc.
    $tidy_opts = array("output-xhtml" => true,
		       "drop-font-tags" => true,
		       "drop-proprietary-attributes" => true,
		       "join-styles" => false,
		       "numeric-entities" => true,
		       "show-body-only" => true,
		       );
    /** NOTE: cannot use  option "clean" because it converts inline styles to
        css styles, which is much harder to manage & display correctly.
     */
    return tidy_repair_string($string, $tidy_opts);
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



  /**
    * convert text tags contained in node or value passed in
    * into proper xml nodes (so < & * > don't get escaped as characters)
    *
    * @param DOMNode $node
    * @param string $value text to be converted (optional, overrides node text)
    * @return converted node
    */
  public static function tags_to_nodes(DOMNode $node, $value = "") {

    if ($value == "") {
      // save the text xml currently in the node (to be converted)
      $value = $node->nodeValue;
    }
    
    // blank out current content so it can be replaced with the new nodes
    $node->nodeValue = "";

    // clean html using tidy -- handles special characters, &, <, etc.
    $value = etd_html::cleanEntities($value);
    
    // load the xml into a temporary DOM
    $tmpdoc = new DOMDocument();
    $loaded = $tmpdoc->loadXML("<doc>" . $value . "</doc>");
    if ($loaded == false) {
      trigger_error("Cannot parse xml", E_USER_ERROR);
      // FIXME: how to set an error message here ?

      // restore initial value so at least nothing is lost
      $node->nodeValue = $value;
      return;
    }

    // append all nodes under top-level doc to the node being converted
    if ($tmpdoc->documentElement->hasChildNodes()) {
      for ($i = 0; $i < $tmpdoc->documentElement->childNodes->length; $i++) {
	$newnode = $node->ownerDocument->importNode($tmpdoc->documentElement->childNodes->item($i), true);
	$node->appendChild($newnode);
      }
    }
    
    return $node;
  }

}


