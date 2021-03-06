<?php
/**
 * Process a PDF submission and extract data to create a new ETD record.
 * @category Etd
 * @package Etd_Controllers
 * @subpackage Etd_Controller_Helpers
 */

// include etd html class for getContentXml function
require_once("models/datastreams/etd_html.php");

class Etd_Controller_Action_Helper_ProcessPDF extends Zend_Controller_Action_Helper_Abstract {

  // run pdftohtml on pdf
  // process resulting file to pull out metadata


  public $next;
  private $current_page;
  private $previous_line;
  public $fields;

  private $order;

  private $doc;
  private $debug;

  private $title_complete = false;

  public function __construct() {
    $this->initialize_fields();
    
    // FIXME: how to encapsulate order information ?
    // (note: this is not yet in use... )
    $this->order= array("circ_agreement" => 0,
      "signature"    => 1,
      "abstract_cover" => 2,
      "abstract"   => 3,
      "abstract_cont"  => 4, // (only in some cases)
      // could be acknowledgements
      // table of contents somewhere after this
      );
     
  }

  /**
   * initialize (or reset) fields to be filled in
   */
  public function initialize_fields() {
    // set the fields to be filled in 
    $this->fields = array("title" => "",
        "keywords" => array(),
        "filename" => "",
        // advisor, committee and department will no longer be populated;
        // leaving empty arrays to avoid potential errors if any code is missed
        // that still expects these values
        "department" => "",
        "advisor" => array(),    
        "committee" => array(),  
        // table of contents will no longer be populated, since detection 
        // and formatting is often incomplete and unreliable
        "toc" => "",
        // NOTE: abstract detection will be attempted since it abstract text
        // is used to clean the title, but abstract text may be incomplete or unreliable
        "abstract" => "",
        "distribution_agreement" => false);
  }
  
  /**
   * shortcut to process_upload (default helper action)
   * @see Etd_Controller_Action_Helper_ProcessPDF::process_upload()
   */
  public function direct($fileinfo) {
    return $this->process_upload($fileinfo);
  }


  /**
   * process an uploaded PDF and extract ETD record information
   * - runs pdftohtml on the first 10 pages of the file
   * - extracts record info based on expected page order for ETD submissions
   *
   * @param array $fileinfo php file upload info
   * @return array array of information found in the PDF
   */
  public function process_upload($fileinfo) {
    if (Zend_Registry::isRegistered('debug')) // set in environment config file
      $this->debug = Zend_Registry::get('debug');
    else  $this->debug = false;     // if not set, default to debugging off

    $config = Zend_Registry::get('config');
    $tmpdir = $config->tmpdir;
    if (!file_exists($tmpdir))    // create temporary directory if it doesn't exist
      mkdir($tmpdir);
    if(!is_writable($tmpdir))
      throw new Exception("ProcessPDF: Temporary directory $tmpdir is not writable");


    // location of temporary upload copy of user's pdf
    $pdf = $fileinfo['tmp_name'];
    // store the original filename
    $this->fields['filename'] = $fileinfo['name'];
    // path to temporary uploaded file
    $this->fields['pdf'] = $pdf;
    
    $flashMessenger = $this->_actionController->getHelper('FlashMessenger');

    $fileUpload = $this->_actionController->getHelper('FileUpload');

    // define errors so it can be treated as an array even if things go wrong
    $this->_actionController->view->errors = array();
    
    // verify that the upload succeeded and file is correct type
    if($fileUpload->check_upload($fileinfo, array("application/pdf"))) {

      // errors to be displayed to the user
      // log of what was found where
      $this->_actionController->view->log = array();
      $_html = tempnam($tmpdir, "html_");
      // NOTE: pdftohtml insists on creating output file as .html
      $html  = $_html . ".html";
      
      $pdftohtml = $config->pdftohtml;
      // pdftohtml options: -q -noframes -i -l 10 filename.pdf filename.html
      // generate a single file (no frames), first 10 pages, ignore images, quiet (no output)
      $cmd = escapeshellcmd("$pdftohtml -q -noframes -i -l 10 $pdf $html");
      $result = system($cmd);
      // error if system call exited with failure code or html file does not exist
      if ($result === false || !file_exists($html)) {
        // is this user-comprehensible enough?
        $this->_actionController->view->errors[] = "Failure reading PDF";
      } else {
        $this->getInformation($html);

        // remove temporary html file after we have pulled the information from it
        unlink($html);
        unlink($_html);   // unlink empty tmp file created by tempnam()
      }
      
      // return fields even if there was an error, so we at least have original filename
      return $this->fields;
    }

  }


