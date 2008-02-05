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

  public function pid() { return $this->PID; }
  public function status() { return $this->status; }
  public function title() { return $this->title; }
  public function author() { return $this->author; }
  public function program() { return $this->program; }
  public function subfield() { return isset($this->subfield) ? $this->subfield : ""; }
  public function advisor() { return $this->advisor; }
  public function committee() { return $this->committee; }
			  // how to handle non-emory committee?
  	// dissertation/thesis/etc
  public function document_type() { return $this->document_type; }
  public function language() { return $this->language; }
  public function year() { return $this->year; }
  public function _abstract() { return $this->abstract; }
  public function tableOfContents() { return $this->tableOfContents; }
  public function num_pages() { return $this->num_pages; }
  
  public function keywords() { return  $this->keyword; }	//array
  public function researchfields() { return $this->subject; } 	//array


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