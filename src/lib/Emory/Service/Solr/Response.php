<?php

/**
 * Classes to simplify interacting with serialized php response returned by Solr
 * @author Rebecca Sutton Koeser, May 2008
 * 
 * @category EmoryZF
 * @package Emory_Service
 * @subpackage Emory_Service_Solr
 */
class Emory_Service_Solr_Response {
  public $responseHeader;
  public $start;
  public $rows;
  public $numFound;
  public $docs;
  public $facets;

  public function __construct($response, $format) {

    if ($format == "phps") {	// serialized php
      $data = unserialize($response);
      if (! $data)
	throw new Exception("Problem unserializing result from Solr");
      $this->init_from_array($data);
    } elseif ($format == "json") {	// json 
      $data = json_decode($response);
      if (! $data) 
	throw new Exception("Problem decoding json result from Solr (deeper than 127 elements?)");

      $this->init_from_object($data);
    }
      
  }

  protected function init_from_array(array $data) {
    $this->responseHeader = $data['responseHeader'];

    $this->numFound = $data['response']['numFound'];
    $this->start = $data['response']['start'];

    // Note: 'rows' field is not set in the params or anywhere else in
    // the response if not specified in the query
    if (isset($data['responseHeader']['params']['rows']))
      $this->rows = $data['responseHeader']['params']['rows'];
    else	// this won't always be accurate, but should be sufficient...
      $this->rows = count($data['response']['docs']);
    
    $this->docs = array();
    foreach ($data['response']['docs'] as $doc) 
      $this->docs[] = new Emory_Service_Solr_Response_Document($doc);
    if (isset($data['facet_counts']) && isset($data['facet_counts']['facet_fields']))
      $this->facets = new Emory_Service_Solr_Response_Facets($data['facet_counts']['facet_fields']);
    
  }

  protected function init_from_object(stdclass $data) {
    $this->responseHeader = $data->responseHeader;

    $this->numFound = $data->response->numFound;
    $this->start = $data->response->start;

    // Note: 'rows' field is not set in the params or anywhere else in
    // the response if not specified in the query
    if (isset($data->responseHeader->params->rows))
      $this->rows = $data->responseHeader->params->rows;
    else	// this won't always be accurate, but should be sufficient...
      $this->rows = count($data->response->docs);
    
    $this->docs = array();
    foreach ($data->response->docs as $doc) 
      $this->docs[] = new Emory_Service_Solr_Response_Document($doc);
    if (isset($data->facet_counts) && isset($data->facet_counts->facet_fields))
      $this->facets = new Emory_Service_Solr_Response_Facets($data->facet_counts->facet_fields);
  }
  
}

/**
 * simple object interface for response document returned by Solr
 * @package Emory_Service
 * @subpackage Emory_Service_Solr
 */
class Emory_Service_Solr_Response_Document implements Iterator {
  private $data;

  public function __construct($data) {
    if (is_array($data)) 
      $this->data = $data;
    else {
      $this->data  = array();
      foreach ($data as $key => $value){
	$this->data[$key] = $value;
      }
	
    }
  }

  public function __get($name) {
    if (isset($this->data[$name])) return $this->data[$name];
    else trigger_error("No data for $name", E_USER_NOTICE);
  }

  /** iterator functions - makes internal, dynamic data array visible, usable inside foreach */
  public function rewind() { reset($this->data); }
  public function current(){ return current($this->data);  }
  public function key()    { return key($this->data); }
  public function next()   { return next($this->data); }
  public function valid()  { return $this->current() !== false; }

}

/**
 * simple object interface for facets returned by Solr
 * @package Emory_Service
 * @subpackage Emory_Service_Solr
 */
class Emory_Service_Solr_Response_Facets implements Iterator {
  private $data;

  public function __construct($data) {
    if (is_array($data)) 
      $this->data = $data;
    else {
      $this->data  = array();
      // Note: when using json_decode, facet results come in as a numbered array,
      // with a facet value in one entry and the count for that value in the next, e.g.
      // Array ( [0] => Chemistry, [1] => 8 [2] => Biology, [3] => 8)
      
      foreach ($data as $key => $values){
	$facets = array();
	// convert numbered array into the facet => count array we actually want
	for ($i = 0;  $i < count($values); $i+=2) {
	  $facets[$values[$i]] = $values[$i+1];
	}
	$this->data[$key] = $facets;
      }
    }
  }
  
  /**
   * magic get function so facets can be referenced by name
   * also checks for name with _facet appended (secondary)
   */
  public function __get($name) {
    if (isset($this->data[$name])) return $this->data[$name];
    elseif (isset($this->data[$name . "_facet"])) return $this->data[$name . "_facet"];
    else trigger_error("No data for $name", E_USER_NOTICE);
  }

  /**
   * magic isset function for referencing facets by name
   * also looks for name with _facet appended
   */
  public function __isset($name) {
    return (isset($this->data[$name])) || isset($this->data[$name . "_facet"]);
  }

  /**
   * check if at least one facet has at least one value (they could all be empty
   * @return boolean
   */
  public function hasValues() {
    foreach ($this->data as $name => $value) {
      if (count($value)) return true;
    }
    return false;
  }


  /** iterator functions - makes internal, dynamic data array visible, usable inside foreach */

  public function rewind() { reset($this->data); }
  public function current(){ return current($this->data);  }
  public function key()    { return key($this->data); }
  public function next()   { return next($this->data); }
  public function valid()  { return $this->current() !== false; }
  
}