  /**
   * extract information from an html file
   * @param string $file filename
   */
  public function getInformation($file) {
    $doc = new DOMDocument();
    $this->doc = $doc;

    // clean up any unclosed/mismatched tags generated by pdftohtml
    $tidy_opts = array("output-xml" => true,
           "drop-font-tags" => true,
           "drop-proprietary-attributes" => true,
           "clean" => true,
           "merge-divs" => true,
           "join-styles" => true,
           "join-classes" => true,
           "bare" => true);
    $doc->loadHTML(tidy_repair_file($file, $tidy_opts, "utf8"));

    // looking for meta tags
    //  (depending on how pdf was created, there may be useful meta tags)
    $meta_tags = $doc->getElementsByTagName("meta");
    for ($i = 0; $i < $meta_tags->length; $i++) {
      $meta = $meta_tags->item($i);

      // capture any keyword or subject meta tags in the header
      if ($meta->getAttribute("name") == "keywords" ||
          $meta->getAttribute("name") == "subject") {
        $tags = $meta->getAttribute("content");
        $keywords = preg_split("/, ?/", $tags);
        $this->fields['keywords'] = array_merge($this->fields['keywords'], $keywords);
      }
    }

    $body_tags = $doc->getElementsByTagName("body");
    $body = $body_tags->item(0);

    if (preg_match("/^\s*$/", $body->textContent)) {
      $this->_actionController->view->errors[] = "No text content found; is PDF an image-only document?";
      return;
    }

    // creating a DOMDocument to hold the contents of the current page
    // NOTE: using separate dom to allow deep-importing of nodes
    // (some content was getting missed when trying to append to fragment)
    
    $page = new DOMDocument();
    $div = $page->createElement("div", "");
    $page->appendChild($div);
    
    for ($i = 0; $i < $body->childNodes->length; $i++) {
      $node = $body->childNodes->item($i);
      if ($node->nodeName == "a") { // beginning of a page
        // process the previous page
        if ($page->hasChildNodes()) {
          $this->process_page($page);
        }

        // number of the current page
        $this->current_page = $node->getAttribute("name");

        // empty out the current page doc
        $page->removeChild($page->documentElement);
        $div = $page->createElement("div", "");
        $page->appendChild($div);
      }

      // deep import and append node into temporary current page doc
      $newnode = $page->importNode($node, true);
      $page->documentElement->appendChild($newnode);
    }

    // process the final page
    $this->process_page($page);


    // minor cleaning on fields that need it
    /* FIXME: some dom equivalent for this? */
    
    $this->fields['abstract'] = trim($this->fields['abstract'], "\n ");
    $this->fields['abstract'] = preg_replace("|<hr/?>|", "",  $this->fields['abstract']);   
    $this->fields['abstract'] = preg_replace("|<a name=\"?\d+\"?\/>|", "",  $this->fields['abstract']);
    // consolidate repeating line breaks into a single line break
    $this->fields['abstract'] = preg_replace("|(<br/>){2,}|", "<br/>",  $this->fields['abstract']);
    $this->fields['abstract'] = preg_replace("|^\s*(<br/>)?\s*(<b>)?\s*abstract\s*(</b>)?\s*(<br/>)?\s*|i", "",  $this->fields['abstract']);    
    $this->fields['abstract'] = trim($this->fields['abstract'], "\n "); 
    $this->fields['abstract'] = preg_replace("|\s*(<br/>)?\s*(<b>)?\s*[Bb][Yy]\s*(<br/>)?\s*[A-Z]+.*\s*(</b>)?\s*(<br/>)?\s*|", "<br/>",  $this->fields['abstract']);     
    
    // Trim the title from the beginning of the Abstract if present.   
    $trimmed_title = preg_replace("|\s*(<br/>)?\s*(<b>)?\s*abstract\s*(</b>)?\s*(<br/>)?\s*|i", "",  trim($this->fields['title'], "\n "));     
    if (isset($this->fields['abstract']) && !empty($this->fields['abstract'])) {
      $title_pos = $this->positionMatch($this->fields['abstract'], $trimmed_title);
      if ($title_pos > 0) {      
        $this->fields['abstract'] = substr($this->fields['abstract'], $title_pos);
      }
    }  

    // some error-checking to display useful messages to the user
    if ($this->fields["distribution_agreement"] == false)
      $this->_actionController->view->errors[] = "Distribution Agreement not detected";
    // FIXME: only true essential is title - should we fall back to filename as title?
    if (!isset($this->fields['title']) || $this->fields['title'] == "") 
      $this->_actionController->view->errors[] = "Could not determine title";
    
    // FIXME: what other fields should be checked?

  }


