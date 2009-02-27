<?php

require_once("models/mods.php");

require_once("models/esdPerson.php");

class etd_mods extends mods {

  protected $etd_namespace = "http://www.ndltd.org/standards/metadata/etdms/1.0/";

  protected $required_fields;
  
  // auto-magic variables
  /**
   * @var author
   * @var committee
   * @var nonemory_committee
   */
  
  
  // add etd-specific mods mappings
  protected function configure() {
    /** NOTE: this data is edited on several different pages;
        each required field has a value of the edit action
	where that field is edited. */
    $this->required_fields = array("title" => "title",
				   "author" => "record",
				   "program" => "program",
				   "chair" => "faculty",
				   "committee members" => "faculty",
				   "researchfields" => "researchfield",
				   "keywords" => "record",
				   "degree" => "record",
				   "language" => "record",
				   "abstract" => "abstract",
				   "table of contents" => "contents",
				   "embargo request" => "rights",
				   "submission agreement" => "rights",
				   "send to ProQuest" => "rights",
				   "copyright" => "rights");
    
    parent::configure();

    $this->addNamespace("etd", $this->etd_namespace);
    
    $this->xmlconfig["author"] = array("xpath" => "mods:name[mods:role/mods:roleTerm = 'author']",
				       "class_name" => "mods_name");
    $this->xmlconfig["department"] = array("xpath" => "mods:name[mods:role/mods:roleTerm = 'author']/mods:affiliation");
    $this->xmlconfig["subfield"] = array("xpath" => "mods:extension/etd:degree/etd:discipline");

    // committee chair - may be more than one
    $this->xmlconfig["chair"] = array("xpath" => "mods:name[mods:role/mods:roleTerm = 'Thesis Advisor']",
				      "class_name" => "mods_name", "is_series" => true);
    $this->xmlconfig["committee"] = array("xpath" => "mods:name[mods:description = 'Emory Committee Member']",
					  "class_name" => "mods_name", "is_series" => "true");
    $this->xmlconfig["nonemory_committee"] = array("xpath" =>
						   "mods:name[mods:description = 'Non-Emory Committee Member']",
						   "class_name" => "mods_name", "is_series" => "true");
    
    $this->xmlconfig["researchfields"] = array("xpath" =>
					       "mods:subject[@authority='proquestresearchfield']",
					       "is_series" => true, "class_name" => "etdmods_subject");
    $this->xmlconfig["keywords"] = array("xpath" => "mods:subject[@authority='keyword']",
					 "is_series" => true, "class_name" => "etdmods_subject");
    $this->xmlconfig["pages"] = array("xpath" => "mods:physicalDescription/mods:extent");

    $this->xmlconfig["degree"] = array("xpath" => "mods:extension/etd:degree", "class_name" => "etd_degree");

    $this->xmlconfig["copyright"] = array("xpath" => "mods:note[@type='admin'][@ID='copyright']");

    $this->xmlconfig["embargo_request"] = array("xpath" => "mods:note[@type='admin'][@ID='embargo']");
    $this->xmlconfig["embargo"] = array("xpath" => "mods:accessCondition[@type='restrictionOnAccess']");
    $this->xmlconfig["embargo_end"] = array("xpath" => "mods:originInfo/mods:dateOther[@type='embargoedUntil']");
    $this->xmlconfig["embargo_notice"] = array("xpath" => "mods:note[@type='admin'][@ID='embargo_expiration_notice']");


    $this->xmlconfig["pq_submit"] = array("xpath" => "mods:note[@type='admin'][@ID='pq_submit']");
    
    // FIXME: may need mods_date class to set keyDate, start/end, qualifier attributes
    
    $this->xmlconfig["rights"] = array("xpath" => "mods:accessCondition[@type='useAndReproduction']");

    $this->xmlconfig["ark"] = array("xpath" => "mods:identifier[@type='ark']");
    
    
  }
  

  public function __set($name, $value) {
    switch ($name) {
    case "pages":
      $value .= " p."; break;	// value should be passed in as a number (check incoming value?)
    case "embargo":
      $value = "Embargoed for " . $value;
    } 
    parent::__set($name, $value);
  }

