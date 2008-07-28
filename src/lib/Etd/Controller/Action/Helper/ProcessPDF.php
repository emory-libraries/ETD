<?php

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

  private $found_circ = false;

  public function __construct() {
    $this->initialize_fields();
    
    // FIXME: how to encapsulate order information ?
    // (note: this is not yet in use... )
    $this->order= array("circ_agreement" => 0,
			"signature" 	 => 1,
			"abstract_cover" => 2,
			"abstract"	 => 3,
			"abstract_cont"  => 4, // (only in some cases)
			// could be acknowledgements
			// table of contents somewhere after this
			);
		 
  }

  // initialize (or reset) fields to be filled in
  public function initialize_fields() {
    // set the fields to be filled in 
    $this->fields = array("title" => "",
			  "department" => "",
			  "advisor" => "",
			  "committee" => array(),
			  "abstract" => "",
			  "toc" => "",
			  "keywords" => array());
  }
  

  public function direct($fileinfo) {
    return $this->process_upload($fileinfo);
  }


  public function process_upload($fileinfo) {
    if (Zend_Registry::isRegistered('debug'))	// set in environment config file
      $this->debug = Zend_Registry::get('debug');
    else  $this->debug = false;			// if not set, default to debugging off

    $config = Zend_Registry::get('config');
    $tmpdir = $config->tmpdir;
    if (!file_exists($tmpdir)) 		// create temporary directory if it doesn't exist
      mkdir($tmpdir);
    if(!is_writable($tmpdir))
      throw new Exception("ProcessPDF: Temporary directory $tmpdir is not writable");


    // location of temporary upload copy of user's pdf
    $pdf = $fileinfo['tmp_name'];
    
    $flashMessenger = $this->_actionController->getHelper('FlashMessenger');

    $fileUpload = $this->_actionController->getHelper('FileUpload');

    // verify that the upload succeeded and file is correct type
    if($fileUpload->check_upload($fileinfo, array("application/pdf"))) {

      // errors to be displayed to the user
      $this->_actionController->view->errors = array();
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
	$this->fields['pdf'] = $pdf;

	// remove temporary html file after we have pulled the information from it
	unlink($html);
	unlink($_html);		// unlink empty tmp file created by tempnam()
	
	return $this->fields;
      }
    }

  }


  public function getInformation($file) {
    $doc = new DOMDocument();
    $this->doc = $doc;

    // clean up any unclosed/mismatched tags generated by pdftohtml
    $tidy_opts = array("output-xml" => true,
		       "drop-font-tags" => true,
		       "drop-proprietary-attributes" => true,
		       "clean" => true,
		       "join-styles" => true);
    $doc->loadHTML(tidy_repair_file($file, $tidy_opts));

    // looking for meta tags
    // 	(depending on how pdf was created, there may be useful meta tags)
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
      if ($node->nodeName == "a") {	// beginning of a page
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
    $this->fields['toc'] = preg_replace("|<hr/?>|", "",  $this->fields['toc']);
    $this->fields['toc'] = preg_replace("|^\s*(<b>)?Table of Contents\s*(</b>)?\s*(<br/>)?\s*|i", "",
					     $this->fields['toc']);
    $this->fields['toc'] = preg_replace("|<a name=\"\d\"\/>|", "",  $this->fields['toc']);

    $this->fields['abstract'] = preg_replace("|<hr/?>|", "",  $this->fields['abstract']);
    $this->fields['abstract'] = preg_replace("|<a name=\"\d\"\/>|", "",  $this->fields['abstract']);
    $this->fields['abstract'] = preg_replace("|^\s*(<b>)?abstract\s*(</b>)?\s*(<br/>)?\s*|i", "",  $this->fields['abstract']);



    // some error-checking to display useful messages to the user
    if ($this->found_circ == false)
      $this->_actionController->view->errors[] = "Circulation Agreement not detected";
    // FIXME: only true essential is title - should we fall back to filename as title?
    if (!isset($this->fields['title']) || $this->fields['title'] == "") 
      $this->_actionController->view->errors[] = "Could not determine title";
    if (!isset($this->fields['abstract']) || $this->fields['abstract'] == "") 
      $this->_actionController->view->errors[] = "Could not find abstract";
    
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
    $content = $page->textContent;	// complete text content under this node
    // collapse multiple spaces into one to simplify regular expressions
    $content = preg_replace("/ +/", " ", $content);

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
      // save as xml to preserve formatting
      $this->fields['abstract'] .= $page->saveXML($page->documentElement);

      // abstract could be two pages, so next page may be more abstract
      $this->next = "abstract-cont";	// possibly 
      break;

    case "abstract-cont":
      // possible second page of abstract - check
      if (preg_match("/submitted\s+to\s+the\s+Faculty\s+of\s+the\s+Graduate/", $content)) {
	// title page
	if ($this->debug) print "DEBUG: abstract does not continue on second page<br>\n";
	$this->_actionController->view->log[] = "Found Abstract on page " .
	  ($this->current_page - 1);
	$this->next = "";
      } else {	// no title page: must be second page of abstract
	if ($this->debug) print "DEBUG: abstract continues on second page<br>\n";
	$this->_actionController->view->log[] = "Found Abstract on pages " .
	  ($this->current_page - 1) . "-" . $this->current_page;
	$this->fields['abstract'] .= $page->saveXML($page->documentElement);
	$this->next = "";
      }
      break;


    default:
      // if next page is not set, try to figure out where we are
    
      if (preg_match("/Circulation Agreement/", $content) ||
	  preg_match("/available\s+for\s+inspection\s+and\s+circulation/m", $content)) {
	// part of boiler-plate grad-school circ agreement text
	if ($this->debug) print "* found circ agreement\n";
	$this->_actionController->view->log[] = "Found Circulation Agreement on page " . $this->current_page;
	$this->next = "signature";	// next page expected
	$this->found_circ = true;
	return;
      } elseif ($this->current_page == 1) {
	// page 1 and no circ agreement - probably signature page
	$this->processSignaturePage($page);
      } elseif (preg_match("/Table of Contents/i", $content)) {
	if ($this->debug) print "* found table of contents - page " . $this->current_page . "\n";
	$this->_actionController->view->log[] = "Found Table of Contents on page " . $this->current_page;

	$this->fields['toc'] .= $page->saveXML($page->documentElement);
	// FIXME: cleaning for dashes/dots in table of contents ?
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
    $this->find_title($page->documentElement);
    // clean up title whitespace
    $this->fields['title'] = preg_replace("/\s+/", " ", $this->fields['title']);

    // work through lines to find advisor & committee names
    $this->processSignatureLines($page);
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
      if (in_array($subnode->localName, $tags_to_keep)) {
	$this->fields['title'] .= $node->ownerDocument->saveXML($subnode);

      // recurse on any childnodes to catch formatted text (e.g., italics)
      } elseif ($subnode->hasChildNodes()) {
      	$this->find_title($subnode);
      	$nodeName = $subnode->localName;

      // skip (empty) tags that should not be included in the title
      } elseif (in_array($subnode->localName, $tags_to_skip)) {
	continue;

      // marker for the end of the title - break out of the loop
      } elseif (preg_match("|(.*)[Bb]y|", $subnode->textContent, $matches)) {
	// preserve any content before the "by", in case it is on same line as title
	$this->fields['title'] .= $matches[1];
	break;

      // default action: add the current node to the title
      } else {
	$tmp = $node->ownerDocument->saveXML($subnode);
	$this->fields['title'] .= $node->ownerDocument->saveXML($subnode);
      }
      
    } // end looping through child nodes
  }

  
  /**
   * recursive function - wrapper to process_line for finding names on
   * signature page
   *
   * @param DOMNode $node
   */
  public function processSignatureLines(DOMNode $node) {
    for($i = 0; $i < $node->childNodes->length; $i++) {
      $subnode = $node->childNodes->item($i);
      if ($subnode->hasChildNodes()) {
	$this->processSignatureLines($subnode);
      } else {
	$this->processSignatureLine($subnode);
      }
    }
  }


  /**
   * Process lines of signature page to find Advisor, Committee, Department.
   * Stores last line for certain patterns (Advisor/Committee label on line below)
   * @param DOMNode $node single node with no child nodes
   */
  public function processSignatureLine(DOMNode $node) {
    $content = $node->textContent;
    // skip blank lines, and don't store as previous line
    if ($content == "") return;

    /** Advisor **/
    //	 look for advisor on the same line, either as "Name, Advisor" or Advisor: Name"
    if (preg_match("/Advis[eo]r:\s*(.+)/m", $content, $matches)
	|| preg_match("/([^_]+),?\s*Advis[eo]r/m", $content, $matches)) {
      $this->fields['advisor'] = $this->clean_name($matches[1]);
      if ($this->debug) print "DEBUG: found advisor: " . $this->fields['advisor'] . "\n";

    //  look for advisor on two lines: Name (line 1), Advisor (line 2)
    } elseif (preg_match("/^ *Advis[eo]r/", $content)) {
      // pick up name from the preceding line 
      $this->fields['advisor'] = $this->clean_name($this->previous_line);

      
      /** Committee Members **/
      //  look on two lines: Name (line 1), Committee Member or Reader (line 2)
    } elseif (preg_match("/^ *Committee/", $content) || preg_match("/^ *Reader/", $content)) {
      $name = $this->clean_name($this->previous_line); // pick up the committee from the line before
      if ($name != "Committee Member" && $name != "") {	// in some cases getting false matches
	array_push($this->fields['committee'], $name);
      }
      // look on a single line: "Name, Committee Member"
    } elseif (preg_match("/^([^_]+), Committee/", $content, $matches)
	      || preg_match("/^([^_]+), Reader/", $content, $matches)) {
	array_push($this->fields['committee'], $this->clean_name($matches[1]));
	      
    } elseif ($this->fields['department'] == "") {
      $this->department($content);
    }

    // store current line as the next previous for multi-line matches
    $this->previous_line = $content;
  }
  

  /**
   * detect department name - wrapper for single-line department detection
   *
   * @param array $lines array of lines in the page
   */
  private function findDepartment(array $lines) {
    // only look for department if not already set
    if (!isset($this->fields['department']) || $this->fields['department'] == "") {

      // department should be on a single line
      foreach ($lines as $line) {
	$this->department($line);
      }
    }
  }
    
  /**
   * Pull department name from a single line.
   * Stores department in class variable fields['department']
   *
   * @param string $line single line of text
   */
  public function department($line) {  
    // department should be on a single line
    // matches either "Department of Chemistry" or "Chemistry Department"
    if (preg_match("/Department of (.+)/m", $line, $matches)
	|| preg_match("/(.+) Department/m", $line, $matches)) {
      $this->fields['department'] = $matches[1];

    // match any specially named departments here
    } elseif (preg_match("/Graduate Institute of the Liberal Arts/", $line)) {
      $this->fields['department'] = "Graduate Institute of the Liberal Arts";
    }

    // trim any whitespace
    $this->fields['department'] = trim($this->fields['department']);
  }
  

  /**
   * minimal cleaning for advisor/committee names - remove dr., ph.d., m.s., etc.
   *
   * @param string $name name
   * @return string cleaned name
   */
  private function clean_name ($name) {
    $name = str_replace("Dr. ", "", $name);
    //   match PhD or Ph.D.; also match MD/PhD, or M.S.
    $name = preg_replace("|, (M.S.)?(MD/?)?(Ph\.?D\.?)?|", "", $name);
    $name = preg_replace("/\((.*)\)/", "$1", $name);	// remove parentheses around the name
    $name = trim($name);
    return $name;
  }





}

?>
