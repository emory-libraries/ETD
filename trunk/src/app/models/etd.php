<?php

require_once("api/fedora.php");
require_once("api/risearch.php");
require_once("models/foxml.php");

require_once("persis.php");

require_once("etdInterface.php");
// etd datastreams
require_once("etd_mods.php");
require_once("etd_html.php");
require_once("premis.php");
require_once("policy.php");

// related objects
require_once("etdfile.php");
require_once("user.php");

// etd object for etd08
class etd extends foxml implements etdInterface {

  // associated files
  public $pdfs;
  public $originals;
  public $supplements;
  public $authorInfo;	// user object

  
  
  public function __construct($arg = null) {
    parent::__construct($arg);

    
    if ($this->init_mode == "pid") {
      // anything here?
    } elseif ($this->init_mode == "dom") {
      // anything here?
    } elseif ($this->init_mode == "template") {
      // new etd objects
      $this->cmodel = "etd";
      // all new etds should start out as drafts
      $this->rels_ext->addRelation("rel:etdStatus", "draft");
    }


    
    // FIXME: this part (at least) should be lazy-init - slows down the browse listing significantly
    $this->pdfs = array();
    $files = array("pdfs" => "PDF",
		   "originals" => "Original",
		   "supplements" => "Supplement");
    foreach ($files as $var => $type) {
      $this->$var = array();
      // FIXME: why not just use RELS-EXT ?
      $pids = $this->findFiles($type);
      foreach ($pids as $pid) {
	if ($pid) {
	  try {
	    $this->{$var}[] = new etd_file($pid, $this);
	  } catch (FedoraAccessDenied $e) {
	    // should this warn or not? regular users won't want to see it...
	    trigger_error("Access denied to $type file $pid", E_USER_NOTICE);
	  }
	}
      }
    }
    // fixme: better to use risearch or rels-ext for related objects?

    // fixme: need to handle permissions better - may not have permission to see all related objects
    
    // FIXME: this only works if user has permissions on rels-ext...
    if ($this->init_mode == "pid") {
      try {
	$this->rels_ext != null;
      } catch  (FedoraAccessDenied $e) {
	// if the current user doesn't have access to RELS-EXT, they don't have full access to this object
	throw new FoxmlException("Access Denied to " . $this->pid); 
      }
      if (isset($this->rels_ext->hasAuthorInfo)) {
	try {
	  $authorpid = $this->rels_ext->hasAuthorInfo;
	  $this->authorInfo = new user($authorpid);
	} catch (FedoraAccessDenied $e) {
	  print "Fedora Access Denied: " . $e->getMessage() . "<br>\n";
	  // most users will NOT have access to the author information
	  trigger_error("Access denied to author info $pid", E_USER_NOTICE);
	}
      }	// end if authorInfo
    }
    
  }


  // add datastreams here 
  protected function configure() {
    parent::configure();

    // add mappings for xmlobject
    $this->xmlconfig["html"] = array("xpath" => "//foxml:xmlContent/html",
				     "class_name" => "etd_html", "dsID" => "XHTML");
    $this->addNamespace("mods", "http://www.loc.gov/mods/v3");
    $this->xmlconfig["mods"] = array("xpath" => "//foxml:xmlContent/mods:mods",
				     "class_name" => "etd_mods", "dsID" => "MODS");

    $this->addNamespace("premis", "http://www.loc.gov/standards/premis/v1");
    $this->xmlconfig["premis"] = array("xpath" => "//foxml:xmlContent/premis:premis",
				       "class_name" => "premis", "dsID" => "PREMIS");
    // override default rels-ext class
    $this->xmlconfig["rels_ext"]["class_name"] = "etd_rels";

    // xacml policy
    $this->addNamespace("x", "urn:oasis:names:tc:xacml:1.0:policy");
    $this->xmlconfig["policy"] = array("xpath" => "//foxml:xmlContent/x:Policy",
				       "class_name" => "XacmlPolicy", "dsID" => "POLICY");
  }


  /**
   *  determine a user's role in relation to this ETD
   */
  public function getUserRole(esdPerson $user) {
    if ($user->netid == $this->rels_ext->author)
      return "author";
    elseif ($this->rels_ext->committee instanceof DOMElementArray
	    && $this->rels_ext->committee->includes($user->netid))	
      return "committee";
    elseif ($user->role == "staff" && $this->mods->department && ($user->department == $this->mods->department))
      return "departmental staff";
    else
      return $user->role;
  }


