<?php

require_once("Emory/Service/Solr/Response.php");

/**
 * @category EmoryZF
 * @package Emory_Service
 * @subpackage Emory_Service_Solr
 */
class SolrException extends Exception {}

/**
 * php class for searching Solr
 *
 * @package Emory_Service
 * @subpackage Emory_Service_Solr
 */
class Emory_Service_Solr {

  /**
   * Base URL for Solr.
   * @var string
   */
  protected $baseurl;
  /**
   * Array of fields to be used as facets
   * @var array
   */
  protected $facet_fields;
  /**
   * Number of facets to be returned
   * @var int
   */
  protected $facet_limit;
  /**
   * Minimum number of matches a facet must have to be included
   * @var int
   */
  protected $facet_mincount;
  /**
   * Prefix for facet search (for query suggestor)
   * @var string
   */
  protected $facet_prefix;
  /**
   * Where to begin in facet results (for paging through facets)
   * @var int
   */
  protected $facet_offset;
  /**
   * Filter query
   * @var filter
   */
  protected $filter;
  /**
   * Format of response desired from Solr
   * @var $responseFormat
   */
  protected $responseFormat = "phps";

  /**
   * Configuration settings for connecting to solr
   *
   * @param string $hostname server where Solr is running
   * @param string $port port where Solr is running
   * @param string $path  path for Solr url (default solr/select)
   */
  public function __construct($hostname, $port, $path = "solr", $options = array()) {
    $this->baseurl = "https://$hostname:$port/$path/select";	// select is for searching

    $this->facet_fields = array();
    $this->facet_limit = 5;	// reasonable defaults (?)
    $this->facet_mincount = 2;

    // configure Solr response format; default to json (must be enabled in Solr)
    if (isset($options['responseFormat'])) {
      $this->responseFormat = $options['responseFormat'];
    }

    $this->filter = "";
  }

  /**
   * Add facets to be returned on a query
   * @param array $fields field names
   */
  public function addFacets(array $fields) {
    foreach ($fields as $f)
      array_push($this->facet_fields, $f);
    return $this;
  }

  /**
   * Remove all configured facet fields
   */
  public function clearFacets() {
    unset($this->facet_fields);
    $this->facet_fields = array();
    return $this;
  }

  /**
   * find all terms for a single field
   * @param string $field browse term
   * @param int $start
   * @param int $max
   */
  public function browse($field, $prefix = "", $start = null, $max = null) {
    $this->clearFacets();
    $this->addFacets(array($field));
    $this->facet_limit = -1;	// no limit
    $this->facet_mincount = 1;	// minimum one match
    $this->facet_prefix = $prefix;
    if ($start != null) $this->facet_offset = $start;		// if not set, defaults to 0
    if ($max != null)   $this->facet_limit = $max;
    // probably need to add sort option...
    return $this->query("*:*", 0, 0);	// find facets on all records, return none
  }

  /**
   * auto-suggestor/auto-completer query based on a facet field
   * @param string $string beginning of text to match in facet field
   * @param string $query optional query
   * @return Emory_Service_Solr_Response
   */
  public function suggest($string, $query = "*:*") {
    $this->facet_prefix =  $string;
    $this->facet_mincount = 1;		// any facet with at least one match should be included
    return $this->query($query, 0, 0);
  }

  /**
   * change number of facet terms to be returned when querying
   * @param int $limit number for new facet limit
   */
  public function setFacetLimit($limit) {
    $this->facet_limit = $limit;
    return $this;
  }
  // FIXME: should a default be stored/reverted to?

  /**
   * set minimum count for facets that should be returned
   * @param int $minimum number for new facet minimu
   */
  public function setFacetMinCount($minimum) {
    $this->facet_mincount = $minimum;
    return $this;
  }


  public function addFilter($filterQuery) {
    // if there is already a filter, combine it with the new one so both are in effect
    if ($this->filter != "" && ! preg_match("/^\s*NOT/", $filterQuery) ) $this->filter .= " AND ";
    $this->filter .= " " . $filterQuery;
    return $this;
  }

  /* FIXME: may need this function also; store/revert to a default filter?
   public function clearFilter() {
  }
  */


  // FIXME: should sort still be a parameter here?
  public function query($queryString, $start = null, $max = null, $sort = null) {
    $params = array("q" => $queryString,
        "wt" => $this->responseFormat,
        "fl" => "* score");		// return all stored fields plus relevance score
    if ($this->filter != "") $params["fq"] = $this->filter;
    // pass along optional parameters - otherwise, use Solr defaults
    if (!is_null($start)) $params["start"] = $start;
    if (!is_null($max)) $params["rows"] = $max;
    if (!is_null($sort)) $params["sort"] = $sort;

    // configure parameters for facets if facet fields are defined
    if(count($this->facet_fields)) {
      $params["facet"] = "true";
      $params["facet.mincount"] = $this->facet_mincount;
      $params["facet.limit"] = $this->facet_limit;
      if (is_numeric($this->facet_offset)) $params["facet.offset"] = $this->facet_offset;
      if ($this->facet_prefix) $params["facet.prefix"] = $this->facet_prefix;
      $params["facet.field"] = array();
      foreach ($this->facet_fields as $field) {
  $params["facet.field"][] = $field;
      }
    }

    $val = $this->post($this->baseurl, $params);
    if ($val) {
      // NOTE: in some cases Solr returns an apache error instead of the requested response format
      return new Emory_Service_Solr_Response($val, $this->responseFormat);
    } else {
      // FIXME: better error handling here
      throw new Exception("No response from Solr");
    }
  }

  /**
   * Post a Solr request and return the result
   * Note: using POST  because GET was insufficient for very long queries
   * @param string $url  url
   * @param array $params array of POST parameters
   */
  protected function post($url, $params) {
    $client = new Emory_Http_Client($url,
           array('timeout' => 60)	// increase timeout to 60 seconds (default is 10)
           );
    $client->setParameterPost($params);
    $client->setEncType(Zend_Http_Client::ENC_FORMDATA);
    $client->setMethod(Zend_Http_Client::POST);
    $response = $client->request();
    if ($response->isSuccessful())
      return $response->getRawBody();	// don't do any decoding, etc
    else {
      // FIXME: is this exception-worthy?
      throw new SolrException($response->getMessage());
    }
  }
}
