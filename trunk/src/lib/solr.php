<?php

  // minimal solr query ... abstract better to search by field, do
  // facets, limit result set, etc.

class solr {

  private $baseurl;
  private $facet_fields;
  private $facet_limit;
  
  function __construct($hostname, $port, $path = "solr/select") {
    $this->baseurl = "http://$hostname:$port/$path";

    $this->facet_fields = array();
    $this->facet_limit = 5;	// is this reasonable?
  }

  function query($queryString, $start = null, $max = null) {	// solr defaults to 1, 10
    $url = $this->baseurl . "?q=$queryString";
    if ($start !== null) $url .= "&start=$start";
    if ($max !== null) 	$url .= "&rows=$max";
    if(count($this->facet_fields)) {
      $url .= "&facet=true&facet.mincount=1&facet.limit=" . $this->facet_limit;	// sane defaults?
      foreach ($this->facet_fields as $field) {
	$url .= "&facet.field=$field";
      }
    }
    $url .= "&wt=phps";
    $val = file_get_contents($url);
    //    print $val;
    print "<pre>"; print_r(unserialize($val)); print "</pre>";
    return unserialize($val);
  }

  function addFacets(array $field) {
    foreach ($field as $f) 
      array_push($this->facet_fields, $f);
  }

  function clearFacets() {
    unset($this->facet_fields);
    $this->facet_fields = array();
  }

  // find all terms for a single field
  function browse($field) {
    $this->clearFacets();
    $this->addFacets(array($field));
    $this->facet_limit = -1;	// no limit
    // probably need to add sort option...
    return $this->query("*:*", 0, 0);	// find facets on all records, return none
  }
  
}

function solrQuery($query) {
  $host = "mothra.library.emory.edu";
  $port = "8983";
  $baseurl = "solr/select";
  //  return unserialize(file_get_contents("http://$host:$port/$baseurl?q=$query&facet=true&facet.mincount=1&facet.field=advisor_lastnamefirst&facet.field=year&facet.field=subject_facet&facet.field=author_lastnamefirst&facet.field=committee_lastnamefirst&facet.limit=5&wt=phps"));

  return unserialize(file_get_contents("http://$host:$port/$baseurl?q=*:*&facet=true&facet.mincount=1&facet.field=committee_lastnamefirst&rows=0&wt=phps"));
}


// fixme: could do date faceting... might be cool

?>

