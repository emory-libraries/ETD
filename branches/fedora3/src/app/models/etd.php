<?php

require_once("api/risearch.php");
require_once("models/foxml.php");

require_once("etdInterface.php");
// etd datastreams
require_once("etd_mods.php");
require_once("etd_html.php");
require_once("etd_rels.php");
require_once("premis.php");
require_once("policy.php");

// related objects
require_once("etdfile.php");
require_once("user.php");

require_once("solrEtd.php");

// email notifier
require_once("etd_notifier.php");

// etd object for etd08
class etd extends foxml implements etdInterface {

  // associated files
  /*  public $pdfs;
  public $originals;
  public $supplements;
  public $authorInfo;	// user object
  */

  /**
   * @var float $relevance score when found via Solr
   */
  public $relevance;

  /**
   * @var string $acl_id basic ACL resource id (has subresource variants that inherit)
   */
  protected $acl_id;


  /**
   * @var string generic name for admin/agency responsible for this type of etd
   */
  public $admin_agent;
  
  
  public function __construct($arg = null) {      
    parent::__construct($arg);

    if ($this->init_mode == "pid") {
      // make sure user has access to this object by accessing RELS datastream
      // - if not, this will throw error on initialization, when it is easier to catch
      $this->rels_ext;		
            
      // anything else here?
    //if ($this->cmodel != "etd") {
    // FIXME: add a check that hasModel rel is present...?
        //throw new FoxmlBadContentModel("$arg is not an etd");
    } elseif ($this->init_mode == "dom") {    
      // anything here?
    } elseif ($this->init_mode == "template") {
        // new etd objects - add relation to contentModel object
        if (Zend_Registry::isRegistered("config")) {
            $config = Zend_Registry::get("config");
            $this->rels_ext->addContentModel($config->contentModels->etd);
        } else {
            trigger_error("Config is not in registry, cannot retrieve contentModel for etd");
        }
     
      // all new etds should start out as drafts
      $this->rels_ext->addRelation("rel:etdStatus", "draft");
    }

    // base ACL resource id is etd
    $this->acl_id = "etd";

    // for default etds, admin agent is grad school
    $this->admin_agent = "the Graduate School";
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

    // relations to other objects
    $this->relconfig["pdfs"] = array("relation" => "hasPDF", "is_series" => true, "class_name" => "etd_file",
				     "sort" => "sort_etdfiles");
    $this->relconfig["originals"] = array("relation" => "hasOriginal", "is_series" => true,
					  "class_name" => "etd_file", "sort" => "sort_etdfiles");
    $this->relconfig["supplements"] = array("relation" => "hasSupplement", "is_series" => true,
					    "class_name" => "etd_file", "sort" => "sort_etdfiles");
    $this->relconfig["authorInfo"] = array("relation" => "hasAuthorInfo", "class_name" => "user");
  }
  


  /**
   *  determine a user's role in relation to this ETD (for access controls)
   */
  public function getUserRole(esdPerson $user = null) {
    if (is_null($user)) return "guest";

    // superuser should supercede all other roles
    if ($user->role == "superuser") return $user->role;
    
    if ($user->netid == $this->rels_ext->author)
      return "author";
    elseif ($this->rels_ext->committee instanceof DOMElementArray
	    && $this->rels_ext->committee->includes($user->netid))	
      return "committee";
    elseif ($user->isCoordinator($this->mods->department))
      return "program coordinator";
    else
      return $user->role;
  }

