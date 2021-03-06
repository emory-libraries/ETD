<?php
require_once("models/foxmlDatastreamAbstract.php");

/**
 * ETD xhtml datastream
 * @category Etd
 * @package Etd_Models
 * @subpackage Etd
 *
 * @property string $title formatted title
 * @property string $abstract formatted abstract
 * @property string $contents formatted table of contents
 */
class etd_html extends foxmlDatastreamAbstract {
  
  public $id = "XHTML";
  public $dslabel = "Formatted ETD Fields";
  public $control_group = FedoraConnection::MANAGED_DATASTREAM;
  public $state = FedoraConnection::STATE_ACTIVE;
  public $versionable = true;
  public $mimetype = 'text/xml';  
  
  public function __construct($dom=null) {
    
    if (is_null($dom)) {
      $dom = $this->construct_from_template();
    }    
    
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

  protected function construct_from_template() {
    $dom = new DOMDocument();
    $dom->loadXML(self::getTemplate());
    return $dom;
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

  /**
   * return the content of the node *without* the containing node tag
   * @param DOMNode $node
   * @return string
   */
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

  /**
   * return the text content of the nodes without any nodes (but properly escaped)
   * @param DOMNode $node
   * @return string
   */
  public static function getContentText($node) {
    $content = "";
    if ($node->hasChildNodes()) {
      for ($i = 0; $i < $node->childNodes->length; $i++) {
        $child = $node->childNodes->item($i);
        $content .= etd_html::getContentText($child);
      }
    } elseif ($node instanceof DOMText) {
      $content .= $node->ownerDocument->saveXML($node);
    }
    return $content;
  }


  // utility functions for handling html-related of tasks

  /**
   * clean up entities, convert named entities into numeric ones that fedora can handle
   * @static
   * @param string $string
   * @param bool $clean_whitespace optional, defaults to true
   * @return string
   */ 
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

    // remove Microsoft class names (by themselves or mixed with classes we want to preserve)
    $string = preg_replace(array('/ *class="Mso[a-zA-Z]+"/', '/class="([^"]*)?Mso[a-zA-Z]+([^"]*)?"/'),
         array('', 'class="$1$2"'),  $string);
    // remove style attributes (all FCKeditor formatting should now use css classes)
    $string = preg_replace('/ *style="[^"]+"/', '', $string);
    // remove Microsoft language encoding  (FIXME: any case where we would want to preserve this?)
    $string = preg_replace('/ *(xml:)?lang="[^"]+"/', '', $string);
    
    // use Tidy to clean up the formatting, tags, etc.
    $tidy_opts = array("output-xhtml" => true,
           "drop-font-tags" => true,
           "drop-proprietary-attributes" => true,
           "join-styles" => false,
           "numeric-entities" => true,
           "show-body-only" => true,
           // strip out MS Word junk 
           //"word-2000" => true, -- breaks things
           //"clean" => true, -- generates classes, requires css
           );
    /** NOTE: cannot use  option "clean" because it converts inline styles to
        css styles, which is much harder to manage & display correctly.
     */
    return tidy_repair_string($string, $tidy_opts);
  }


  
  /**
   * clean up html tags, removing unwanted or bad tags
   * @param string $string
   * @param bool $keep_breaks optional, defaults to false
   * @return string
   */ 
  public static function cleanTags($string, $keep_breaks = false) {

    // convert tags to a more easily matchable form, remove unneeded formatting
    $string = etd_html::cleanEntities($string);

    // remove extra spaces, unused tags, clean up break tags; also remove empty tags
    $search = array("|<br\s+/?>|", "|</?i/?>|", "|</?b/?>|", "|</?em/?>|", "|</?strong/?>|",
        "|</?sup/?>|", "|</?sub/?>|", "|</?span/?>|", "|</?st1:[^>]+/?>|", "|</?o:p/?>|");
    $replace = array("<br/>");  // standardize break tags to simplify split pattern later
    $string = preg_replace($search, $replace, $string);

    if ($keep_breaks)
      return $string;
    else
      return str_replace("<br/>", "", $string);
  }

  /**
   * remove _all_ xml tags from a string, returning only the text content
   * @param string $string xml content
   * @return string
   */
  public static function removeTags($string) {
    $dom = new DOMDocument();
    $string = etd_html::cleanEntities($string);
    $dom->loadXML("<div>" . $string . "</div>");
    // return the text content of this node and all its children, without node tags, cleaning up whitespace
    return trim(etd_html::getContentText($dom->documentElement));
  }


  /**
   * convert formatted table of contents to plain text
   * - cleans & removes tags, splits on sections if possible, sections are delimited with ' -- '
   * @param string $string
   * @return string
   * @static
   */
  public static function formattedTOCtoText($string) {
    // remove any tag attributes
    $string = preg_replace("|<(\w+)( [^>/]+)?>|", "<$1>", $string);
    
    
    // remove tags, convert line breaks to --
    $text = etd_html::cleanTags($string, true);

      /* split on:
         - breaks
   - divs
   - li tags (<ul> tags in pattern so they will not be in output)
       */
      $unfiltered_toc_lines = preg_split('{(<ul>\s*)?</?(li|p|div|br/)>(\s*</ul>)?}', $text);
      
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
    * @return DOMNode converted node
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


