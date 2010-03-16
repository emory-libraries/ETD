<?php
require_once("models/mods.php");

require_once("models/esdPerson.php");

/**
 * ETD extension of MODS XmlObject / Fedora Foxml datastream
 * @category Etd
 * @package Etd_Models
 * @subpackage Etd
 *
 * @property string $author
 * @property string $department author affiliation
 * @property array $chair array of mods_name with role 'Thesis Advisor'
 * @property array $committee array of mods_name with description 'Emory Committe Member'
 * @property array $nonemory_committee array of mods_name with description 'Non-Emory Committe Member'
 * @property array $researchfields array of etdmods_subject with authority 'proquestresearchfield'
 * @property array $keywords array of etdmods_subject with authority 'keyword'
 * @property string $pages physicalDescription/extent
 * @property etd_degree $degree mods:extension with etd-ms degree information
 * @property string $copyright administrative note regarding copyright
 * @property string $embargo_request administrative note regarding embargo request
 * @property string $embargo restriction on access
 * @property string $embargo_end  originInfo/dateOther with type 'embargoedUntil'
 * @property string $embargo_notice administrative note regarding embargo notice
 * @property string $pq_submit administrative note regarding ProQuest submission
 * @property string $rights alternate mapping to useAndReproduction
 * @property string $ark identifier with type 'ark'
 * @property string $identifier identifier with type 'uri'
 * @property string $genre genre with authority 'aat'
 * @property string $marc_genre genre with authority 'marc'
 */
class etd_mods extends mods {

  const ETDMS_NS = "http://www.ndltd.org/standards/metadata/etdms/1.0/";

  const EMBARGO_MIN = 0;
  const EMBARGO_NONE = 0;
  const EMBARGO_FILES = 1;
  const EMBARGO_TOC = 2;
  const EMBARGO_ABSTRACT = 3;
  const EMBARGO_MAX = 3;
  private $embargo_name_map;

  /**
   * submission fields that are available in mods (could be required or optional)
   * @var array
   */
  public $available_fields;
  //  protected $required_fields;
  //  protected $optional_fields;

  // add etd-specific mods mappings
  protected function configure() {
    $this->available_fields = array("title",
				    "author",
				    "program",
				    "chair",
				    "committee members",
				    "researchfields",
				    "keywords",
				    "degree",
				    "language",
				    "abstract",
				    "table of contents",
				    "embargo request",
				    "submission agreement",
				    "send to ProQuest",
				    "copyright");

    $this->embargo_name_map = array(etd_mods::EMBARGO_FILES => "files",
                                    etd_mods::EMBARGO_TOC => "toc",
                                    etd_mods::EMBARGO_ABSTRACT => "abstract");
    
    parent::configure();

    $this->addNamespace("etd", etd_mods::ETDMS_NS);
    
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
    $this->xmlconfig["degree_grantor"] = array("xpath" =>
					       "mods:name[@type='corporate'][mods:role/mods:roleTerm='Degree grantor']",
					       "class_name" => "mods_name");	
    
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
    $this->xmlconfig["identifier"] = array("xpath" => "mods:identifier[@type='uri']");

    // only use aat genre for normal usage; add mapping for marc-specific genre term
    $this->xmlconfig["genre"] = array("xpath" => "mods:genre[@authority='aat']");
    $this->xmlconfig["marc_genre"] = array("xpath" => "mods:genre[@authority='marc']");
    
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

  /**
   * add new research field by text + id
   * @param string $text
   * @param string $id optional
   */
  public function addResearchField($text, $id = "") {
    $this->addSubject($text, "researchfields", "proquestresearchfield", $id);
  }

  /**
   * add a new keyword
   * @param string $text keyword
   */
  public function addKeyword($text) {
    $this->addSubject($text, "keywords", "keyword");
  }
  
  /**
   * add subject/topic pair - used for adding both keywords & research fields
   * @param string $text
   * @param string $mapname keywords or researchfields
   * @param string $authority optional, defaults to none
   * @param string $id optional, defaults to none
   */
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
      $this->domnode->appendChild($subject);
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
      $newnode = $this->domnode->appendChild($newnode);
    

    $this->update();
  }


