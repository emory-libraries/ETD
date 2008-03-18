<?php

class Etd_Controller_Action_Helper_ProcessPDF extends Zend_Controller_Action_Helper_Abstract {

  // run pdftohtml on pdf
  // process resulting file to pull out metadata

  //        return $this->_actionController;

  private $next;
  private $fields;

  private $debug;

  public function direct($fileinfo) {
    return $this->process_upload($fileinfo);
  }


  public function process_upload($fileinfo) {
    if (Zend_Registry::isRegistered('debug'))	// set in environment config file
      $this->debug = Zend_Registry::get('debug');
    else  $this->debug = false;			// if not set, default to debugging off

    $config = Zend_Registry::get('config');
    $tmpdir = $config->tmpdir;
    if (!file_exists($tmpdir))			// create temporary directory if it doesn't exist
      mkdir($tmpdir); 

    // filename where the temporary upload file should be moved
    $pdf = $tmpdir . "/" . $fileinfo['name'];
    // if user is so silly as to include spaces in their filename, get rid of them
    $pdf = str_replace(" ", "_", $pdf);
    
    $flashMessenger = $this->_actionController->getHelper('FlashMessenger');

    $fileUpload = $this->_actionController->getHelper('FileUpload');
    
    if($fileUpload->direct($fileinfo, $pdf, array("application/pdf"))) {

      $this->_actionController->view->log = array();
      $html = $tmpdir . "/" . basename(str_replace(" ", "_", $fileinfo['name']), ".pdf") . ".html";
      
      $pdftohtml = $config->pdftohtml;
      // pdftohtml options: -q -noframes -i -l 10 filename.pdf filename.html
      // generate a single file (no frames), first 10 pages, ignore images, quiet (no output)
      $cmd = "$pdftohtml -q -noframes -i -l 10 $pdf $html";
      $result = system($cmd);
      if ($result === 0) {
	$flashMessenger->addMessage("error converting pdf to html");
      } else {
	$this->getInformation($html);
	$this->fields['pdf'] = $pdf;

	// remove temporary html file after we have pulled the information from it
	unlink($html);
	
	return $this->fields;
      }
    }

  }
   
  private function getInformation($file) {
    $handle = fopen($file, "r");

    $page = "";	// number of the current page
    $curpage = "";	// content of the current page

    $this->next = "";	// expected content for the next page


    // fields to be filled in 
    $this->fields = array("title" => "",
			  "department" => "",
			  "advisor" => "",
			  "committee" => array(),
			  "abstract" => "",
			  "toc" => "",
			  "keywords" => array());
    
    if ($handle) {
      while (! feof($handle)) {
	$buffer = fgets($handle);
	if (preg_match("/<A name=(\d+)/", $buffer, $matches)) {
	  // found a new page
	  if ($page) {	// process the last one
	    $this->process_page($curpage, $page);
	  }
	  $page = $matches[1];	// save the page number
	  $curpage = $buffer;	// start saving the content of the new page

	} elseif (preg_match("{<META +name=\"(keywords|subject)\" +content=\"(.*)\">}", $buffer, $matches)) {
	  // depending on how pdf was created, there may be meta tags 
	  $keywords = preg_split("/, ?/", $matches[2]);
	  $this->fields['keywords'] = array_merge($this->fields['keywords'], $keywords);
	} else {
	  $curpage .= $buffer;	// add the current line to current page
	}
      }
      // process the last page
      $this->process_page($curpage, $page);
      fclose($handle);
    }


    // minor cleaning on fields that need it
    $this->fields['toc'] = preg_replace("|<hr/?>|", "",  $this->fields['toc']);
    $this->fields['toc'] = preg_replace("|^\s*(<b>)?Table of Contents\s*(</b>)?\s*(<br/>)\s*?|i", "",
					     $this->fields['toc']);
    $this->fields['toc'] = preg_replace("|</?html>|i", "", $this->fields['toc']);

    $this->fields['abstract'] = preg_replace("|<hr/?>|", "",  $this->fields['abstract']);
    $this->fields['abstract'] = preg_replace("|<A name=\d></a>|", "",  $this->fields['abstract']);
    $this->fields['abstract'] = preg_replace("|^\s*(<b>)?abstract\s*(</b>)?\s*(<br/>)?\s*|i", "",  $this->fields['abstract']);
    $this->fields['abstract'] = preg_replace("|</?html>|i", "", $this->fields['abstract']);
	

    $this->fields['department'] = trim($this->fields['department']);
  }
  

