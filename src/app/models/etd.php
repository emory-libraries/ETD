<?php

require_once("api/fedora.php");
require_once("api/risearch.php");
require_once("models/foxml.php");

require_once("abstractEtd.php");
require_once("etd_mods.php");
require_once("etd_html.php");
require_once("etdfile.php");

// etd object for etd08
class etd extends foxml implements abstractEtd {
  //  public $mods;
  //  public $html;
  //  public $rels;


  // associated files
  public $pdfs;
  public $originals;
  public $supplements;
    

  
  public function __construct($arg = null) {
    parent::__construct($arg);
    
    //    $this->owner = "rsutton";	// just to test

    if ($this->init_mode == "pid") {
      $dom = new DOMDocument();
      $dom->loadXML(fedora::getDatastream($arg, "MODS"));
      $this->map{"mods"} = new etd_mods($dom);

      $dom = new DOMDocument();
      $dom->loadXML(fedora::getDatastream($arg, "XHTML"));
      $this->map{"html"} = new etd_html($dom);

    } else {
      $this->cmodel = "etd";
    }


    // FIXME: this part (at least) should be lazy-init - slows down the browse listing significantly
    $this->pdfs = array();
    $files = array("pdfs" => "PDF",
		   "originals" => "Original",
		   "supplements" => "Supplement");
    foreach ($files as $var => $type) {
      $this->$var = array();
      $pids = $this->findFiles($type);
      foreach ($pids as $pid) $this->{$var}[] = new etd_file($pid);
    }
  }


  // add datastreams here 
  protected function configure() {
    parent::configure();
    // add to template for new foxml
    $this->datastreams[] = "etd_html";
    $this->datastreams[] = "etd_mods";

    // add mappings for xmlobject
    $this->xmlconfig["html"] = array("xpath" => "//foxml:xmlContent/html",
				     "class_name" => "etd_html");
    $this->addNamespace("mods", "http://www.loc.gov/mods/v3");
    $this->xmlconfig["mods"] = array("xpath" => "//foxml:xmlContent/mods:mods",
				     "class_name" => "etd_mods");
  }


  /* FIXME: systematic way to link mods fields to dublin core?  update
     them all at once when saving?

     ... one idea: have an 'update' function that can be overridden
     and is called before any save action (save xml, save xml to file,
     save to fedora)
  */
  
  // handle special values
  public function __set($name, $value) {
    switch ($name) {
      // store formatted version in html, plain-text version in mods
    case "title":
      // remove tags that are not wanted even in html 
      $this->html->title = $value;

      // clean entities & remove all tags before saving to mods/dc
      $value = etd_html::cleanTags($value);
      $value = etd_html::removeTags($value); // necessary?
      // set title in multiple places (record label, dublin core, mods)
      $this->label = $this->dc->title = $this->mods->title = $value;
      break;
    case "abstract":
      // remove unwanted tags
      $this->html->abstract = $value;
      // clean & remove all tags
      $value = etd_html::cleanTags($value);
      $value = etd_html::removeTags($value);
      $this->dc->description = $this->mods->abstract = $value;
      break;
    case "contents":
      $this->html->contents = $value;
      $this->mods->tableOfContents = etd_html::formattedTOCtoText($value);
      
      /*      // remove tags, convert breaks to --
      $toc_text = etd_html::cleanTags($value, true);
      $unfiltered_toc_lines = split('<br/>', $toc_text);
      $toc_lines = array();
      foreach ($unfiltered_toc_lines as $line) {
	// filter out blank lines
	if (preg_match("/^\s*$/", $line)) continue;
	array_push($toc_lines, $line);
      }
      $toc_text = implode("--", $toc_lines);
      $this->mods->tableOfContents = $toc_text;*/
      break;
    default:
      parent::__set($name, $value);
    }
  }



  // type should be: Original, PDF, Supplement
  protected function findFiles($type) {
    // prefix pid with info:fedora/ (required format for risearch)
    $pid = $this->pid;
    if (strpos($pid, "info:fedora/") === false)
      $pid = "info:fedora/$pid";
    
    $query = 'select $etdfile  from <#ri>
	where  <' . $pid . '> <fedora-rels-ext:has' . $type . '> $etdfile';

    $filelist = risearch::query($query);

    $files = array();
    foreach ($filelist->results->result as $result ) {
      $pid = $result->etdfile["uri"];
      $pid = str_replace("info:fedora/", "", $pid);
      $files[] = $pid;
    }

    return $files;
  }
  
  
  public static function totals_by_status() {

    $countquery = 'select $status count( select $etd from <#ri>
		   where  $etd <fedora-rels-ext:etdStatus> $status ) 
		   from <#ri>
		   where $etd <fedora-rels-ext:etdStatus> $status';

    $countlist = risearch::query($countquery);
    
    // fixme: use config variables, itql/risearch class?
    //    $countlist = simplexml_load_file("http://wilson:6080/fedora/risearch?type=tuples&lang=iTQL&format=Sparql&query=" . urlencode($countquery));
    
    // If there are no records of a particular status, no count will
    // be returned-- so initialize all to zero
    $status = array('published' => 0,
		    'approved'  => 0,
		    'reviewed'  => 0,
		    'submitted' => 0,
		    'draft' 	=> 0);
    foreach ($countlist->results->result as $result) {
      $status[(string)$result->status] = (int)$result->k0;
    }

    return $status;
  }



  // find etds by status
  public static function findbyStatus($status) {

    $query = 'select $etd  from <#ri>
	      where  $etd <fedora-rels-ext:etdStatus> \'' . $status . '\'';
    // note: MUST use single quotes and not double on status string here
    $etdlist = risearch::query($query);
    
    //    $etdlist = simplexml_load_file("http://wilson:6080/fedora/risearch?type=tuples&lang=iTQL&format=Sparql&query=" . urlencode($query));

    $etds = array();
    foreach($etdlist->results->result as $result) {
      $pid = (string)$result->etd["uri"];
      $pid = str_replace("info:fedora/", "", $pid);
      $etds[] = new etd($pid);
    }
    return $etds;
  }
    
  
  public function pid() { return $this->pid; }
  public function title() { return $this->html->title; }	// how to know which?
  public function author() { return $this->mods->author->full; }
  public function program() { return $this->mods->department; }
  public function advisor() { return $this->mods->advisor->full; }

  // note: doesn't handle non-emory committee members
  public function committee() {
    $committee = array();
    foreach ($this->mods->committee as $cm) {
      array_push($committee, $cm->full);
    }
    return $committee;
  }
			  // how to handle non-emory committee?
  	// dissertation/thesis/etc
  public function document_type() { return $this->mods->genre; }
  public function language() { return $this->mods->language; }
  public function year() { return $this->mods->year; }
  public function _abstract() { return $this->mods->abstract; }
  public function tableOfContents() { return $this->mods->tableOfContents; }
  public function num_pages() { return $this->mods->num_pages; }
  
  public function keywords() {
    $keywords = array();
    for ($i = 0; $i < count($this->mods->keywords); $i++)
      array_push($keywords, $this->mods->keywords[$i]->topic);
    return $keywords;
  }
  
  public function researchfields() {
    $subjects = array();
    for ($i = 0; $i < count($this->mods->researchfields); $i++) 
      array_push($subjects, $this->mods->researchfields[$i]->topic);
    return $subjects;
  }

  
  
}