  /**
   * set author name from esd person
   * @param esdPerson $person
   */
  public function setAuthorFromPerson(esdPerson $person) {
    $this->setNameFromPerson($this->author, $person, true);
  }

  /**
   * generic function to set name fields from an esdPerson object
   * @param mods_name $name
   * @param esdPerson $person
   * @param bool $preserve_aff preserve affiliation? optional, defaults to false
   */
  private function setNameFromPerson(mods_name $name, esdPerson $person, $preserve_aff = false) {
    $name->id    = trim($person->netid);
    $name->last  = trim($person->lastname);
    $name->first = trim($person->name);	// directory name OR first+middle
    $name->full  = trim($person->lastnamefirst);

    // remove any prior affiliation so people and affiliations don't get mixed up
    if (isset($name->affiliation) && !$preserve_aff) {
      $name->domnode->removeChild($name->map["affiliation"]);
      $this->update();
    }
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

  /**
   * remove all non-Emory committee members  (use before re-adding them)
   */
  public function clearNonEmoryCommittee() {
    $nodelist = $this->xpath->query("//mods:name[mods:description='Non-Emory Committee Member']");
    for ($i = 0; $i < $nodelist->length; $i++) {
      $node = $nodelist->item($i);
      $node->parentNode->removeChild($node);
    }
    $this->update();
  }

  /**
   * set committee member affiliation by id
   * @param string $id
   * @param string $affiliation
   * @param string $type optional, defaults to committee
   */
  public function setCommitteeAffiliation($id, $affiliation, $type = "committee") {
    foreach ($this->{$type} as $member) {
      if ($member->id == $id) {
	if (isset($member->affiliation)) {
	  $member->affiliation = $affiliation;
	} else {
	  $newnode = $this->dom->createElementNS($this->namespace, "mods:affiliation",
						 $affiliation);
	  $member->domnode->appendChild($newnode);
	}
      }
    }
    $this->update();
  }

  
  /**
   * set all research fields from an array, overwriting any currently set fields
   * and adding new fields as necessary
   * @param array $values associative array of research field id => name
   */
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
  /**
   * remove a research field by id
   * @param string|int $id
   */
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

  /**
   * find the index for a research field by id
   * @param string|int $id
   */
  public function researchFieldIndex($id) {
    for ($i = 0; count($this->researchfields); $i++) {
      $field = $this->researchfields[$i];
      if ($field->id == $id)
	return $i;
    }
  }
  /**
   * check by id if researchfield is present
   * @param string|int $id
   * @return bool
   */
  public function hasResearchField($id) {
    foreach ($this->researchfields as $field) {
      if ($field->id == $id)
	return true;
    }
    return false;
  }

  /**
   * add mods:note at end of document
   * @param string $text text of the note to add
   * @param string $type note type (set in mods note type attribute)
   * @param string $id note id (set in mods note id attribute)
   */
  public function addNote($text, $type, $id) {
    $note = $this->domnode->appendChild($this->dom->createElementNS($this->namespaceList["mods"], "mods:note", $text));
    $note->setAttribute("type", $type);
    $note->setAttribute("ID", $id);
    $this->update();
  }

  /**
   * what is the requested embargo level?
   * @return int|null null if not set
   */
  public function embargoRequestLevel() {
    $embargo = $this->embargo_request;

    if (strpos($embargo, "no") === 0)
      return etd_mods::EMBARGO_NONE;
    else if (strpos($embargo, "yes") === 0) {
      $highest = etd_mods::EMBARGO_FILES; // the default if no ":"
      if ((strlen($embargo)) >= 4 && ($embargo[3] == ":")) {
        $level_bits = explode(",", substr($embargo, 4));
        foreach ($level_bits as $level_bit) {
          $level = array_search($level_bit, $this->embargo_name_map);
          if ($level > $highest)
            $highest = $level;
        } // foreach
      } // if ":"
      return $highest;
    } else { // neither "yes" nor "no" {
      // FIXME: "" is legal, but should we throw an exception for other values?
      return NULL;
    }
  }

  /**
   * check that embarge request level is valid
   * @param int $level
   * @return bool
   */
  public function validEmbargoRequestLevel($level) {
    return ($level >= etd_mods::EMBARGO_MIN &&
            $level <= etd_mods::EMBARGO_MAX);
  }

  /**
   * set embargo request
   * @param int $level 
   */
  public function setEmbargoRequestLevel($level) {
    if ($level == etd_mods::EMBARGO_NONE) {
      $this->embargo_request = "no";
    } else {
      $embargo_bits = "";
      for ($i = etd_mods::EMBARGO_FILES; $i <= $level; $i++) {
        $embargo_bits .= ",";
        $embargo_bits .= $this->embargo_name_map[$i];
      }
      $this->embargo_request = "yes:" . substr($embargo_bits, 1);

      //TOC and ABSTRACT should not be stored if embargoed
      if($level == etd_mods::EMBARGO_TOC && $this->isEmbargoed()){
          $this->tableOfContents = "";
      }

      if($level == etd_mods::EMBARGO_ABSTRACT  && $this->isEmbargoed()){
          $this->tableOfContents = "";
          $this->abstract = "";
      }

    }
  }

  /**
   * has the student requested an embargo?
   * @param int $level optional information about a particular embargo level
   * @return bool
   */
  public function isEmbargoRequested($level = null) {
    if (is_null($level)) {
      // is *any* embargo requested? could be "yes" or "yes:foo". "" and "no" are false.
      return (strpos($this->embargo_request, "yes") === 0);
    } else {
      return ($this->embargoRequestLevel() >= $level);
    }
  }

  /**
   * add marc genre to record if not already present
   */
  public function setMarcGenre() {
      if (! isset($this->marc_genre)) {
          $genre = $this->dom->createElementNS(mods::MODS_NS, "mods:genre");
          $genre->setAttribute("authority", "marc");
          $this->domnode->appendChild($genre);
          $this->update();
      }
      $this->marc_genre = "thesis";
  }

  /**
   * set recordIdentifier in MODS, adding recordInfo if necessary
   * @param string $id
   */
  public function setRecordIdentifier($id) {
      if (! isset($this->recordInfo)) {
          $recordInfo = $this->dom->createElementNS(mods::MODS_NS, "mods:recordInfo");
          $this->domnode->appendChild($recordInfo);
          $this->update();
      }
      $this->recordInfo->identifier = $id;
  }

  /**
   * update identifiers & urls
   * - short form ark stored in identifier type 'ark'
   * - resolvable ark stored in identifier type 'uri'
   */
  public function cleanIdentifiers() {
    // if ark starts with http, it is the full url and needs to be updated
    if (strpos($this->ark, "http") === 0) {
        // store full uri identifier
        if (!isset($this->identifier)) {
            // if mods:identifier type uri does not exist, create & add the node
            $newid = $this->dom->createElementNS(mods::MODS_NS, "mods:identifier");
            $newid->setAttribute("type", "uri");
            $this->domnode->appendChild($newid);
            $this->update();
        }
        $this->identifier = $this->ark;
        // parse ark and set as ark identifier
        if (Zend_Registry::isRegistered("persis")) {
            $persis =  Zend_Registry::get("persis");
        } elseif (Zend_Registry::isRegistered("persis-config")) {
            $persis = new Emory_Service_Persis(Zend_Registry::get("persis-config"));
        } else {
            throw new Exception("Neither persis nor persis-config is registered, cannot parse ARK");
        }
        list($nma, $naan, $noid) = $persis->parseArk($this->ark);
        $this->ark = "ark:/" . $naan . "/" . $noid;
    }
  }    

  /**
   * remove a field from the xml
   * @param string $mapname configured field name (one of the magic properties)
   */
  public function remove($mapname){
    if (!isset($this->{$mapname})) {
      trigger_error("Cannot remove '$mapname' - not mapped", E_USER_WARNING);
      return;
    }
    /*    for ($i = 0; $i < $nodelist->length; $i++) {
      $node = $nodelist->item($i);      
      $node->parentNode->removeChild($node);*/

    if ($this->map[$mapname] instanceof DOMElementArray ||
      	isset($this->xmlconfig[$mapname]["is_series"]) && $this->xmlconfig[$mapname]["is_series"]) {
      foreach ($this->map[$mapname] as $el) {
	if ($el instanceof XmlObject) 
	  $el->domnode->parentNode->removeChild($el->domnode);
	else
	  $el->parentNode->removeChild($el->domnode);
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
   * @param array $required list of required fields
   * @return boolean ready or not
   */
  public function readyToSubmit(array $required) {
    // if anything is missing, record is not ready to submit
    if (count($this->checkFields($required))) return false;

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
   * check a list of fields; returns an array with problems, missing data
   * @param array of fields to check (e.g., list of required or optional fields)
   * @return array associative array of missing fields with the action where they are edited
   */
  public function checkFields(array $fields) {
    $missing = array();

    // check everything that is specified as required 
    foreach ($fields as $field) {
      if (! $this->isComplete($field)) $missing[] = $field;
    }
    // NOTE: key is  missing field, value is edit action

    return $missing;
  }

  /**
   * check all available fields
   * @see etd_mods::checkFields
   * @return array 
   */
  public function checkAllFields() {
    return $this->checkFields($this->available_fields);
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
      return (isset($this->chair[0]) && $this->chair[0]->id != "");
    case "committee members":
      // complete if there is at least one committee member (valid faculty, same as chair test)
      return (isset($this->committee[0]) && $this->committee[0]->id != "");
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

  /**
   * get the display label for required/optional fields
   * @param string $field field name
   * @return string label
   */
  public function fieldLabel($field) {
    switch ($field) {
    case "chair":
      return "committee chair";
    case "researchfields":
      return "ProQuest research fields";

      // in most cases, field = label
    case "author":
    case "program":
    case "committee members":
    case "keywords":
    case "language":
    case "table of contents":
    case "embargo request":
    case "submission agreement":
    case "send to ProQuest":
    case "copyright":
    case "title":
    case "abstract":
    case "degree":
      return $field;
    }
  }

  

  /**
   * specialized version of check required - disregards rights completion status
   * FIXME: no longer usable?  how to handle this?
   * @return bool
   */
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

  /**
   * has copyright request been set?
   * @return bool
   */
  function hasCopyright() {
    return (isset($this->copyright) && $this->copyright != "");
    //    return preg_match("/applying for copyright\? (yes|no)/", $this->copyright);
  }

  /**
   * has embargo request been set?
   * @return bool
   */
  function hasEmbargoRequest() {
    return (isset($this->embargo_request) && $this->embargo_request != "");
  }

  /**
   * has submission agreement been agreed to?
   * @return bool
   */
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
  
  /**
   * is submit to proquest set (yes/no)
   * @return bool
   */
  public function hasSubmitToProquest() {
    return (isset($this->pq_submit) && $this->pq_submit != "");
  }

  /**
   * is the record set to be submitted to proquest?
   * @return bool
   */
  public function submitToProquest() {
    return ($this->degree->name == "PhD" || $this->pq_submit == "yes");
  }

  /**
   * is this record embargoed?
   * returns true if embargo end date is set and after current time
   * @return boolean
   */
  public function isEmbargoed() {
    if ($this->embargo_end)
      return (strtotime($this->embargo_end, 0) > time());
    else	// no embargo date defined - not (yet) embargoed
      return false;
  }

}


/**
 * ProQuest fields are stored as subjects with a numerical id, but this
 * is not valid xml; intercept the get/and set calls on the id of the
 * mods_subject to add the id to the xml but not show to the user
 * @package Etd_Models
 * @subpackage Etd
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


/**
 *  ETD-MS degree XmlObject
 * @package Etd_Models
 * @subpackage Etd
 *
 * @property string $name degree name
 * @property string $level degree level
 * @property string $discipline
 */
class etd_degree extends modsXmlObject {
  protected $namespace = etd_mods::ETDMS_NS;
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

