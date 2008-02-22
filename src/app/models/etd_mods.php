<?php

require_once("models/mods.php");

require_once("models/esdPerson.php");

class etd_mods extends mods {

  protected $etd_namespace = "http://www.ndltd.org/standards/metadata/etdms/1.0/";
  
  // add etd-specific mods mappings
  protected function configure() {
    parent::configure();

    $this->addNamespace("etd", $this->etd_namespace);
    
    $this->xmlconfig["author"] = array("xpath" => "mods:name[mods:role/mods:roleTerm = 'author']",
				       "class_name" => "mods_name");
    $this->xmlconfig["department"] = array("xpath" => "mods:name[mods:role/mods:roleTerm = 'author']/mods:affiliation");
    $this->xmlconfig["subfield"] = array("xpath" => "mods:extension/etd:degree/etd:discipline");
    
    $this->xmlconfig["advisor"] = array("xpath" => "mods:name[mods:role/mods:roleTerm = 'Thesis Advisor']",
					"class_name" => "mods_name");
    // can't seem to filter out empty committee members added by Fez...
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
      $value = str_replace("applying for copyright? ", "", $value);
      $value = str_replace("applying for copyright?", "", $value);
      break;
    case "embargo_request":
      $value = str_replace("embargo requested? ", "", $value);
      $value = str_replace("embargo requested?", "", $value);
      break;
    case "embargo":
      $value = str_replace("Embargoed for ", "", $value);
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

  public function addCommitteeMember($lastname, $firstname, $emory = true, $affiliation = null) {
    // fixme: need a way to set netid -- set from netid if emory ?
    
    if ($emory)  {
      $type = "Emory Committee Member";
      $newnode = $this->map['committee'][0]->domnode->cloneNode(true);
    } else {
      // fixme: what if there are no nonemory_committee nodes?
      // could easily clone emory committee and set description
      $type = "Non-Emory Committee Member";
      if (isset($this->map['nonemory_committee'][0])) {
	$no_nonemory = false;
	$newnode = $this->map['nonemory_committee'][0]->domnode->cloneNode(true);
      } elseif (isset($this->map['committee'][0])) {
	// xml does not include a non-emory committee member - copy emory and change accordingly
	$no_nonemory = true;
	$newnode = $this->map['committee'][0]->domnode->cloneNode(true);
	$newnode->appendChild($this->dom->createElementNS($this->namespaceList["mods"], "mods:affiliation"));
      } else {
	trigger_error("No committee nodes to copy! Can't add committee member", E_USER_WARNING);
	return;
      }
    }

    // map new domnode to xml object
    $name = new mods_name($newnode, $this->xpath);
    $name->first = $firstname;
    $name->last = $lastname;
    $name->full = "$lastname, $firstname";
    if (!$emory && !is_null($affiliation)) {
      $name->affiliation = $affiliation;
      if ($no_nonemory) $name->description = $type;
    }
    
    // find first node following current type of subjects and append before
    $nodeList = $this->xpath->query("//mods:name[mods:description='$type'][last()]/following-sibling::*");

    // if a context node was found, insert the new node before it
    if ($nodeList->length) {
      $contextnode = $nodeList->item(0);
      $newnode = $contextnode->parentNode->insertBefore($newnode, $contextnode);
    } elseif (!$emory) {
      // if adding a non-emory committee member and there are none in the xml,
      // then add after last emory committee membe
      $nodeList = $this->xpath->query("//mods:name[mods:description='Emory Committee Member'][last()]/following-sibling::*");
      if ($nodeList->length) {
	$contextnode = $nodeList->item(0);
	$newnode = $contextnode->parentNode->insertBefore($newnode, $contextnode);
      }
    }else {
      print "error: couldn't find context node...<br/>\n";	// this shouldn't happen.... ?
    }

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
   * set advisor fields
   */
  public function setAdvisor($id) {
    if ($this->advisor->id == $id)   // if id is unchanged, don't lookup/reset
      return;
    $esd = new esdPersonObject();
    $this->setAdvisorFromPerson($esd->findByUsername($id));
  }

  public function setAdvisorFromPerson(esdPerson $person) {
    if (isset($this->advisor))
      $this->setNameFromPerson($this->advisor, $person);
    else
      trigger_error("Advisor is not set!", E_USER_WARNING);
  }
  
  public function setCommitteeFromPersons(array $people) {
    $needUpdate = false;
    $i = 0;	// index for looping over committee array
    foreach ($people as $person) {
      if (isset($this->map["committee"][$i])) {
	$this->setNameFromPerson($this->map["committee"][$i], $person);
      } else {
	$this->addCommitteeMember($person->lastname, $person->name);
	// FIXME: need a better way store netid... - should be part of addCommittee function ?
	$this->committee[$i]->id = $person->netid;
	$needUpdate = true;	// DOM has changed - new nodes
      }
      $i++;
    }

    // remove any committee members beyond this set of new ones
    while (isset($this->committee[$i]) ) {
      $this->removeCommitteeMember($this->committee[$i]->id);
      $needUpdate = true;	// DOM has changed - removed nodes
    }

    if ($needUpdate) $this->update();
  }
  
  public function setCommittee(array $netids) {
    $esd = new esdPersonObject();
    $i = 0;	// index for looping over committee array
    
    foreach ($netids as $id) {  // if id is unchanged, don't lookup/reset
      if (isset($this->map["committee"][$i]) && $this->committee[$i]->id == $id) {
	$i++;
	continue;
      }
      $person = $esd->findByUsername($id);
      if ($person) {
	if (isset($this->map["committee"][$i])) {
	  $this->setNameFromPerson($this->committee[$i], $person);
	} else {
	  $this->addCommitteeMember($person->lastname, $person->name);
	  // FIXME: need a better way store netid... - should be part of addCommittee function ?
	  $this->committee[$i]->id = $id;
	}
      } else {
	// shouldn't come here, since ids should be selected by drop-down populated from ESD...
	trigger_error("Could not find person information for '$id' in Emory Shared Data", E_USER_WARNING);
      }
      $i++;
    }

    // remove any committee members beyond this set of new ones
    while (isset($this->committee[$i]) ) {
      $this->removeCommitteeMember($this->committee[$i]->id);
    }

    $this->update();
  }

  /* remove a committee member by id */
  public function removeCommitteeMember($id) {
    // remove the node from the xml dom
    $nodelist = $this->xpath->query("//mods:name[@ID = '$id']");
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
	$this->researchfields[$i]->id = "id" . $id;	// for valid xml - can't start numeric
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
    if (! $this->isValid()) {	    // xml should be valid MODS
      // error message?
      return false;
    }      

      
    // all checks passed
    return true;
  }

  /**
   * check required fields; returns an array with problems, missing data
   * @return array missing fields
   */
  public function checkRequired() {
    $missing = array();

    // note - key is display of what is missing, value is edit action (where should this logic be?)
    
    // must have a valid advisor - should have an emory id
    if ($this->advisor->id == "") $missing["advisor"] = "faculty";

    // at least one committee member (valid faculty, same as advisor test)
    if ($this->committee[0]->id == "") $missing["committee members"] = "faculty";

    // at least one research field (filled out, not blank)
    if (!count($this->researchfields) ||
	$this->researchfields[0]->id == "" || $this->researchfields[0]->topic == "") {
      $missing["proquest research fields"] = "researchfield";
    }
    // fixme: check if there are too many? app should not let them set too many

    // author's department 
    if ($this->department == "") $missing["program"] = "program";

    // abstract
    if ($this->abstract == "")   $missing["abstract"] = "abstract";

    // table of contents
    if ($this->tableOfContents == "")  $missing["table of contents"] = "contents";

    // keywords
    if (!count($this->keywords) || $this->keywords[0]->topic == "")
      $missing["keywords"] = "record";
    
    // other required fields?
    // genre/etd type (?)
    // degree
    
    return $missing;
  }

  function hasCopyright() {
    return ($this->copyright != "");
    //    return preg_match("/applying for copyright\? (yes|no)/", $this->copyright);
  }

  function hasEmbargoRequest() {
    return ($this->embargo_request != "");
  }

  function hasSubmissionAgreement() {
    return ($this->rights != "");
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
}