  /**
   * Process a page of text according to what page is expected next.
   * Stores values found into class variable fields.
   *
   * @param DOMDocument $page temporary DOMDocument with only the contents of the current page
   */
  public function process_page(DOMDocument $page) {
    $page->normalize();
    $content = $page->textContent;  // complete text content under this node
    // collapse multiple spaces into one, to simplify regular expressions
    $content = preg_replace("/\s+/", " ", $content);

    // look for content based on the next expected page
    switch($this->next) {

    case "signature":
      if ($this->debug) print "signature page expected<br>\n";
      
      $this->processSignaturePage($page);
      $this->_actionController->view->log[] = "Found Title Page on page " . $this->current_page;
      $this->next = "abstract-cover";
      break;

    
    case "abstract-cover": $this->next = "abstract";
      // abstract cover page - do nothing
      break;

    case "abstract":
      // save as xml to preserve formatting (xml without wrapping div node)
      $this->fields['abstract'] .= etd_html::getContentXml($page->documentElement);

      // abstract could be two pages, so next page may be more abstract
      $this->next = "abstract-cont";  // possibly 
      break;

    case "abstract-cont":
          // possible second page of abstract - eliminate the following conditions           
      if  ( 
          // does the page begin with the title?
          (0==strcmp(trim($this->fields['title']), substr(trim($content), 0, strlen($this->fields['title']))))
          // does the page begin with "Acknowledgements"?          
          || (preg_match("/^Acknowledgements/i", trim($content)))
          // does the page begin with "submitted to the Faculty of the Graduate"?          
          || (preg_match("/submitted\s+to\s+the\s+Faculty\s+of\s+the\s+Graduate/", trim($content)))          
          ) 
      {
        if ($this->debug) print "DEBUG: abstract does not continue on second page<br>\n";
        $this->_actionController->view->log[] = "Found Abstract on page " .
          ($this->current_page - 1);
        $this->next = "";
      } else {  // Abstract DOES continue on second page            
        if ($this->debug) print "DEBUG: abstract continues on second page<br>\n";
        $this->_actionController->view->log[] = "Found Abstract on pages " .
          ($this->current_page - 1) . "-" . $this->current_page;
        $this->fields['abstract'] .= etd_html::getContentXml($page->documentElement);
        $this->next = "";
      }
      break;


    default:
      // if next page is not set, try to figure out where we are
      
      // look for expected first page: Distribution (formerly Circulation) Agreement
      if (!$this->fields["distribution_agreement"] &&
            (preg_match("/Distribution\s+Agreement/i", $content) || // new text, fall 2008

             // NOTE: using element's nodeValue to get all text with no tags
             // (tags could be anywhere mixed in with the text we actually care
             // about, depending on how the document is formatted)
            preg_match("/grant\s+to\s+Emory\s+University\s+.*non-\s*exclusive\s+license/s",
                 $page->documentElement->nodeValue) ||
             
            // old version of Circ Agreement text
            preg_match("/Circulation\s+Agreement/i", $content) ||
            preg_match("/available\s+for\s+inspection\s+and\s+circulation/m", $content)) ) {
        // part of boiler-plate grad-school circ agreement text
        if ($this->debug) print "* found circ agreement\n";
        $this->_actionController->view->log[] = "Found Distribution Agreement on page " . $this->current_page;
        $this->next = "signature";  // next page expected
        $this->fields["distribution_agreement"] = true;
        return;
      } elseif ($this->current_page == 1) {
        // page 1 and no circ agreement - probably signature page
        $this->processSignaturePage($page);
      }
      
    }
  }