  // loop through the pages, determining which content to look for
  private function process_page($content, $number) {
    //    global $next, $title, $department, $advisor, $committee, $abstract, $toc;

    $_content = $content;	// preserve the original version of the page


    // remove break tags 
    $content = str_replace("<br>", " ", $_content);
    // collapse multiple spaces into one
    $content = preg_replace("/ +/", " ", $content);
    // split by lines
    $lines = split("\n", $content);
    // remove newlines
    $content = str_replace("\n", "", $content);
	
  
    if ($this->debug) print "processing page $number<br>";
    switch($this->next) {
    case "signature":
      if ($this->debug) print "signature page expected<br>\n";
      $this->processSignaturePage($content, $lines);
      $this->_actionController->view->log[] = "expected title page on page $number";
      $this->next = "abstract-cover";
      break;
    
    case "abstract-cover": $this->next = "abstract";
      // abstract cover page - do nothing
      break;

    case "abstract":
      $this->fields['abstract'] .= $content;
      // need to do some cleaning - remove all tags
      // remove "abstract:?"/i if it's at the beginning
      // possibly remove title if at beginning?

      // leave next as abstract, since it could be two pages?
      $this->next = "abstract-cont";	// possibly 
      break;

    case "abstract-cont":
      // possible second page of abstract - check
      if (preg_match("/submitted to the Faculty of the Graduate/", $content)) {
	// title page
	// if department has not yet been found, look for it here
	if (!isset($department) || $department == "") {
	  foreach ($lines as $line) {
	    if (preg_match("/Department of (.+)/m", $line, $matches)
		|| preg_match("/(.+) Department/m", $line, $matches)) {
	      $this->fields['department'] = $matches[1];
	    }
	  }
	}
	if ($this->debug) print "DEBUG: abstract does not continue on second page<br>\n";
	$this->_actionController->view->log[] = "found abstract on page " . ($number - 1);
	$this->next = "";
      } else {
	if ($this->debug) print "DEBUG: abstract continues on second page<br>\n";
	$this->_actionController->view->log[] = "found abstract on pages " . ($number - 1) . "-" . $number;
	$this->fields['abstract'] .= $content;
	$this->next = "";
      }
      break;

    default:
      // if next is not defined - try to figure out where we are
    
      if (preg_match("/Circulation Agreement/", $content) ||
	  preg_match("/available for inspection and circulation/", $content)) {
	// part of boiler-plate grad-school circ agreement text
	if ($this->debug) print "* found circ agreement - page $number<br>\n";
	$this->_actionController->view->log[] = "found circulation agreement on page $number";
	$this->next = "signature";	// next page expected
	return;
      } elseif ($number == 1) {
	// page 1 and no circ agreement - probably signature page
	$this->processSignaturePage($content, $lines);
      } elseif (preg_match("/Table of Contents/i", $content)) {
	if ($this->debug) print "* found table of contents - page $number<br>\n";
	$this->_actionController->view->log[] = "found table of contents on page $number";
	//	$toc = "";
	foreach ($lines as $line) {
	  // remove link and bold tags
	  $_line = preg_replace("|</?[ab][^<>]*>|i", "", $line);

	  if (! preg_match("/^\s*$/", $_line)) {	// ignore blank lines
	    // remove .... ## at end of line
	    $_line = preg_replace("/----*/", "", $_line);	// remove toc dashes (needed?)
	    $this->fields['toc'] .= preg_replace("/[\.-]* *\d* *$/", "", $_line) . "<br/>";
	  }
	}
      }

      // if department has not already been found, look for it again
      if (!isset($department) || $department == "") {
	foreach ($lines as $line) {
	  if (preg_match("/Department of (.+)/m", $line, $matches)
	      || preg_match("/(.+) Department/m", $line, $matches)) {
	    $department = $matches[1];
	  }
	}
      }

    }
  
  }


  private function processSignaturePage($content, $lines) {
    global $title, $department, $advisor, $committee;
    // pick up the title  (could be multiline)
    if (preg_match("|<A name=\d><\/a>(.+)[Bb]y|D", $content, $matches)) {
      $this->fields['title'] = $matches[1];
      if ($this->debug) print "DEBUG: found title:<pre>$title</pre>";
    }
    
    // loop through the lines for single-line content
    for($i = 0; $i < count($lines); $i++) {
      $line = $lines[$i];
      // department  (should be on a single line)
      if (preg_match("/Department of (.+)/m", $line, $matches)
	  || preg_match("/(.+) Department/m", $line, $matches)) {
	$this->fields['department'] = $matches[1];
	if ($this->debug) print "DEBUG: found department:<pre>$department</pre>";
	// FIXME: if department not here, look on title page

	// match any specially named departments here
      } elseif (preg_match("/Graduate Institute of the Liberal Arts/", $line)) {
	$this->fields['department'] = "Graduate Institute of the Liberal Arts";
      }
    
      // advisor
      if (preg_match("/Advis[eo]r:(.+)/", $line, $matches) ||
	  preg_match("/([^_ ]+)Advis[eor]/", $line, $matches)) {
	$this->fields['advisor'] = $this->clean_name($matches[1]);
	if ($this->debug) print "DEBUG: found advisor: (1)<pre>$advisor</pre> ";
      } elseif (preg_match("/^ *Advis[eo]r/", $line)) {
	$this->fields['advisor'] = $this->clean_name($lines[$i-1]);	// pick up the advisor from the line before
	if ($this->debug) print "DEBUG: found advisor: (2)<pre>$advisor</pre>";
      } elseif (preg_match("/^ *Committee/", $line) || preg_match("/Reader/", $line)) {	
	array_push($this->fields['committee'], $this->clean_name($lines[$i-1]));// pick up the committee from the line before
	if ($this->debug) print "DEBUG: found committee member:<pre>" . $this->clean_name($lines[$i-1]) . "</pre>";
      } elseif (preg_match("/^(.+), Committee/", $line, $matches)) {
	// looking for name, Committee Member on a single line
	array_push($this->fields['committee'], $this->clean_name($matches[1])); 
      }
    
    }
  }


  // minimal cleaning for advisor/committee names - remove dr.,  ph.d.
  private function clean_name ($name) {
    $name = str_replace("Dr. ", "", $name);
    $name = str_replace(", Ph.D.", "", $name);
    $name = trim($name);
    return $name;
  }





}

?>
