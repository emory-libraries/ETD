<?php

require_once("models/mods.php");

class etd_mods extends mods {

  protected $etd_namespace = "http://www.ndltd.org/standards/metadata/etdms/1.0/";
  
  // add etd-specific mods mappings
  protected function configure() {
    parent::configure();

    $this->addNamespace("etd", $this->etd_namespace);
    
    $this->xmlconfig["author"] = array("xpath" => "mods:name[mods:role/mods:roleTerm = 'author']",
				       "class_name" => "mods_name");
    $this->xmlconfig["department"] = array("xpath" => "mods:name[mods:role/mods:roleTerm = 'author']/mods:affiliation");
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
    $this->xmlconfig["embargo"] = array("xpath" => "mods:note[@type='admin'][@ID='embargo']");
    
  }
  

  public function __set($name, $value) {
    switch ($name) {
    case "pages":
      $value .= " p."; break;	// value should be passed in as a number (check incoming value?)
    } 
    parent::__set($name, $value);
  }

  public function __get($name) {
    $value = parent::__get($name);
    switch ($name) {
    case "pages":
      $value = str_replace(" p.", "", $value);	// return just the number 
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
    
    $this->map{$mapname}[] = new etdmods_subject($subject, $this->xpath);
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
    for (; isset($this->researchfields[$i]); $i++) {
      $this->removeResearchField($this->researchfields[$i]->id);
    }
  }

  public function removeResearchField($id) {
    // remove the node from the xml dom
    $nodelist = $this->xpath->query("//mods:subject[@authority='proquestresearchfield'][@ID = '$id']");
    for ($i = 0; $i < $nodelist->length; $i++) {
      $node = $nodelist->item($i);      
      $node->parentNode->removeChild($node);
    }

    // remove from the in-memory array
    array_splice($this->map{"researchfields"}, $this->researchFieldIndex($id));
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
    // xml should be valid MODS
    if (! $this->isValid()) {
      // error message?
      return false;
    }      

    // if anything is missing, record is not ready to submit
    if (count($this->checkRequired())) return false;
      
    // all checks passed
    return true;
  }

  /**
   * check required fields; returns an array with problems, missing data
   * @return array missing fields
   */
  public function checkRequired() {
    $missing = array();
    
    // must have a valid advisor - how to tell if valid? username?

    // at least one committee member (valid faculty - same as advisor test?)

    // at least one research field (filled out, not blank)
    if (!count($this->researchfields) ||
	$this->researchfields[0]->id == "" || $this->researchfields[0]->topic == "") {
      $missing[] = "proquest research field (at least one)";
    }
    // fixme: check if there are too many? app should not let them set too many

    // author's department 
    if ($this->department == "") $missing[] = "department";

    // abstract
    if ($this->abstract == "")   $missing[] = "abstract";

    // table of contents
    if ($this->tableOfContents == "")  $missing[] = "table of contents";


    // other required fields?
    // genre/etd type (?)
    // degree
    
    return $missing;
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
    else return $value;
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