  /**
   * process signature page - special handling for page with most content
   *
   * @param DOMDocument $page  with the contents of the signature page
   */
  public function processSignaturePage(DOMDocument $page) {
    // retrieve the title
    $this->title_complete = false;
    $this->find_title($page->documentElement);

    // clean up messy bold tags in the title
    // - remove bolded whitespace or empty bold tags (caused by line breaks)
    $this->fields['title'] = preg_replace("|<b>(\s*)</b>|", "$1", $this->fields['title']);
    // - remove whitespace between bold tags
    $this->fields['title'] = preg_replace("|</b>(\s*)<b>|", "$1", $this->fields['title']);
    // - clean up any redundant whitespace
    $this->fields['title'] = preg_replace("/\s+/", " ", $this->fields['title']);
    // - remove leading or trailing whitespace to simplify the next regexp
    $this->fields['title'] = trim($this->fields['title']); 
    // - if the whole title is bold, remove bold tags
    $this->fields['title'] = preg_replace("|^<b>(.*)</b>$|", "$1", $this->fields['title']);

    // work through lines to find advisor & committee names
    // NOTE: committee member detection disabled 2012/06 to avoid generating 
    // records with inaccurate names or even potentially invalid mods (duplicate names/ids)
    //$this->processSignatureLines($page);
  }


  /**
   * Find the title on the current page, recursing through nodes to add
   * text and allowed formatting until we hit "by" or "By" (signals end
   * of title).  Stores value in class variable fields['title']
   *
   * @param DOMNode $node
   */
  public function find_title($node) {
    // tags for allowed/expected formatting in title
    $tags_to_keep = array("i", "b");
    // empty tags to be excluded from title
    $tags_to_skip = array("a", "br", "hr");

    
    for($i = 0; $i < $node->childNodes->length; $i++) {
      $subnode = $node->childNodes->item($i);

      // *before* recursing on subnodes: if this is a tag we want, add to the title
      /*  if (in_array($subnode->localName, $tags_to_keep)) {
          $this->fields['title'] .= $node->ownerDocument->saveXML($subnode);

      // recurse on any childnodes to catch formatted text (e.g., italics)
      } else*/
      if ($subnode->hasChildNodes()) {
        $nodeName = $subnode->localName;
        $keeptag = (in_array($nodeName, $tags_to_keep));

        if ($keeptag) $this->fields['title'] .= "<" . $nodeName . ">";

        $this->find_title($subnode);

        if ($keeptag) $this->fields['title'] .= "</" . $nodeName . ">";

        // end of title was found in a child node
        if ($this->title_complete == true) break;

      // skip (empty) tags that should not be included in the title
      } elseif (in_array($subnode->localName, $tags_to_skip)) {
        continue;

      // marker for the end of the title - break out of the loop
      } elseif (preg_match("|^(.*)[Bb][Yy]|", $subnode->textContent, $matches)) {
        // preserve any content before the "by", in case it is on same line as title
        $this->fields['title'] .= $matches[1];
        $this->title_complete = true;
        break;

      // default action: add the current node to the title
      } else {
        $tmp = $node->ownerDocument->saveXML($subnode);
        $this->fields['title'] .= $node->ownerDocument->saveXML($subnode);
      }
    } // end looping through child nodes
  }