  public function setStatus($new_status) {
    $old_status = $this->rels_ext->status;
    if ($new_status == $old_status) return;	// do nothing - no change

    // when leaving certain statuses, certain rules need to go away
    switch ($old_status) {
    case "published":	$this->removePolicyRule("published"); break;
    case "draft":	 $this->removePolicyRule("draft");    break;
    }
    
    // certain policy rules need to change when status changes
    switch ($new_status) {
    case "draft":	// add draft rule & allow author to edit
      $this->addPolicyRule("draft");
      break;
    case "published":
      $this->addPolicyRule("published");
      // FIXME: how to handle embargos/publication ?
      // by default, set the embargo expiration to today here?
      // ... $this->policy->embargoed->condition->date = date("Y-m-d");
      // fixme: split out embargo rule for etd file objects only ?
      break;
    }


    // for all - store the status in rels-ext
    $this->rels_ext->status = $new_status; 
  }

  /* convenience functions to add and remove policies in etd and related objects all at once
      note: the only rule that may be added and removed to original/archive copy is draft
   */
  
  private function addPolicyRule($name) {
    $objects = array_merge(array($this), $this->pdfs, $this->supplements);
    if ($name == "draft") $objects = array_merge($objects, $this->originals);
    foreach ($objects as $obj) {
      if (!isset($obj->policy->{$name}))	// should not be the case, but doesn't hurt to check
	$obj->policy->addRule($name);
      // special case - owner needs to be specified for draft rule
      if ($name == "draft") $obj->policy->draft->condition->user = $this->owner;
    }
  }

  private function removePolicyRule($name) {
    $objects = array_merge(array($this), $this->pdfs, $this->supplements);
    if ($name == "draft") $objects = array_merge($objects, $this->originals);
    foreach ($objects as $obj) {
      if (isset($obj->policy->{$name}))	$obj->policy->removeRule($name);
    }
  }

  
  public function addPdf(etd_file $etdfile) { return $this->addFile($etdfile, "PDF");  }
  public function addOriginal(etd_file $etdfile) { return $this->addFile($etdfile, "Original");  }
  public function addSupplement(etd_file $etdfile) { return $this->addFile($etdfile, "Supplement");  }
  
