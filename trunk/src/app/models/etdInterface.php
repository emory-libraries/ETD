<?php

// etd interface object for etd08 - to ensure compatibility between fedora etd & solr/lucene etd
interface etdInterface extends Zend_Acl_Resource_Interface{

  public function pid();
  public function status();
  public function title();
  public function author();
  public function program();
  public function advisor();
  public function committee();	// array
			  // how to handle non-emory committee?
  public function document_type();	// dissertation/thesis/etc
  public function language();
  public function year();
  public function _abstract();
  public function tableOfContents();
  public function num_pages();
  
  public function keywords();	//array
  public function researchfields();	//array


  // allow setting output mode? (html/xml) so e.g. title() can return correct version
  
}
