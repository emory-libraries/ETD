<?php

require_once("Emory/Service/Solr.php");


/**
 * customized version of Solr service
 *  - default filter to restrict results to ETD objects only
 *  - convenience function to search published records only
 */
class Etd_Service_Solr extends Emory_Service_Solr {

  public function __construct($hostname, $port, $path = "solr/select") {
    parent::__construct($hostname, $port, $path);
    // default filter query: limit to ETD records
    $this->filter = "contentModel:etd";
  }

  // convenience function to limit query to published records only 
  public function queryPublished($queryString, $start = null, $max = null, $sort = null) {
    $this->addFilter("status:published");
    $this->facet_limit = 5;	// only display top 5 facets
    // FIXME: minimum count ?    
    return $this->query($queryString, $start, $max, $sort);
  }

  public function browse($field, $start = null, $max = null) {
    $this->addFilter("status:published");	// FIXME: can we assume this?
    return parent::browse($field, $start, $max);
  }
  
}

