<?php

require_once("Emory/Service/Solr.php");


/**
 * customized version of Solr service 
 */
class Etd_Service_Solr extends Emory_Service_Solr {

  public function __construct($hostname, $port, $path = "solr/select") {
    parent::__construct($hostname, $port, $path);
    // set a default filter query to limit to ETD records
    $this->filter = "contentModel:etd";
  }

  // limit query to published records only 
  public function queryPublished($queryString, $start = 0, $max = 10, $sort = "") {
    $this->addFilter("status:published");
    return $this->query($queryString, $start, $max, $sort);
  }

  public function browse($field) {
    $this->addFilter("status:published");	// FIXME: can we assume this?
    return parent::browse($field);
  }

  
}