  public function __get($name) {
    $value = parent::__get($name);
    switch ($name) {
    case "pages":
      $value = str_replace(" p.", "", $value);	// return just the number
      break;
    case "copyright":
      $value = str_replace("registering copyright? ", "", $value);
      break;
    case "embargo_request":
      $value = str_replace("embargo requested? ", "", $value);
      break;
    case "embargo":
      $value = str_replace("Embargoed for ", "", $value);
      break;
    case "embargo_notice":
      $value = str_replace("sent ", "", $value);    // return date only
      break;
    }
    return $value;
  }
  
  public function addResearchField($text, $id = "") {
    $this->addSubject($text, "researchfields", "proquestresearchfield", $id);
  }
  
  public function addKeyword($text) {
    $this->addSubject($text, "keywords", "keyword");
  }
  
  //  function to add subject/topic pair - used to add keyword & research field
  public function addSubject($text, $mapname, $authority = null, $id = "") {
    // add a new subject/topic to the DOM and the in-memory map
    $subject = $this->dom->createElementNS($this->namespaceList["mods"], "mods:subject");
    if (! is_null($authority)) {
      $subject->setAttribute("authority", $authority);
      // proquest fields need an ID attribute, even if it is not set when the field is created
      if ($authority == "proquestresearchfield") {
	$subject->setAttribute("ID", "id$id");	  // id can't start with a number to be valid xml
      }
    }

    $topic = $this->dom->createElementNS($this->namespaceList["mods"],
					 "mods:topic", $text);
    $topic = $subject->appendChild($topic);
    
    // find first node following current type of subjects and append before
    $nodeList = $this->xpath->query("//mods:subject[@authority='$authority'][last()]/following-sibling::*");
    
    // if a context node was found, insert the new node before it
    if ($nodeList->length) {
      $contextnode = $nodeList->item(0);
      //	print "attempting to insert before:\n";
      //	print $this->dom->saveXML($nodeList->item(0)) . "\n";
      
      $contextnode->parentNode->insertBefore($subject, $contextnode);
    } else {
      // if no context node is found, new node will be appended at end of xml document
      $this->dom->documentElement->appendChild($subject);
    }

    // update in-memory map
    $this->update();
  }

  /**
   * add Committee persons - committee chair, member, or non-emory member
   * @param string $lastname
   * @param string $firstname
   * @param string $type : one of committee, chair, or nonemory_committee
   * @param string $affiliation (optional)
   */
  public function addCommittee($lastname, $firstname, $type = "committee", $affiliation = null) {
    // fixme: need a way to set netid -- set from netid if emory ?
    if (! in_array($type, array("committee", "chair", "nonemory_committee"))) {
      trigger_error("$type is not allowed type for addCommittee", E_USER_ERROR);
    }

    $set_description = false;

    $template = new etd_mods(DOMDocument::loadXML(file_get_contents("mods.xml", FILE_USE_INCLUDE_PATH)));
    
    $newnode = $this->dom->importNode($template->map[$type][0]->domnode, true);

    // map new domnode to xml object
    $name = new mods_name($newnode, $this->xpath);
    $name->first = $firstname;
    $name->last = $lastname;
    $name->full = "$lastname, $firstname";
    if (isset($name->affiliation) && !is_null($affiliation)) {
      $name->affiliation = $affiliation;
    }
	  
    // find first node following current type of subjects and append before
    if (isset($name->description)) {
      $xpath = "//mods:name[mods:description='" . $name->description ."'][last()]/following-sibling::*";
    } else {
      $xpath = "//mods:name[mods:role/mods:roleTerm='" . $name->role ."'][last()]/following-sibling::*";
    }
      $nodeList = $this->xpath->query($xpath);

    // if a context node was found, insert the new node before it
    if ($nodeList->length) {
      $contextnode = $nodeList->item(0);
    } elseif ($type == "committee") {		// currently no emory committee members in xml - insert after advisor
      $contextnode = $this->map["chair"][count($this->chair) - 1]->domnode;
    } elseif ($type == "nonemory_committee") {
      // if adding a non-emory committee member and there are none in the xml,
      // then add after last emory committee member
      if (isset($this->map['committee']) && count($this->committee))
	$contextnode = $this->map['committee'][count($this->committee) - 1]->domnode;
      else
	$contextnode = $this->map["chair"][count($this->chair) - 1]->domnode;
    } else {
      // this shouldn't happen unless there is something wrong with the xml.... 
      trigger_error("Couldn't find context node to insert new committee member", E_USER_NOTICE);
    } 

    if (isset($contextnode))
      $newnode = $contextnode->parentNode->insertBefore($newnode, $contextnode);
    else	// if no context is found, just add at the end of xml
      $newnode = $contextnode->parentNode->appendChild($newnode);
    

    $this->update();
  }