  // set the relations between this etd and a file object (original, pdf, supplement)
  private function addFile(etd_file $etdfile,  $relation) {
    // etd is related to file
    $this->rels_ext->addRelationToResource("rel:has{$relation}", $etdfile->pid);

    // fixme: need better error handling here...
    return $this->save("adding relation to file ($relation) " . $etdfile->pid);
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

    case "pid":
      if (isset($this->premis)) {
	// store pid in premis object; used for basis of event identifiers
	$this->premis->object->identifier->value = $value;
      }

      // set pid in policy - used to set what resource policy applies to
      if ($this->policy) {
	$this->policy->pid = $value;
	$this->policy->policyid = str_replace(":", "-", $value);
      }
      
      // base foxml class also stores pid value in multiple places
      parent::__set($name, $value);
      break;
    case "owner":
      // add author's username to the appropriate rules
      if (isset($this->policy) && isset($this->policy->draft))
	$this->policy->draft->condition->user = $value;

      // store author's username in rels-ext as author
      if (isset($this->rels_ext)) {
	if (isset($this->rels_ext->author)) $this->rels_ext->author = $value;
	else $this->rels_ext->addRelation("rel:author", $value);
      }
      // set ownerId property
      parent::__set($name, $value);
      break;
      
    case "cmodel":	
      if (isset($this->premis)) {
	// use content model name for premis object category
	$this->premis->object->category = $value;
      }
      parent::__set($name, $value);
      break;
	    
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
      // use the html contents value, since it is already slightly cleaned up
      $this->mods->tableOfContents = etd_html::formattedTOCtoText($this->html->contents);
      break;

    case "department":
      // set author's department in mods & in view policy
      $this->mods->department = $value;
      if (isset($this->policy->view))
	$this->policy->view->condition->department = $value;
      break;
    default:
      parent::__set($name, $value);
    }
  }


  // set advisor in mods metadata but *also* set in view policy
  public function setAdvisor($id) {
    // if a different advisor was set before, remove from policy
    if ($this->mods->advisor->id)
      $this->policy->view->condition->removeUser($this->mods->advisor->id);
    // store in mods    
    $this->mods->setAdvisor($id);

    // store relation in rels-ext
    if (isset($this->rels_ext->advisor)) $this->rels_ext->advisor = $id;
    else $this->rels_ext->addRelation("rel:advisor", $id);

    // add to 'view' policy rule
    $this->policy->view->condition->addUser($id);
  }

  // similar logic to advisor above
  public function setCommittee(array $ids) {
    // fixme: clear any old committee members from policy ?
    foreach ($this->mods->committee as $cm) {
      if ($cm->id)
	$this->policy->view->condition->removeUser($cm->id);
    }

    $this->mods->setCommittee($ids);
    $this->rels_ext->setCommittee($ids);
    
    foreach ($ids as $id) 
      $this->policy->view->condition->addUser($id);
  }


  // type should be: Original, PDF, Supplement
  protected function findFiles($type) {
    // prefix pid with info:fedora/ (required format for risearch)
    $pid = $this->pid;
    if (strpos($pid, "info:fedora/") === false)
      $pid = "info:fedora/$pid";
    
    $query = 'select $etdfile  from <#ri>
    	where  <' . $pid . '> <fedora-rels-ext:has' . $type . '> $etdfile';
    // fixme: order by sequenceNumber

    $filelist = risearch::query($query);

    $files = array();
    foreach ($filelist->results->result as $result ) {
      $pid = $result->etdfile["uri"];
      $pid = str_replace("info:fedora/", "", $pid);
      $files[] = $pid;
    }

    return $files;
  }


  
  public function readyToSubmit() {
    if (! $this->mods->readyToSubmit()) return false;
    if (! $this->hasPDF()) return false;
    if (! $this->hasOriginal()) return false;
    if (!isset($this->authorInfo) || !$this->authorInfo->readyToSubmit()) return false;
    return true;
  }

  // should have at least one pdf
  public function hasPDF() {
    // what is the best way to check this? use rels-ext?
    return (count($this->pdfs) > 0);
  }

  public function hasOriginal() {
    return (count($this->originals) > 0);
  }

  
  /**  override default foxml ingest function to use arks for object pids
   *
   */
  public function ingest($message ) {
    /* Note: it would be good to validate the XACML policy here,
       but the policy->isValid can only validate the entire record (since it is all in one DOM)
     */
    $persis = new etd_persis();
    
    // FIXME: use view/controller to build this url?
    $ark = $persis->generateArk("http://etd/view/pid/emory:{%PID%}", $this->label);
    $pid = $persis->pidfromArk($ark);

    $this->pid = $pid;
    $this->mods->ark = $ark;

    print "<pre>" . htmlentities($this->saveXML()) . "</pre>";
    return $this->fedora->ingest($this->saveXML(), $message);
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
      try {
	$etds[] = new etd($pid);
      } catch (FedoraObjectNotFound $e) {
	trigger_error("Record not found: $pid", E_USER_WARNING);
      }
    }
    return $etds;
  }

  public static function findbyAuthor($username) {
    // FIXME: need sanity checking on username
    $query = 'select $etd from <#ri>
	      where $etd <fedora-rels-ext:author> \'' . $username . '\'
	      and $etd <fedora-model:contentModel> \'etd\'';
    $etdlist = risearch::query($query);
    $etds = array();
    foreach($etdlist->results->result as $result) {
      $pid = (string)$result->etd["uri"];
      $pid = str_replace("info:fedora/", "", $pid);
      $etds[] = new etd($pid);
    }
    return $etds;
  }


  
  public function pid() { return $this->pid; }
  public function status() { return $this->rels_ext->status; }
  public function title() { return $this->html->title; }	// how to know which?
  public function author() { return $this->mods->author->full; }
  public function program() { return $this->mods->department; }
  public function subfield() { return isset($this->mods->subfield) ? $this->mods->subfield : ""; }
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



  // for Zend ACL Resource

  public function getResourceId() {
    if ($this->status() != "") {
      return $this->status() . " etd";
    } else {
      return "etd";
    }
  }
  
}