  public function setStatus($new_status) {
    $old_status = $this->rels_ext->status;
    //    if ($new_status == $old_status) return;	// do nothing - no change (?)

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

  /**
   * set OAI identifier in RELS-EXT based on full ARK 
   */
  public function setOAIidentifier() {
    if (!isset($this->mods->ark) || $this->mods->ark == "")
        throw new Exception("Cannot set OAI identifier without ARK.");
    // use persis to parse the ark into its components
    $persis = new Emory_Service_Persis(Zend_Registry::get('persis-config'));
    list($nma, $naan, $noid) = $persis->parseArk($this->mods->ark);
    // set OAI id based on ark
    $this->rels_ext->setOAIidentifier("oai:ark:/" . $naan . "/" . $noid);
  }

  /* convenience functions to add and remove policies in etd and related objects all at once
      note: the only rule that may be added and removed to original/archive copy is draft
   */
  public function addPolicyRule($name) {
    $objects = array_merge(array($this), $this->pdfs, $this->supplements);
    if ($name == "draft") $objects = array_merge($objects, $this->originals);
    foreach ($objects as $obj) {
      if (!isset($obj->policy)) continue;	// should only be the case for test objects; give notice/warn ?
      if (!isset($obj->policy->{$name}))	// should not be the case, but doesn't hurt to check
	$obj->policy->addRule($name);

      // special case - owner needs to be specified for draft rule
      if ($name == "draft") {
	if (isset($this->owner) &&  $this->owner != "") {
	  // NOTE: owner is *not* retrieved from Fedora when initializing by pid
	  $owner = $this->owner;
	} else {
	  // fall-back / alternate way to get author's netid
	  $owner = $this->rels_ext->author;
	}

	$obj->policy->draft->condition->user = $owner;
      }

      // another special case: if embargo end is already set, put that date in publish policy as well
      if ($name == "published" && isset($this->mods->embargo_end) && $this->mods->embargo_end
	  && isset($obj->policy->published->condition) && isset($obj->policy->published->condition->embargo_end))
	// (only in etdfile objects)
	$obj->policy->published->condition->embargo_end = $this->mods->embargo_end;
    }
  }

  public function removePolicyRule($name) {
    $objects = array_merge(array($this), $this->pdfs, $this->supplements, $this->originals);
    foreach ($objects as $obj) {
      if (isset($obj->policy->{$name}))	$obj->policy->removeRule($name);
    }
  }

  
  public function addPdf(etd_file $etdfile) {
    // if value is already set, add to the total (multiple pdfs)
    if (isset($etdfile->dc->pages) && isset($this->mods->pages)) 
      $this->mods->pages = (int)$this->mods->pages + (int)$etdfile->dc->pages;
    else
      $this->mods->pages = $etdfile->dc->pages;

    return $this->addFile($etdfile, "PDF");
  }
  public function addOriginal(etd_file $etdfile) {
    // original documents should not have 'view' rule; this is the best place to remove 
    if (isset($etdfile->policy->view)) $etdfile->policy->removeRule("view");
    return $this->addFile($etdfile, "Original");
  }
  public function addSupplement(etd_file $etdfile) { return $this->addFile($etdfile, "Supplement");  }
  
  // set the relations between this etd and a file object (original, pdf, supplement)
  private function addFile(etd_file $etdfile,  $relation) {
    switch ($relation) {
    case "PDF": 
    case "Original":
    case "Supplement":
      $rel_name = strtolower($relation) . "s";	//pdfs, originals, supplements
      break;
    }
    $count = count($this->{$rel_name});
    
    // if there is already a file of this type, set the number accordingly
    if ($count) {
      $etdfile->rels_ext->sequence = $count + 1;
    }

    // update etdfile xacml with any information already added to etd
    //  - for all files other than the Original,
    // committee chairs & committee members should always be able to view
    if ($relation != "Original") {
      // add netids for advisor and committee if they have already been set
      foreach (array_merge($this->mods->chair, $this->mods->committee) as $committee) {
	if ($committee->id != "")
	  $etdfile->policy->view->condition->addUser($committee->id);
      }
      // if department has already been set, add to the view rule for departmental staff
      if ($this->mods->department != "")
	$etdfile->policy->view->condition->department = $this->mods->department;
    }


    // etd is related to file
    $this->rels_ext->addRelationToResource("rel:has{$relation}", $etdfile->pid);

    /** add the etd file to the appropriate related objects array in this object
        this will result in the changed etdfile being saved when and if the etd is saved
     */
    if (!isset($this->related_objects[$rel_name])) $this->related_objects[$rel_name] = array();
    $this->related_objects[$rel_name][] = $etdfile;

    // NOTE: record should be saved after this; not saving here for consistency & easier testing
  }


  // remove relation to a file (when file is purged or deleted)
  public function removeFile(etd_file $etdfile) {
    switch ($etdfile->type) {
    case "pdf":
      // remove pdf from the total number of pages
      if (isset($etdfile->dc->pages) && isset($this->mods->pages)) 
	$this->mods->pages = $this->mods->pages - $etdfile->dc->pages;
      $relation = "hasPDF";
      break;
    case "original": $relation = "hasOriginal"; break;
    case "supplement"; $relation = "hasSupplement"; break;
    }
    $this->rels_ext->removeRelation("rel:$relation", $etdfile->pid);
  }

  /**
   * get an array of all file objects, no matter the type
   * @return array of etdFile objects
   */
  public function getAllFiles() {
    return array_merge($this->pdfs,
		       array_merge($this->originals, $this->supplements));
  }


  public function save($message) {
    // cascade save to related etdfile objects
    
    // since changes on etd can cause changes on etdfiles (e.g., xacml)
    // should not be a big performance hit, because only changed datastreams are actually saved
    $etdfiles = array_merge($this->pdfs, $this->originals, $this->supplements);
    foreach ($etdfiles as $file) {
      $result = $file->save($message);	// FIXME: add a note that it was saved with/by etd?
      // FIXME2: how to capture/return error messages here?
    }

    // update Dublin Core before saving in case MODS has changed
    $this->updateDC();
    return parent::save($message);
  }
  
  // synchronize Dublin Core with MODS (master metadata)
  public function updateDC() {
    $this->dc->title = $this->mods->title;
    $this->dc->type = $this->mods->genre;
    $this->dc->date = $this->mods->date;
    $this->dc->language = $this->mods->language->text;

    // in some cases, a properly escaped ampersand in the MODS shows
    // up not escaped in the DC, even though unit test indicates it does not
    // -- circumvent this problem whenever possible
    if ($this->dc->description != $this->mods->abstract)
      $this->dc->description = $this->mods->abstract;

    // author
    $this->dc->creator = $this->mods->author->full;

    // committee chair(s)
    $chairs = array();
    foreach ($this->mods->chair as $chair) {
      $chairs[] = $chair->full;
    }
    $this->dc->setContributors($chairs);

    // subjects : research fields and keywords
    $subjects = array_merge($this->researchfields(), $this->keywords());
    $this->dc->setSubjects($subjects);

    // ark for this object
    if (isset($this->mods->ark)) $this->dc->setArk($this->mods->ark);

    // related objects : arks for public etd files (pdfs and supplements)
    $relations = array();
    foreach (array_merge($this->pdfs, $this->supplements) as $file) {
      if (isset($file->dc->ark)) $relations[] = $file->dc->ark;
    }
    $this->dc->setRelations($relations);


    // FIXME: what else should be included in this update?
    // - number of pages?
  }

  
  
  // handle special values
  public function __set($name, $value) {
    switch ($name) {

    case "pid":
      if (isset($this->premis)) {
	// store pid in premis object; used for basis of event identifiers
	$this->premis->object->identifier->value = $value;
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
      $value = etd_html::removeTags($value); // necessary?
      // set title in multiple places (record label, dublin core, mods)
      $this->label = $value;
      $this->dc->title = $value;
      $this->mods->title = $value;
      break;
    case "abstract":
      // remove unwanted tags
      $this->html->abstract = $value;
      // clean & remove all tags
      $value = etd_html::removeTags($value);
      $this->mods->abstract = $value;
      $this->dc->description = $value;
      break;
    case "contents":
      $this->html->contents = $value;
      // use the html contents value, since it is already slightly cleaned up
      $this->mods->tableOfContents = etd_html::formattedTOCtoText($this->html->contents);
      break;

    case "department":
      // set author's department in mods & in view policy
      $this->mods->department = $value;

      // set department on xacml policy for etd and any associated etdFiles
      $objects = array_merge(array($this), $this->pdfs, $this->supplements);
      foreach ($objects as $obj) {
	if (isset($obj->policy->view))
	  $obj->policy->view->condition->department = $value;
      }
      break;
    default:
      parent::__set($name, $value);
    }
  }

  /*
   * set committee chairs & members in MODS metadata
   * but *also* set in xacml view policy and RELS-EXT
   *
   * @param array $ids array of string OR of esdPerson objects; assumed to be all the same
   * @param string $type type of committee person: member or chair
   */
  public function setCommittee(array $ids, $type = "committee") {
    // clear any old committee members from policy 
    foreach ($this->mods->{$type} as $committee) {
      if ($committee->id)
	// remove from etd policy and from associated etd files
	foreach (array_merge(array($this), $this->pdfs, $this->supplements) as $obj) {
	  $obj->policy->view->condition->removeUser($committee->id);
	}
    }

    // store new committee in mods, rels-ext
    if ($ids[0] instanceof esdPerson)		// already esdPerson objects - no lookup required
      $this->mods->setCommitteeFromPersons($ids, $type);
    else	       // set in mods by netid
      $this->mods->setCommittee($ids, $type);


    // generate an array of netid strings
    $netids = array();
    if ($ids[0] instanceof esdPerson) {
      foreach ($ids as $person) $netids[] = $person->netid;
    } else {
      $netids = $ids;
    }
    $this->rels_ext->setCommittee($netids);

    foreach ($netids as $id) {
      // add to view policy rule for etd and associated files
      foreach (array_merge(array($this), $this->pdfs, $this->supplements) as $obj) {
        $obj->policy->view->condition->addUser($id);
      }
    }
  }

  // is this record embargoed?
  public function isEmbargoed() {
    if ($this->mods->embargo_end)
      return (strtotime($this->mods->embargo_end, 0) > time());
    else	// no embargo date defined - not (yet) embargoed
      return false;
  }

  /**
   * check if a field is required
   * 
   * @param string $field field name
   * @return boolean
   */
  public function isRequired($field) {
    // for now, all required fields are either in the mods or user contact info,
    // so a field is required here if it is required by either of them
    if (isset($this->authorInfo))
      return ($this->mods->isRequired($field) || $this->authorInfo->isRequired($field));
    else
      return $this->mods->isRequired($field);
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

  // easy way to check if this object (or inherited object) is honors or not
  public function isHonors() {
    return ($this instanceof honors_etd);
  }

  
  /**  override default foxml ingest function to use arks for object pids
   *
   */
  public function ingest($message ) {
    /* Note: it would be good to validate the XACML policy here,
       but the policy->isValid can only validate the entire record (since it is all in one DOM).
     */

    // could generate service unavailable exception - should be caught in the controller
    $persis = new Emory_Service_Persis(Zend_Registry::get('persis-config'));
    
    // FIXME: use view/controller to build this url?
    $ark = $persis->generateArk("http://etd.library.emory.edu/view/record/pid/emory:{%PID%}", $this->label);
    $pid = $persis->pidfromArk($ark);

    // FIXME: error handling? make sure pid is successfully generated?

    $this->pid = $pid;
    $this->mods->ark = $ark;

    return $this->fedora->ingest($this->saveXML(), $message);
  }


  /* make a note in record history that graduation has been confirmed */
  public function confirm_graduation() {
    $this->premis->addEvent("administrative",
			   "Graduation Confirmed by ETD system",
			   "success",  array("software", "etd system"));
  }
  

  /* publish a record - does several things:
   *  - set publication date (mods:dateIssued)
   *  - calculate embargo end date (if there is an embargo)
   *  - set status to published, and load appropriate policies
   *
   * @param string $publish_date official publish date for the record; should be in YYYY-MM-DD format
   * @param string $date optional 'real' publish date (defaults to today)- used for embargo calculations, etc
   *
   * Clarification: the official publish date is a set date for each
   * semester so that all records, no matter when they are actually
   * published (this could vary by a few days or more), will have the
   * same publication date.
   * 
   * The "real" publication date is the actual date when the Registrar
   * confirms graduation and the record is released (e.g., this could
   * be May 5 when the official publish date is May 31).  The real
   * date must be used everywhere else (e.g., calculating embargo end
   * date).  This is particularly important for records with no
   * embargo, so that they are not restricted between the real
   * publication date and the official one.
   *
   * Note that the "real" publication date can be overridden, e.g. in
   * case a record is missed and needs to be published retroactively.
   * Should mostly be used only for testing.
   * 
   */
  public function publish($publish_date, $date = null) {
    // set publication date (date issued)
    $this->mods->originInfo->issued = $publish_date;

    // set date if not specified
    if (!$date) $date = date("Y-m-d");	// defaults to today - this is what should be used in most cases

    // unix timestamp representation of "actual" publication date
    $datetime = strtotime($date, 0);
    
    // calculate embargo end date
    // calculation is based on duration (mods embargo field)
    // in reference to 'real' publication date, e.g. today + 6 months
    $end_date = strtotime($this->mods->embargo, $datetime);
    // sanity check on calculated end date : should be greater than or equal to publication date
    if ($end_date <  $datetime) {
      throw new XmlObjectException("Calculated embargo date does not look correct (timestamp:$end_date, " .
				   date("Y-m-d", $end_date) . ")");
    }

    $this->mods->embargo_end = date("Y-m-d", $end_date);
    
    // sets status, add appropriate policies to etd & related files, and puts embargo end date in policies
    $this->setStatus("published");
    // record publication in history
    $this->premis->addEvent("status change",
			    "Published by ETD system",
			    "success",  array("software", "etd system"));
  }

  
  /* make a note in record history that record has been sent to ProQuest */
  public function sent_to_proquest() {
    $this->premis->addEvent("administrative",
			   "Submitted to Proquest by ETD system",
			   "success",  array("software", "etd system"));
  }


  public function embargo_expiration_notice() {
    //add an admin note that embargo expiration was sent
    $this->mods->addNote("sent " . date("Y-m-d"), "admin", "embargo_expiration_notice");
    // log the event in the record history
    $this->premis->addEvent("notice",
			    "Embargo Expiration 60-Day Notification sent by ETD system",
			    "success",  array("software", "etd system"));
  }

  /**
   * remove embargo notice stuff
   * use this when embargo expiration script mistakenly sends emails to everyone
   * (hopefully never again)
   */
  public function undoEmbargoExpirationNotice() {
    // remove administrative note that embargo notice was sent from mods
    $this->mods->remove("embargo_notice");

    // remove from erroneous 60-day notification event from premis event history
    foreach ($this->premis->event as $event) {
      if ($event->type == "notice" && strpos($event->detail, "60-Day Notification sent")) {
	$this->premis->removeEvent($event->identifier->value);
      }
    }
  }


  public function abstract_word_count() {
    // return word count on text-only version, not formatted html version
    return str_word_count($this->mods->abstract);
  }


  public function pid() { return $this->pid; }
  public function status() { return isset($this->rels_ext->status) ? $this->rels_ext->status : ""; }
  public function title() { return $this->html->title; }	// how to know which?
  public function author() { return $this->mods->author->full; }
  public function program() { return $this->mods->department; }
  public function program_id() {
    return isset($this->rels_ext->program) ? $this->rels_ext->program : null;
  }
  public function subfield() { return isset($this->mods->subfield) ? $this->mods->subfield : ""; }
  public function subfield_id() {
    return isset($this->rels_ext->subfield) ? $this->rels_ext->subfield : null;
  }
  public function chair() {
    $chairs = array();
    foreach ($this->mods->chair as $c) {
      array_push($chairs, $c->full);
    }
    return $chairs;
  }
  // return hash of committe chair name and affiliation (if any)
  public function chair_with_affiliation() {
    $chairs = array();
    foreach ($this->mods->chair as $c) {
      if (isset($c->affiliation)) 
	$chairs[$c->full] = $c->affiliation;
      else
	$chairs[$c->full] = "";
    }
    return $chairs;
  }
  // return hash of committe member name and affiliation (if any)
  public function committee_with_affiliation() {
    $committee = array();
    foreach ($this->mods->committee as $c) {
      if (isset($c->affiliation)) 
	$committee[$c->full] = $c->affiliation;
      else
	$committee[$c->full] = "";
    }
    return $committee;
  }




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
  public function year() {
    if ($date = $this->mods->date)		// if date is set
      return date("Y", strtotime($date, 0));		// convert date to year only
  }
  public function pubdate() { return $this->mods->originInfo->issued; }
  public function _abstract() { return $this->mods->abstract; }
  public function tableOfContents() { return $this->mods->tableOfContents; }
  public function num_pages() { return isset($this->mods->pages) ? $this->mods->pages : ""; }
  
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

  public function ark() {
    return $this->mods->ark;
  }



  // for Zend ACL Resource
  public function getResourceId() {
    if ($this->status() != "") {
      return $this->status() . " " . $this->acl_id;
    } else {
      return $this->acl_id;
    }
  }

  
}





// simple function to sort etdfiles based on sequence number  (used when foxml class initializes them)
function sort_etdfiles(etd_file $a, etd_file $b) {
    if ($a->rels_ext->sequence == $b->rels_ext->sequence) return 0;
    return ($a->rels_ext->sequence < $b->rels_ext->sequence) ? -1 : 1;
}