  public function setAuthorFromPerson(esdPerson $person) {
    $this->setNameFromPerson($this->author, $person);
  }

  // generic function to set name fields from an esdPerson object
  private function setNameFromPerson(mods_name $name, esdPerson $person) {
    $name->id    = trim($person->netid);
    $name->last  = trim($person->lastname);
    $name->first = trim($person->name);	// directory name OR first+middle
    $name->full  = trim($person->lastnamefirst);
  }

  /**
   * set committee names from an array of esdPerson objects
   * @param array $people
   * @param string $type defaults to committee (member); can also be chair
   */
  public function setCommitteeFromPersons(array $people, $type = "committee") {
    $needUpdate = false;
    $i = 0;	// index for looping over committee array
    foreach ($people as $person) {
      if (isset($this->map[$type][$i])) {
	$this->setNameFromPerson($this->map[$type][$i], $person);
      } else {
	$this->addCommittee($person->lastname, $person->name);
	// FIXME: need a better way store netid... - should be part of addCommittee function ?
	$this->{$type}[$i]->id = $person->netid;
	$needUpdate = true;	// DOM has changed - new nodes
      }
      $i++;
    }

    // remove any committee members beyond this set of new ones
    while (isset($this->{$type}[$i]) ) {
      $this->removeCommittee($this->{$type}[$i]->id);
      $needUpdate = true;	// DOM has changed - removed nodes
    }

    if ($needUpdate) $this->update();
  }

  /**
   * set committee names from an array of netids
   * @param array $netids
   * @param string $type defaults to committee (member); can also be chair
   */
  public function setCommittee(array $netids, $type = "committee") {
    $esd = new esdPersonObject();
    $i = 0;	// index for looping over committee array
    
    foreach ($netids as $id) {  // if id is unchanged, don't lookup/reset
      if (isset($this->map[$type][$i]) && $this->committee[$i]->id == $id) {
	$i++;
	continue;
      }
      $person = $esd->findByUsername($id);
      if ($person) {
	if (isset($this->map[$type][$i])) {
	  $this->setNameFromPerson($this->{$type}[$i], $person);
	} else {
	  $this->addCommittee($person->lastname, $person->name, $type);
	  // FIXME: need a better way store netid... - should be part of addCommittee function ?
	  $this->{$type}[$i]->id = $id;
	}
      } else {
	// shouldn't come here, since ids should be selected by drop-down populated from ESD...
	trigger_error("Could not find person information for '$id' in Emory Shared Data", E_USER_WARNING);
      }
      $i++;
    }

    // remove any committee members beyond this set of new ones
    while (isset($this->{$type}[$i]) && $this->{$type}[$i]->id  != "") {
      $this->removeCommittee($this->{$type}[$i]->id);
    }
    $this->update();
  }

  /**
   * remove a committee member or chair person by id
   * @param string $id netid
   */
  public function removeCommittee($id) {
    if ($id == "") {
      throw new XmlObjectException("Can't remove committee member/chair with non-existent id");
      return;	// don't remove empty nodes (should be part of template)
    }
    
    // remove the node from the xml dom
    $nodelist = $this->xpath->query("//mods:name[@ID = '$id'][mods:role/mods:roleTerm = 'Committee Member'
	or mods:role/mods:roleTerm = 'Thesis Advisor']");
    for ($i = 0; $i < $nodelist->length; $i++) {
      $node = $nodelist->item($i);      
      $node->parentNode->removeChild($node);
    }
    // update in-memory array so it will reflect the change
    $this->update();
  }

  // remove all non-Emory committee members  (use before re-adding them)
  public function clearNonEmoryCommittee() {
    $nodelist = $this->xpath->query("//mods:name[mods:description='Non-Emory Committee Member']");
    for ($i = 0; $i < $nodelist->length; $i++) {
      $node = $nodelist->item($i);
      $node->parentNode->removeChild($node);
    }
    $this->update();
  }

  
  // set all research fields from an array, over writing any currently set fields
  // and adding new fields as necessary
  public function setResearchFields(array $values) {
    $i = 0;	// research field array index
    foreach ($values as $id => $text) {
      if (array_key_exists($i, $this->researchfields)) {
	$this->researchfields[$i]->id = $id;
	$this->researchfields[$i]->topic = $text;
      } else {
	$this->addResearchField($text, $id);
      }
      $i++;
    }
    // remove any research fields beyond the set of new ones
    while (isset($this->researchfields[$i]) ) {
      $this->removeResearchField($this->researchfields[$i]->id);
    }
  }

