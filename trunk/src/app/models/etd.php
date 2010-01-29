<?php
/**
 * @category Etd
 * @package Etd_Models
 * @subpackage Etd
 */

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


/**
 * ETD fedora object
 * - note that magic methods depend on ETD content model & service objects
 *
 * @property etd_rels $rels_ext RELS-EXT datastream
 * @property etd_mods $mods MODS datastream
 * @property etd_html $html XHTML datastream
 * @property premis $premis PREMIS datastream
 * @property XacmlPolicy $policy POLICY datastream
 * @property array of etd_file $pdfs array of related etd_file PDF objects
 * @property array of etd_file $originals array of related etd_file Original objects
 * @property array of etd_file $supplements array of related supplemental etd_file objects
 * @property user $authorInfo authorInfo object for etd author
 * @method string getMarcxml() get metadata in marcxml format
 * @method string getEtdms() get metadata in ETD-MS format
 * @method string getMods() get cleaned up MODS (without ETD-specific fields)
 */
class etd extends foxml implements etdInterface {

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

  /**
   * @var Zend_Config configuration for the school that author is getting degree from
   */
  private $school_config;

  /**
   * initialize etd
   * @param string|DOMDocument|Zend_Config $arg optional
   * - if string, retrieve object from Fedora by specified pid
   * - if DOMDocument, initialize from DOM contents (mostly for testing)
   * - if not specified, create a new etd from template
   */
  public function __construct($arg = null) {
    // if parameter is an instance of zend_config, then store it
    // locally but do NOT pass on to parent constructor
    if ($arg instanceof Zend_Config) {
      $this->school_config = $arg;
      $arg = null;
    }
    parent::__construct($arg);
    if (Zend_Registry::isRegistered("config")) {
      $config = Zend_Registry::get("config");
    }

    if ($this->init_mode == "pid") {
      // make sure user has access to this object by accessing RELS datastream
      // - if not, this will throw error on initialization, when it is easier to catch
      $this->rels_ext;

      // check that etd content model is present; otherwise, wrong type of object
      if (isset($config) && (!isset($this->rels_ext->hasModel) ||
			     !$this->rels_ext->hasModels->includes($this->fedora->risearch->pid_to_risearchpid($config->contentModels->etd)))) {
	throw new FoxmlBadContentModel("$arg does not have etd content model " . $config->contentModels->etd);
      }

      // member of collections, attempt to find collection that matches a per-school config
      if (isset($this->rels_ext->isMemberOfCollections) &&
	  count($this->rels_ext->isMemberOfCollections)) {
	$schools_cfg = Zend_Registry::get("schools-config");
	for ($i = 0; $i < count($this->rels_ext->isMemberOfCollections); $i++) {
	  $coll = $this->rels_ext->isMemberOfCollections[$i];
	  if ($school_id = $schools_cfg->getIdByFedoraCollection($this->fedora->risearch->risearchpid_to_pid($coll))) {
	    $this->school_config = $schools_cfg->$school_id;
	  }
	}
      }
      // warn if could not determine school config
      if (! isset($this->school_config))
	trigger_error("Could not determine per-school configuration based on collection membership", E_USER_WARNING);
      
    } elseif ($this->init_mode == "dom") {    
      // anything here?
    } elseif ($this->init_mode == "template") {
        // new etd objects - add relation to contentModel object
      if (isset($config)) {
	$config = Zend_Registry::get("config");
	$this->rels_ext->addContentModel($config->contentModels->etd);
	$this->rels_ext->addRelationToResource("rel:isMemberOfCollection",
					       $config->collections->all_etd);
      } else {
	trigger_error("Config is not in registry, cannot retrieve contentModel for etd");
      }
     
      // all new etds should start out as drafts
      $this->rels_ext->addRelation("rel:etdStatus", "draft");

      // add relation to school-specific collection
      if (isset($this->school_config)) {
	$this->rels_ext->addRelationToResource("rel:isMemberOfCollection",
					       $this->school_config->fedora_collection);
      }

      // NOTE: if PQ research field is optional, the field must be removed because if
      // it is present and left blank, the MODS is invalid
      if (!$this->isRequired("researchfields") && count($this->mods->researchfields)) {
	$this->mods->remove("researchfields");
      }
      
    }


    // base ACL resource id is etd
    $this->acl_id = "etd";

    // set admin agent based on school config
    if (isset($this->school_config)) {
      $this->admin_agent = $this->school_config->label;
    } else {
      // generic default etds admin agent as fall-back
      $this->admin_agent = "ETD Administrator";
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
   * explicitly set the per-school configuration associated with this etd
   * @param Zend_Config $school
   */
  public function setSchoolConfig(Zend_Config $school) {
    $this->school_config = $school;
  }
  


  /**
   *  determine a user's role in relation to this ETD (for access controls)
   * @param esdPerson $user optional
   * @return string role
   * @todo look into using ownerId instead of rels-ext author
   */
  public function getUserRole(esdPerson $user = null) {
    if (is_null($user)) return "guest";

    // superuser should supercede all other roles
    if ($user->role == "superuser") return $user->role;

    // FIXME: can we use top-level ownerId now and get rid of rels-ext author field?
    if ($user->netid == $this->rels_ext->author)
      return "author";
    elseif ($this->rels_ext->committee instanceof DOMElementArray
	    && $this->rels_ext->committee->includes($user->netid))	
      return "committee";
    elseif ($user->isCoordinator($this->mods->department))
      return "program coordinator";
    elseif ($pos = strpos($user->role, " admin")) {
      // if a user is a school-specific admin, determine if they are admin for *this* etd
      $admin_type = substr($user->role, 0, $pos);
      if ($admin_type == $this->school_config->acl_id)
	return "admin";
    }
    
    return $user->role;
  }

  /**
   * set etd status and update any policy rules (on etd and related files) accordingly
   * @param string $new_status
   */
  public function setStatus($new_status) {
    $old_status = $this->rels_ext->status;
    //    if ($new_status == $old_status) return;	// do nothing - no change (?)

    // when leaving certain statuses, certain rules need to go away
    switch ($old_status) {
    case "published":	$this->removePolicyRule("published");   break;        
    case "draft":	 $this->removePolicyRule("draft");    break;
    }
    
    // certain policy rules need to change when status changes
    switch ($new_status) {
    case "draft":	// add draft rule & allow author to edit
      $this->addPolicyRule("draft");
      break;
    case "published":
      $this->addPolicyRule("published");
      // NOTE: embargo expiration end-date for file policy handled by publication script
      $this->setOAIidentifier();
      // ensure that object state is set to Active (e.g., if previously set inactive)
      $this->setObjectState(FedoraConnection::STATE_ACTIVE);

      // if Abstract or ToC is restricted and embargo has not expired,
      // ensure no restricted content is present in mods/dc
      if($this->isEmbargoed() &&
	 $this->mods->isEmbargoRequested(etd_mods::EMBARGO_ABSTRACT)) {
	$this->mods->abstract = "";
	$this->dc->description = "";
      }
      if ($this->isEmbargoed() &&
	  $this->mods->isEmbargoRequested(etd_mods::EMBARGO_TOC)) {
	$this->mods->tableOfContents = "";
      }
      break;
    case "inactive":
      // if removing from publication, set to inactive so OAI provider can notify harvesters of removal
      $this->setObjectState(FedoraConnection::STATE_INACTIVE);
      break;
    }

    // for all - store the status in rels-ext
    $this->rels_ext->status = $new_status; 
  }

  /**
   * find & return the last record status before this one, or null if no previous status
   * @return string
   */
  public function previousStatus() {
    // datastream ID for rels-ext 
    $dsid = $this->xmlconfig["rels_ext"]['dsID'];

    // get datastream history (info about each revision)
    $rels_history = $this->fedora->getDatastreamHistory($this->pid, $dsid);

    $dom = new DOMDocument();
    // fedora returns datastream history with most recent versions first
    // get datastream by date and compare status until one is found that is different from current status
    foreach ($rels_history->datastream as $ds_version) {
      $xml = $this->fedora->getDatastream($this->pid, $dsid, $ds_version->createDate);
      $dom->loadXML($xml);
      $rels = new etd_rels($dom);
      if ($rels->status != $this->rels_ext->status)
	return $rels->status;
    }

    // if no status was found different than the current status, there was no previous status
    return null;
  }

  /**
   * set OAI identifier in RELS-EXT based on full ARK 
   */
  public function setOAIidentifier() {
    if (!isset($this->mods->ark) || $this->mods->ark == "")
        throw new Exception("Cannot set OAI identifier without ARK.");
    // set OAI id based on short-form ark
    $this->rels_ext->setOAIidentifier("oai:" . $this->mods->ark);
    unset($persis);
  }

  /**
   * convenience functions to add and remove policies in etd and related objects all at once
   *  - note: the only rule that may be added and removed to original/archive copy is draft
   * @param string $name
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


      // special case - customize publish rule according to access restrictions
      if ($name == "published") {
	// if embargo end is set, put that date in published policy 
	if (isset($this->mods->embargo_end) && $this->mods->embargo_end != ''
	    && isset($obj->policy->published->condition) &&
	    isset($obj->policy->published->condition->embargo_end))
	  $obj->policy->published->condition->embargo_end = $this->mods->embargo_end;
      }
	
    }

    // special case - customize published rule only for ETD object, not files
    if ($name == "published") {
      // restrict abstract/toc if requested
      $restrict_html = array();
      if ($this->mods->isEmbargoRequested(etd_mods::EMBARGO_TOC))
	$restrict_html[] = "tableofcontents";
      if ($this->mods->isEmbargoRequested(etd_mods::EMBARGO_ABSTRACT))
	$restrict_html[] = "abstract";
      
      $this->policy->published->condition->restrictMethods($restrict_html);
    }
  }

  /**
   * remove policy by name from etd and all related etd files
   * @param string $name
   */
  public function removePolicyRule($name) {
    $objects = array_merge(array($this), $this->pdfs, $this->supplements, $this->originals);
    foreach ($objects as $obj) {
      if (isset($obj->policy->{$name}))	$obj->policy->removeRule($name);
    }
  }

  /**
   * add pdf
   * @param etd_file $etdfile
   */
  public function addPdf(etd_file $etdfile) {
    // if value is already set, add to the total (multiple pdfs)
    if (isset($etdfile->dc->pages) && isset($this->mods->pages)) 
      $this->mods->pages = (int)$this->mods->pages + (int)$etdfile->dc->pages;
    else
      $this->mods->pages = $etdfile->dc->pages;

    return $this->addFile($etdfile, "PDF");
  }
  /**
   * add original file
   * @param etd_file $etdfile
   */
  public function addOriginal(etd_file $etdfile) {
    // original documents should not have 'view' rule; this is the best place to remove 
    if (isset($etdfile->policy->view)) $etdfile->policy->removeRule("view");
    return $this->addFile($etdfile, "Original");
  }
  /**
   * add supplemental file
   * @param etd_file $etdfile
   */
  public function addSupplement(etd_file $etdfile) { return $this->addFile($etdfile, "Supplement");  }
  
  /**
   * set the relations between this etd and a file object (original, pdf, supplement)
   * @param etd_file $etdfile
   * @param string $relation
   */
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


  /**
   * remove relation to a file (when file is purged or deleted)
   * @param etd_file $etdfile
   */
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

  /**
   * save etd and related files
   * @param string $message
   * @return string timestamp on success, empty string on failure
   */
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
  
  /**
   * synchronize Dublin Core with MODS (master metadata); called before save to keep in sync
   */
  public function updateDC() {
    $this->dc->title = $this->mods->title;
    $this->dc->date = $this->mods->date;
    $this->dc->language = $this->mods->language->text;

    // in some cases, a properly escaped ampersand in the MODS shows
    // up not escaped in the DC, even though unit test indicates it does not
    // -- circumvent this problem whenever possible
    if ($this->dc->description != $this->mods->abstract)
      $this->dc->description = $this->mods->abstract;

    // author
    $this->dc->creator = $this->mods->author->full;

    // contributors - committee chair(s) & member(s)
    $contributors = array(); 
    // committee chair(s)
    foreach ($this->mods->chair as $chair) {
      if ($chair->full != "") $contributors[] = $chair->full;
    }
    // committee members
    foreach ($this->mods->committee as $committee_mem) {
      if ($committee_mem->full != "") $contributors[] = $committee_mem->full;
    }
    // non-emory committe
    foreach ($this->mods->nonemory_committee as $committee_mem) {
      if ($committee_mem->full != "") {
	$name = $committee_mem->full;
	if ($committee_mem->affiliation != "") $name .= " (" . $committee_mem->affiliation . ")";
	$contributors[] = $name;
      }
    }
    $this->dc->setContributors($contributors);

    // subjects : research fields and keywords, minus any empty entries
    $subjects = array_diff(array_merge($this->researchfields(), $this->keywords()), array(""));
    $this->dc->setSubjects($subjects);

    // ark (resolvable form) for this object
    if (isset($this->mods->identifier)) $this->dc->setArk($this->mods->identifier);

    // related objects : arks for public etd files (pdfs and supplements)
    $relations = array();
    foreach (array_merge($this->pdfs, $this->supplements) as $file) {
      if (isset($file->dc->ark)) $relations[] = $file->dc->ark;
    }
    $relations[] = "https://etd.library.emory.edu/";
    $this->dc->setRelations($relations);
    if (isset($this->mods->degree_grantor))
      $this->dc->publisher = $this->mods->degree_grantor->namePart;
    if (isset($this->mods->physicalDescription))
      $this->dc->format = $this->mods->physicalDescription->mimetype;

    $rights = isset($this->mods->rights) ? $this->mods->rights : ""; 
    if ($this->isEmbargoed()) {
	$rights = $rights . " Access has been restricted until " . $this->mods->embargo_end; 
    }
    if (! empty($rights)) $this->dc->rights = $rights;
    $this->dc->setTypes(array($this->mods->genre, "text"));
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
      // if abstract is restricted & embargo not yet expired, blank it out in mods/dc
      if ($this->isEmbargoed() &&
	  $this->mods->isEmbargoRequested(etd_mods::EMBARGO_ABSTRACT)) {
	$this->mods->abstract = "";
	$this->dc->description = "";
      }	else {		// otherwise, set plain-text version in mods/dc
	// clean & remove all tags
	$value = etd_html::removeTags($value);
	$this->mods->abstract = $value;
	$this->dc->description = $value;
      } 
      break;
    case "contents":
      $this->html->contents = $value;
      // if ToC is restricted & embargo has not expired, blank out any content 
      if ($this->isEmbargoed() && $this->mods->isEmbargoRequested(etd_mods::EMBARGO_TOC)) {
	$this->mods->tableOfContents = "";
      } else {		// otherwise, set plain-text version in mods
	// using the html contents value, since it is already slightly cleaned up
	$this->mods->tableOfContents = etd_html::formattedTOCtoText($this->html->contents);
      }
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
  
  /**
   * is this record embargoed?
   * returns true if embargo end date is set and after current time
   * @return boolean
   */
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
    if ($this->school_config && is_object($this->school_config->submission_fields->required)) {
      return in_array($field, $this->school_config->submission_fields->required->toArray());
    }
  }

  /**
   * check if required fields are complete
   * @return array of missing fields
   */
  public function checkRequired() {
    $required_fields = $this->requiredFields();

    // check required fields that are provided by mods
    $mods_missing = $this->mods->checkFields(array_intersect($required_fields,
					     $this->mods->available_fields));
  
    // check required fields provided by user object
    if (isset($this->authorInfo)) {
      $user_missing = $this->authorInfo->checkFields(array_intersect($required_fields,
						     $this->authorInfo->available_fields));
    } else {
      // if authorInfo is not yet set, assume all non-mods required fields are missing
      $user_missing = array_diff($required_fields, $this->mods->available_fields);
    }
    return array_merge($mods_missing, $user_missing);
  }
  
  /**
   * check if optional fields are complete
   * @return array of missing fields
   */
  public function checkOptional() {
    $optional_fields = $this->optionalFields();
    
    // check any optional fields that are provided by mods
    $mods_missing = $this->mods->checkFields(array_intersect($optional_fields,
							$this->mods->available_fields));
    // check optional fields provided by user object
    if (isset($this->authorInfo)) {
      $user_missing = $this->authorInfo->checkFields(array_intersect($optional_fields,
				     $this->authorInfo->available_fields));
    } else {
      // if authorInfo is not yet set, assume all non-mods optional fields are missing
      $user_missing = array_diff($optional_fields, $this->mods->available_fields);
    }
    return array_merge($mods_missing, $user_missing);
  }

  /**
   * is this record ready to submit, with all required fields and files?
   * @param string $mode only return information for one of authorInfo, mods (optional)
   * @return bool
   */
  public function readyToSubmit($mode = null) {
    $required_fields = $this->requiredFields();

    if ($mode == null || $mode == "mods") {
      if (! $this->mods->readyToSubmit(array_intersect($required_fields,
				     $this->mods->available_fields)))
	return false;
      // checking mods only
      if ($mode == "mods") return true;
    }

    if ($mode == null || $mode = "authorInfo") {
      if (!isset($this->authorInfo) ||
	  !$this->authorInfo->readyToSubmit(array_intersect($required_fields,
		   			    $this->authorInfo->available_fields)))
	return false;
      // checking author info only
      if ($mode == "authorInfo") return true;
    } 

    if (! $this->hasPDF()) return false;
    if (! $this->hasOriginal()) return false;
    
    return true;
  }

  /**
   * get per-school configured required fields as an array
   * @return array
   */
  public function requiredFields() {
    if (!isset($this->school_config))
      trigger_error("School config is not set - could not determine required fields", E_USER_WARNING);
      
    if (isset($this->school_config->submission_fields->required)) {
      if (is_object($this->school_config->submission_fields->required)) 
	return  $this->school_config->submission_fields->required->toArray();
      else	// single item - force into an array
	return  array($this->school_config->submission_fields->required);
    } else {
      return array();
    }
  }
  
  /**
   * get per-school configured optional fields as an array
   * @return array
   */
  public function optionalFields() {
    if (!isset($this->school_config))
      trigger_error("School config is not set - could not determine optional fields", E_USER_WARNING);

    if (isset($this->school_config->submission_fields->optional)) {
      if (is_object($this->school_config->submission_fields->optional))
	return  $this->school_config->submission_fields->optional->toArray();
      else	// single item - force into an array
	return  array($this->school_config->submission_fields->optional);
    } else {
      return array();
    }
  }

  /**
   * does this etd have at least one pdf?
   * @return bool
   */
  public function hasPDF() {
    // what is the best way to check this? use rels-ext?
    return (count($this->pdfs) > 0);
  }

  /**
   * does this etd have at least one original file?
   * @return bool
   */
  public function hasOriginal() {
    return (count($this->originals) > 0);
  }

  /**
   * check if this object (or inherited object) is honors or not
   * @return bool
   * @deprecated - use schoolId function instead
   */
  public function isHonors() {
    if (! isset($this->school_config))
	trigger_error("School config is not set, cannot determine if is honors", E_USER_ERROR);
    return ($this->school_config->acl_id == "honors");
  }

  /**
   * get school acl id for this etd (e.g., to determine if grad/candler/honors)
   * @return string
   */
  public function schoolId() {
    if (! isset($this->school_config))
	trigger_error("School config is not set, cannot retrieve school id", E_USER_ERROR);
    return $this->school_config->acl_id;;
    
  }

  
  /**
   * override default foxml ingest function to use arks for object pids
   * @param string $message
   * @return string timestamp if successful, empty if not
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
    list($nma, $naan, $noid) = $persis->parseArk($ark);

    $this->pid = $pid;
    // resolvable uri ark is identifier and primary display
    $this->mods->identifier = $ark;
    $this->mods->location->primary = $ark;
    // also store short-form ark
    $this->mods->ark = "ark:/" . $naan . "/" . $noid;

    return $this->fedora->ingest($this->saveXML(), $message);
  }


  /**
   * make a note in record history that graduation has been confirmed
   */
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

  
  /**
   * make a note in record history that record has been sent to ProQuest
   */
  public function sent_to_proquest() {
    $this->premis->addEvent("administrative",
			   "Submitted to Proquest by ETD system",
			   "success",  array("software", "etd system"));
  }

  /**
   * add an admin note that embargo expiration was sent
   */
  public function embargo_expiration_notice() {
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


  /**
   * extend the embargo
   * - change the embargo end date in MODS
   * - remove admin note that embargo expiration has been sent (in MODS)
   * - change end date in the published rule of policy datastream in
   *   etd, PDF, and any supplemental etdFiles
   * - add an event to the record history - change and reason
   * NOTE: record must still be saved after calling this function
   * @param string $newdate embargo end date in YYYY-MM-DD format
   * @param string $message message/reason to put in record history for change
   * @throws Exception for invalid date format or date before current date
   */
  public function updateEmbargo($newdate, $message) {
    // check format of new date - must be YYYY-MM-DD
    if (!preg_match("/^[0-9]{4}\-[012][0-9]\-[0123][0-9]$/", $newdate)) {
      throw new Exception("Invalid date format '$newdate'; must be in YYYY-MM-DD format");
    // additional sanity check - new date must not be before today
    } elseif (strtotime($newdate) < time()) {
      throw new Exception("New embargo date '$newdate' is in the past; cannot update embargo");
    }

    // update embargo end date in MODS
    $this->mods->embargo_end = $newdate;
    
    // if present, remove admin note that embargo 60-day notice has been sent,
    // so a notice will get sent 60 days before new embargo end date
    if (isset($this->mods->embargo_notice))
      $this->mods->remove("embargo_notice");
    
    // update xacml policies
    // - update end date condition on published policies for etd, pdfs, supplements
    // FIXME: duplicate logic from addPolicyRule - pull out somewhere common? 
    foreach (array_merge(array($this), $this->pdfs, $this->supplements) as $obj) {
      if (isset($obj->policy->published) && isset($this->policy->published->condition)
	  && isset($obj->policy->published->condition->embargo_end)) {
	$obj->policy->published->condition->embargo_end = $this->mods->embargo_end;
      }
    }

    // add event to record history with message passed in
    // -- if this is being done by a logged in user, pick up for event 'agent' 
    if (Zend_Registry::isRegistered('current_user')) {
      $user = Zend_Registry::get('current_user');
      $agent = array("netid", $user->netid);
    } else {
      // fall back - generic agent
      $agent = array("software", "etd system");
    }
    $this->premis->addEvent("administrative",
			    "Access restriction ending date is updated to $newdate - $message",
			    "success",  $agent);
  }
  


  public function abstract_word_count() {
    // return word count on text-only version, not formatted html version
    return str_word_count($this->mods->abstract);
  }


  public function pid() { return $this->pid; }
  public function status() { return isset($this->rels_ext->status) ? $this->rels_ext->status : ""; }
  public function title() {
    // call fedora service - returns html title if user is allowed to see it
    try {
      return $this->__call("title", array());
    } catch (FedoraAccessDenied $e) {
      // swallow acccess denied exception and only display as a notice
      trigger_error("Access denied to title -- " . $e->getMessage(), E_USER_NOTICE);
      return "";
    }
  }	
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
  public function _abstract() {
    // call fedora service - returns html abstract if user is allowed to see it
    try {
      return $this->__call("abstract", array());
    } catch (FedoraAccessDenied $e) {
      // swallow acccess denied exception and only display as a notice
      trigger_error("Access denied to abstract -- " . $e->getMessage(), E_USER_NOTICE);
      return "";
    }

  }
  public function tableOfContents() {
    // call fedora service - returns html ToC if user is allowed to see it
    try {
      return $this->__call("tableofcontents", array());
    } catch (FedoraAccessDenied $e) {
      // swallow acccess denied exception and only display as a notice
      trigger_error("Access denied to table of contents -- " . $e->getMessage(),
		    E_USER_NOTICE);
      return "";
    }
  }
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
    return $this->mods->identifier;     // want the resolvable version of the ark
  }

  /**
   * for Zend ACL Resource
   * NOTE: does not include per-school info (e.g., honors, grad)
   * - use getUserRole to see if a per-school admin is admin on a particular etd
   * @see etd::getUserRole
   */
  public function getResourceId() {
    // start with basic resource acl id for etd
    $acl_id = $this->acl_id;

    // if status is available, modify acl id with status
    if ($this->status() != "") {
      $acl_id = $this->status() . " " . $acl_id;
    }

    return $acl_id;
  }

  
}





/**
 * simple function to sort etdfiles based on sequence number  (used when foxml class initializes them)
 */
function sort_etdfiles(etd_file $a, etd_file $b) {
    if ($a->rels_ext->sequence == $b->rels_ext->sequence) return 0;
    return ($a->rels_ext->sequence < $b->rels_ext->sequence) ? -1 : 1;
}
