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
    
    // note: not (currently) using mods_subject class for research fields & keywords
    $this->xmlconfig["researchfields"] = array("xpath" =>
					       "mods:subject[@authority='proquestresearchfield']",
					       "is_series" => true, "class_name" => "mods_subject");
    $this->xmlconfig["keywords"] = array("xpath" => "mods:subject[@authority='keyword']",
					 "is_series" => true, "class_name" => "mods_subject");
    $this->xmlconfig["pages"] = array("xpath" => "mods:physicalDescription/mods:extent");

    $this->xmlconfig["degree"] = array("xpath" => "mods:extension/etd:degree", "class_name" => "etd_degree");
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
	$subject->setAttribute("ID", $id);
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
    
    $this->map{$mapname}[] = new mods_subject($subject, $this->xpath);
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