  public function removeResearchField($id) {
    // remove the node from the xml dom
    // NOTE: takes numerical id as parameter, prepends 'id' (needed for valid id)
    $nodelist = $this->xpath->query("//mods:subject[@authority='proquestresearchfield'][@ID = 'id$id']");
    for ($i = 0; $i < $nodelist->length; $i++) {
      $node = $nodelist->item($i);      
      $node->parentNode->removeChild($node);
    }

    // update in-memory array so it will reflect the change
    $this->update();
  }

  // find the index for a research field by id
  public function researchFieldIndex($id) {
    for ($i = 0; count($this->researchfields); $i++) {
      $field = $this->researchfields[$i];
      if ($field->id == $id)
	return $i;
    }
  }

  public function hasResearchField($id) {
    foreach ($this->researchfields as $field) {
      if ($field->id == $id)
	return true;
    }
    return false;
  }

  // add mods:note at end of document
  public function addNote($text, $type, $id) {
    $note = $this->domnode->appendChild($this->dom->createElementNS($this->namespaceList["mods"], "mods:note", $text));
    $note->setAttribute("type", $type);
    $note->setAttribute("ID", $id);
    $this->update();
  }


  public function remove($mapname){
    if (!isset($this->{$mapname})) {
      trigger_error("Cannot remove '$mapname' - not mapped", E_USER_WARNING);
      return;
    }
    /*    for ($i = 0; $i < $nodelist->length; $i++) {
      $node = $nodelist->item($i);      
      $node->parentNode->removeChild($node);*/

    if ($this->map[$mapname] instanceof DOMElementArray) {
      foreach ($this->map[$mapname] as $node) {
	$node->parentNode->removeChild($node);
      }
    } else if ($this->map[$mapname] instanceof XmlObject) {
      $this->map[$mapname]->domnode->parentNode->removeChild($this->map[$mapname]->domnode);
    } else {
      $this->map[$mapname]->parentNode->removeChild($this->map[$mapname]);
    }

    $this->update();
  }


  /**
   * check if this portion of the record is ready to submit and all required fields are filled in
   *
   * @return boolean ready or not
   */
  public function readyToSubmit() {
    // if anything is missing, record is not ready to submit
    if (count($this->checkRequired())) return false;

    // don't attempt to validate until all required fields are filled
    // (missing research fields is invalid because of the ID attribute)

    // capture any errors so they can be logged
    libxml_use_internal_errors(true);
    libxml_clear_errors();

    // validate against MODS schema
    if (! $this->isValid()) {	    // xml should be valid MODS

      // if logger object is registered, log any validation errors
      if (Zend_Registry::isRegistered('logger')) {
	$logger = Zend_Registry::get('logger');
	// Note: no access to foxml record id at this level, cannot include in log file
	$logger->err("MODS XML is not valid according to MODS schema");
	$errors = libxml_get_errors();
	foreach ($errors as $error) {
	  $message = $error->message . "(Line " . $error->line . ", column " . $error->column . ")";
	  switch ($error->level) {
	  case LIBXML_ERR_WARNING: $logger->warn($message); break;
	  case LIBXML_ERR_ERROR:   $logger->err($message); break;
	  case LIBXML_ERR_FATAL:   $logger->crit($message); break;
	  }
	}
      }
      return false;
    }      
      
    // all checks passed
    return true;
  }

  /**
   * check required fields; returns an array with problems, missing data
   * @return array associative array of missing fields with the action where they are edited
   */
  public function checkRequired() {
    $missing = array();

    // check everything that is specified as required 
    foreach ($this->required_fields as $field => $action) {
      if (! $this->isComplete($field)) $missing[$field] = $action;
    }
    // NOTE: key is  missing field, value is edit action

    return $missing;
  }

