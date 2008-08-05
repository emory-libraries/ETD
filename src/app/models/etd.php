<?php

require_once("api/fedora.php");
require_once("api/risearch.php");
require_once("models/foxml.php");

require_once("etdInterface.php");
// etd datastreams
require_once("etd_mods.php");
require_once("etd_html.php");
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
  
  
  public function __construct($arg = null) {
    parent::__construct($arg);

    if ($this->init_mode == "pid") {
      // make sure user has access to this object by accessing RELS datastream
      // - if not, this will throw error on initialization, when it is easier to catch
      $this->rels_ext;		
            
      // anything else here?
      if ($this->cmodel != "etd")
	throw new FoxmlBadContentModel("$arg is not an etd");
    } elseif ($this->init_mode == "dom") {
      // anything here?
    } elseif ($this->init_mode == "template") {
      // new etd objects
      $this->cmodel = "etd";
      // all new etds should start out as drafts
      $this->rels_ext->addRelation("rel:etdStatus", "draft");
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

  /* convenience functions to add and remove policies in etd and related objects all at once
      note: the only rule that may be added and removed to original/archive copy is draft
   */
  private function addPolicyRule($name) {
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
    $objects = array_merge(array($this), $this->pdfs, $this->supplements);
    if ($name == "draft") $objects = array_merge($objects, $this->originals);
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
  public function addOriginal(etd_file $etdfile) { return $this->addFile($etdfile, "Original");  }
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


  // remove relation to a file (when file is purged)
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

    //    risearch::flush($this->pid);
    $this->fedora->risearch->flush_next();
  }


  public function save($message) {
    // cascade save to related files
    
    // since changes on etd can cause changes on etdfiles (e.g., xacml)
    // should not be a big performance hit, because only changed datastreams are actually saved
    $etdfiles = array_merge($this->pdfs, $this->originals, $this->supplements);
    foreach ($etdfiles as $file) {
      $result = $file->save($message);	// FIXME: add a note that it was saved with/by etd?
      // FIXME2: how to capture/return error messages here?
    }

    // TODO: update DC datastream based on MODS
    return parent::save($message);
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
    $this->mods->setCommittee($ids, $type);
    $this->rels_ext->setCommittee($ids);

    foreach ($ids as $id) {
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


  public function abstract_word_count() {
    // return word count on text-only version, not formatted html version
    return str_word_count($this->mods->abstract);
  }


  /** static functions for finding (or counting) ETDs by various criteria **/
  
  public static function totals_by_status() {
    $fedora = Zend_Registry::get('fedora');
    $totals = array();
    
    // foreach status, do a triple-query and then count the number of matches
    foreach (array('published', 'approved', 'reviewed', 'submitted', 'draft') as $status) {
      $query = '* <fedora-rels-ext:etdStatus> \'' . $status . '\'';
      $rdf = $fedora->risearch->triples($query);
      $ns = $rdf->getNamespaces();
      $totals[$status] = count($rdf->children($ns['rdf']));
    }
    
    return $totals;
  }


  // find etds by status
  public static function findbyStatus($status) {
    $fedora = Zend_Registry::get('fedora');
    
    // can only use triple query with MPTstore
    $query = '* <fedora-rels-ext:etdStatus> \'' . $status . '\'';
    $rdf = $fedora->risearch->triples($query);
    
    $etds = array();
    $ns = $rdf->getNamespaces();
    $descriptions = $rdf->children($ns['rdf']);
    foreach ($descriptions as $desc) {
      $pid = $desc->attributes($ns['rdf']);	// rdf:about
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
    $fedora = Zend_Registry::get('fedora');
    
    // can only use triple query with MPTstore
    $query = '* <fedora-rels-ext:author> \'' . $username . '\'';
    $rdf = $fedora->risearch->triples($query);

    // Note: this query will match records that are not etds, and they need to be filtered somehow...
    //	(can't filter in the query itself because MPTstore only supports querying by triples)
    // 	FIXME-- would it be better to filter with another RIsearch query ? write a getContentModel function ?
    // 		(would have to be done one at a time)
    $etds = array();
    $ns = $rdf->getNamespaces();
    $descriptions = $rdf->children($ns['rdf']);
    foreach ($descriptions as $desc) {
      $pid = $desc->attributes($ns['rdf']);	// rdf:about
      $pid = str_replace("info:fedora/", "", $pid);
      try {
	$etds[] = new etd($pid);
      } catch (FedoraObjectNotFound $e) {
	trigger_error("Record not found: $pid", E_USER_WARNING);
      } catch (FoxmlBadContentModel $e) {      // filtering out non-etds here
	// shouldn't need to do anything... 
	// trigger_error("Found $pid as belonging to $username, but it is not an etd", E_USER_NOTICE);
      }
    }
    return $etds;
  }


  // find records by author with any status *except* for published
  public static function findUnpublishedByAuthor($username) {
    $username = strtolower($username);
    /*    $solr = Zend_Registry::get('solr');
    $query = "ownerId:$username NOT status:published";
    $results = $solr->query($query, null, null, null, null); 	// ... paging? .. , $start, $max - no filter query);

    $etds = array();
    foreach ($results['response']['docs'] as $result_doc) {
      array_push($etds, new etd($result_doc['PID']));
    }
    return $etds;
    */
        
    $etds = etd::findByAuthor($username);
    $unpublished = array();

    foreach ($etds as $etd) {		// have to filter here since MPTstore only supports SPO
      if ($etd->status() != 'published')
	$unpublished[] = $etd;
    }
    return $unpublished;
    
    // could switch to Solr - simple query like this: 
    // fgs.ownerId:rsutton NOT status:published
  }


  // find by department/program - used for program coordinator view
  //  program_facet:Chemistry NOT status:published
  public static function findUnpublishedByDepartment($dept, $start = null, $max = null,
						     $opts, &$total = null, &$facets = null) {
    $solr = Zend_Registry::get('solr');
    // FIXME: might make more sense to clear all filters and add relevant ones-- status, advisor, ?
    $solr->clearFacets();
    $solr->addFacets(array("status", "advisor_facet", "subfield_facet"));
    $query = "program_facet:\"$dept\" AND NOT status:published";
    // add any filters to the query
    $query .= etd::filterQuery($opts);
    
    $results = $solr->query($query, $start, $max);
    $total = $results->numFound;
    $facets = $results->facets;
    
    $etds = array();
    foreach ($results->docs as $result_doc) {
      array_push($etds, new etd($result_doc->PID));
    }
    return $etds;

  }

  // convert facets into filter query 
  public static function filterQuery($opts) {
    $filters = array();
    foreach ($opts as $filter => $value) {
      $filters[] = "$filter:($value)";
    }

    $query = implode(" AND ", $filters);
    return $query;
  }


  // find unpublished records
  public static function findUnpublished($start = null, $max = null) {
    $solr = Zend_Registry::get('solr');
    $query = "NOT status:published";
    $solr->clearFacets();
    $results = $solr->query($query, $start, $max); 

    $etds = array();
    if ($results) {
      foreach ($results->docs as $result_doc) {
	array_push($etds, new etd($result_doc->PID));
      }
    }
    return $etds;
  }

  

  /**
   * find records with embargo expiring anywhere from today up to the specified date
   * where an embargo notice has *not* already been sent and
   * there is an embargo approved by the graduate school
   * (the extra checks are to ensure that no records are missed if the cron script fails to run)
   *
   * @param string $date expiration date in YYYYMMDD format (e.g., 60 days from today)
   * @return array of etds
   */
  public static function findExpiringEmbargoes($date) {
    $solr = Zend_Registry::get('solr');
    // date *must* be in YYYYMMDD format

    // search for any records with embargo expiring between now and specified embargo end date
    // where an embargo notice has not been sent and an embargo was approved by the graduate school
    $query = "date_embargoedUntil:[" . date("Ymd") . " TO $date] NOT embargo_notice:sent NOT embargo_duration:(0 days)";
    $results = $solr->queryPublished($query);	// FIXME: paging? need to get all results

    $etds = array();
    foreach ($results->docs as $result_doc) {
      array_push($etds, new etd($result_doc->PID));
    }
    return $etds;
  }


  public static function findEmbargoed($start, $max, &$total) {
    $solr = Zend_Registry::get('solr');
    $query = "date_embargoedUntil:[" . date("Ymd") . "TO *] NOT embargo_duration:(0 days)";
    $results = $solr->query($query, $start, $max, "date_embargoedUntil asc");
    //print "<pre>"; print_r($results); print "</pre>";
    $total = $results->numFound;
    $etds = array();
    foreach ($results->docs as $result_doc) {
      array_push($etds, new etd($result_doc->PID));
    }
    return $etds;
  }

  // find recently published for goldbar
  // solr query just needs this added: sort=dateIssued%20desc
  public static function findRecentlyPublished($max = 10, $opts = null, $type = "etd") {
    $solr = Zend_Registry::get('solr');
    $query = "status:published";
    $solr->clearFacets();

    if (!is_null($opts)) {
      foreach ($opts as $key => $value) {
	switch ($key) {
	case "program":			// filter query by program
	  $solr->addFilter("program_facet:($value)");
	}
      }
    }

    $results = $solr->query($query, 0, $max, "dateIssued desc");
    //    print "<pre>"; print_r($results); print "</pre>";
    
    $etds = array();
    foreach ($results->docs as $result_doc) {
      if ($type == "etd")
	array_push($etds, new etd($result_doc->PID));
      elseif ($type == "solrEtd")
	array_push($etds, new solrEtd($result_doc));
    }
    return $etds;

  }

  
  public function pid() { return $this->pid; }
  public function status() { return isset($this->rels_ext->status) ? $this->rels_ext->status : ""; }
  public function title() { return $this->html->title; }	// how to know which?
  public function author() { return $this->mods->author->full; }
  public function program() { return $this->mods->department; }
  public function subfield() { return isset($this->mods->subfield) ? $this->mods->subfield : ""; }
  public function chair() {
    $chairs = array();
    foreach ($this->mods->chair as $c) {
      array_push($chairs, $c->full);
    }
    return $chairs;
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



  // for Zend ACL Resource
  public function getResourceId() {
    if ($this->status() != "") {
      return $this->status() . " etd";
    } else {
      return "etd";
    }
  }

  
}




  // simple function to sort etdfiles based on sequence number  (used when foxml class initializes them)
 function sort_etdfiles(etd_file $a, etd_file $b) {
    if ($a->rels_ext->sequence == $b->rels_ext->sequence) return 0;
    return ($a->rels_ext->sequence < $b->rels_ext->sequence) ? -1 : 1;
  }
