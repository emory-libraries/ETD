<?php
/**
 * etd interface object for etd08
 * - to ensure compatibility between fedora etd & solr/lucene etd
 *
 * @category Etd
 * @package Etd_Models
 * @subpackage Etd
 */
interface etdInterface extends Zend_Acl_Resource_Interface{

  public function pid();
  public function status();
  public function title();
  public function author();
  public function program();
  public function program_id();
  public function subfield();
  public function subfield_id();
  public function chair();
  public function chair_with_affiliation();
  public function committee();  // array
        // how to handle non-emory committee?
  public function committee_with_affiliation();
  public function document_type();  // dissertation/thesis/etc
  public function language();
  public function degree();
  public function year();
  public function pubdate();
  public function _abstract();
  public function tableOfContents();
  public function num_pages();
  
  public function keywords(); //array
  public function researchfields(); //array
  public function partneringagencies(); //array  
  
  public function ark();

  // allow setting output mode? (html/xml) so e.g. title() can return correct version
  
}