  /**
   * check if a required field is filled in completely (part of submission-ready check)
   * 
   * @param string $field name
   * @return boolean
   */
  public function isComplete($field) {
    switch($field) {
    case "author":
      return ($this->author->id != "" &&
	      trim($this->author->first) != "" && trim($this->author->last) != "");
    case "program":
      return ($this->author->affiliation != "");
    case "chair":
      // complete if there is at least one valid chair (all should have an emory id)
      return ($this->chair[0]->id != "");
    case "committee members":
      // complete if there is at least one committee member (valid faculty, same as chair test)
      return ($this->committee[0]->id != "");
    case "researchfields":
      // complete if there is at least one non-blank research field
      return ((count($this->researchfields) != 0) &&
	      ($this->researchfields[0]->id != "" || $this->researchfields[0]->topic != ""));
    case "keywords":
      // complete if there is at least one non-blank keyword
      return ((count($this->keywords) != 0) && (trim($this->keywords[0]->topic) != ""));
    case "language":
      return ($this->language->text != "" && $this->language->code != "");
    case "table of contents":
      return (trim($this->tableOfContents) != "");
    case "embargo request":
      return $this->hasEmbargoRequest();
    case "submission agreement":
      return $this->hasSubmissionAgreement();
    case "send to ProQuest":
      return $this->hasSubmitToProquest();
    case "copyright":
      // if record will be sent to PQ, copyright request is required
      if ($this->submitToProquest()) return $this->hasCopyright();
      else return true;		// not required = not incomplete (?)

      // simple cases where field name matches class variable name
    case "title":
    case "abstract":
    case "degree":
      return (trim($this->$field) != "");  	// complete if not empty (not just whitespace)
      
    default:
      // if requested field matches a mapped variable, do a simple check
      if (isset($this->$field))
	return (trim($this->$field) != ""); 
      else
	// otherwise, complain
	trigger_error("Cannot determine if '$field' is complete", E_USER_NOTICE);
    }
  }

  

  // specialized version of check required - disregards rights completion status
  public function thesisInfoComplete() {
    $missing = $this->checkRequired();
    // nothing is missing
    if (count($missing) == 0) return true;

    // the value of the array is the action needed to edit it;
    // thesis info does not include rights values
    $uniq_values = array_unique(array_values($missing));
    foreach ($uniq_values as $val) {
      if ($val != "rights") return false;
    }
    return true;
  }

  
  function hasCopyright() {
    return (isset($this->copyright) && $this->copyright != "");
    //    return preg_match("/applying for copyright\? (yes|no)/", $this->copyright);
  }

  function hasEmbargoRequest() {
    return (isset($this->embargo_request) && $this->embargo_request != "");
  }

  function hasSubmissionAgreement() {
    return (isset($this->rights) && $this->rights != "");
  }

  /**
   * Checks if submission to ProQuest required for this record
   * @return boolean (true = required)
   */
  function ProquestRequired() {
    return ($this->degree->name == "PhD");
  }
  
  // is submit to proquest set (yes/no)
  function hasSubmitToProquest() {
    return (isset($this->pq_submit) && $this->pq_submit != "");
  }

  // is the record set to be submitted to proquest?
  function submitToProquest() {
    return ($this->degree->name == "PhD" || $this->pq_submit == "yes");
  }

  /**
   * check if a field is required
   * @param string $field name of the field
   * @return boolean
   */
  function isRequired($field) {
    return in_array($field, array_keys($this->required_fields));
  }
  
}


/**
 * ProQuest fields are stored as subjects with a numerical id, but this
 * is not valid xml; intercept the get/and set calls on the id of the
 * mods_subject to add the id to the xml but not show to the user
 */
class etdmods_subject extends mods_subject {
  public function __set($name, $value) {
    if ($name == "id" && preg_match("/[0-9]{4}/", $value)) {
      $value = "id$value";
    } 
    parent::__set($name, $value);
  }

  public function __get($name) {
    $value = parent::__get($name);
    if ($name == "id")
      return preg_replace("/^id/", "", $value);
    else
      return $value;
  }
}



class etd_degree extends XmlObject {
  public function __construct($xml, $xpath) {
    $config = $this->config(array(
	"name" => array("xpath" => "etd:name"),
	"level" => array("xpath" => "etd:level"),
	"discipline" => array("xpath" => "etd:discipline"),
	 ));
    parent::__construct($xml, $config, $xpath);
  }

  // default conversion to string-- used for checking field is complete 
  public function __toString() {
    return $this->name;
  }
    
}