  /**
   *  NOTE: committee member autodetection disabled 2012/06 in an attempt to
   *  avoid detection errors or generating invalid records.
   */

  /**
   * minimal cleaning for advisor/committee names - remove dr., ph.d., m.s., etc.
   *
   * @param string $name name
   * @return string cleaned name
   */
  private function clean_name ($name) {  
    $name = str_replace("Dr. ", "", $name);
    // match PhD or Ph.D.; also match MD/PhD, or M.S.
    $name = preg_replace("!, (M\.S|MD|M\.D|MPH|MSPH|PhD|Ph\.D)/?(M\.S|MD|M\.D|MPH|MSPH|PhD|Ph\.D)?\.?!", "", $name); 
    // match for a second degrees
    $name = preg_replace("!, (M\.S|MD|M\.D|MPH|MSPH|PhD|Ph\.D)/?(M\.S|MD|M\.D|MPH|MSPH|PhD|Ph\.D)?\.?!", "", $name); 
    $name = preg_replace("/\((.*)\)/", "$1", $name);  // remove parentheses around the name
    $name = trim($name);   
    
    // Put last name first.
    $name = trim($name);           
    if (preg_match("/^([^\s,]+)(\s[A-Z]\.)?\s([^\s,]+),?$/", $name, $matches)) {       
      if (count($matches) == 4) $name = $matches[3] . ", " . $matches[1] . $matches[2];
      else $name = $matches[2] . ", " . $matches[1]; 
    } 
     
    return $name;
  }
  /**
   * return the position of the needle found in haystack that contains tags.
   *
   * @param string $haystack haystack
   * @param string $needle needle
   * @return int position
   */
function positionMatch($abstract, $title) {
   
    //$logger = Zend_Registry::get('logger');
    //$logger->err("Title=[$title]\n");    
    //$logger->err("Abstract=[$abstract]\n");
       
    $pos = 0; // position in abstract index
           
    for ($i = 0; $i <= strlen($title)-1; $i++) {
      
      //$logger->err("ord[" . ord($abstract[$pos]) . "] pos[$pos]=[" . $abstract[$pos] . "]  === i[$i]=[" . $title[$i] . "] ord[" . ord($title[$i]) . "]\n");
      
      // Try to match abstract char with title; be flexible on space character.
      if ($abstract[$pos] == $title[$i] || (ord($abstract[$pos])==10 && ord($title[$i]) == 32)) {
        $pos++;        // match char in title, continue.
      }
      // Skip over tags and white spaces in the abstract text
      else if ($pos < strlen($abstract) &&
        ($abstract[$pos] == "<" || ($abstract[$pos] == " ") || (ord($abstract[$pos])==10))) {
       
        while ($pos < strlen($abstract) &&
              ($abstract[$pos] == "<" || ($abstract[$pos] == " ") || (ord($abstract[$pos])==10))) {             
         
          // Check for beginning of html tag in abstract, and jump to end of tag
          if ($abstract[$pos] == "<") {             
            $endpos = strpos(substr($abstract, $pos), ">");
            if ($endpos) $pos = $pos + $endpos + 1;    // End tag found, jump to end.
            else return 0; // No end tag found; failure to match.            
          }
          // Check for space character(s) in abstract, jump to end of space character(s)
          else if (($abstract[$pos] == " ") || (ord($abstract[$pos])==10)) {       
            while ($pos < strlen($abstract) && ($abstract[$pos] == " "|| ord($abstract[$pos])==10)) { 
              $pos++;
            }
          }
          // Done jumping over format characters, try to match char with title.
          if ($abstract[$pos] == $title[$i]) {             
            $pos++;    // match char in title, continue.
          }
        }      
      }     
      else {  // Failure to match
        return 0;
      }      
    }
                 
    return $pos;
  }
}

?>
