<?php

require_once("etdInterface.php");

class solrEtd implements etdInterface {

  public function __construct(array $values) {

    // map lucene fields to etd fields - should be named consistently
    foreach ($values as $key =>  $value) {
      $this->$key = $values[$key];
    }
    // note that facet fields will be ignored here
  }


  // fixme: handle fields that could be empty?

  public function pid() { return $this->getField("PID"); }
  public function status() { return $this->getField("status"); }
  public function title() { return $this->getField("title"); }
  public function author() { return $this->getField("author"); }
  public function program() { return $this->getField("program"); }
  public function subfield() { return $this->getField("subfield"); }
  public function advisor() { return $this->getField("advisor"); }
  public function committee() { return $this->getField("committee", true); }
			  // how to handle non-emory committee?
  	// dissertation/thesis/etc
  public function document_type() { return $this->getField("document_type"); }
  public function language() { return $this->getField("language"); }
  public function year() { return $this->getField("year"); }
  public function pubdate() { return $this->getField("dateIssued"); }
  public function _abstract() { return $this->getField("abstract"); }
  public function tableOfContents() { return $this->getField("tableOfContents"); }
  public function num_pages() { return $this->getField("num_pages"); }
  
  public function keywords() { return $this->getField("keyword", true); }	//array
  public function researchfields() { return $this->getField("subject", true); } 	//array


  private function getField($name, $array = false) {
    if (isset($this->$name))
      return $this->$name;
    elseif (isset($this->{$name . "_facet"}))	// program, keyword, subject
      return $this->{$name . "_facet"};
    else if ($array)
      return array();
    else
      return "";
  }

  // for Zend ACL Resource
  public function getResourceId() {
    if (isset($this->status) && $this->status != "") {
      return $this->status() . " etd";
    } else {
      return "etd";
    }
  }

  
}

?>