<?php

require_once("api/fedora.php");
require_once("api/risearch.php");

require_once("solrEtd.php");
require_once("etd.php");
require_once("stats.php");

/**
 * container object for a group of etds retrieved from Solr, with relevant metadata
 *
 ************ FIXME:  would it make sense to move the find functions here and make them non-static ?
 */
class EtdSet {
  
  /**
   * @var array of etds objects (class etd or solrEtd)
   */ 
  public $etds;

  /**
   * @var Emory_Service_Solr_Response
   */
  protected $solrResponse;

  protected $options;
  
  /**
   * initialize from a Solr response
   * @param Emory_Service_Solr_Response
   * @param string $return_type type of etd object to initialize (etd or solrEtd); defaults to etd
   */
  /*  public function __construct(Emory_Service_Solr_Response $result, $return_type = "etd") {
    // FIXME: just store all of the solr response and refer to it that way ?
    // stores query, etc. -- everything that would be needed for retrieving the next set of documents
    $this->solrResponse = $result;

    $this->etds = array();
    }*/

  private function initializeEtds() {
    $this->etds = array();

    // default return type
    if (!isset($this->options["return_type"])) $this->options["return_type"] = "etd";

    
    foreach ($this->solrResponse->docs as $doc) {
      if ($this->options["return_type"] == "etd") { 
	try {
	  $etd = new etd($doc->PID);
	  $etd->relevance = $doc->score;
	  $this->etds[] = $etd;
	  
	} catch (FedoraObjectNotFound $e) {
	  trigger_error("Record not found: $pid", E_USER_WARNING);
	} catch (FoxmlBadContentModel $e) {
	  // should only get this if query is bad or (in some cases) when Fedora is not responding
	  trigger_error($doc->PID . " is not an etd", E_USER_NOTICE);

	 //  if user is not authorized to view this ETD just display a warning
	 //  (will display however many records are allowed to be viewed)
         //  Note: the record count will not match what the user sees in this case
	} catch (FedoraAccessDenied $e) {
	  trigger_error("Access Denied for " . $doc->PID, E_USER_WARNING);
	} catch (FedoraNotAuthorized $e) {
	  trigger_error("Not Authorized to view " . $doc->PID, E_USER_WARNING);
	}
	
	// FIXME: catch other errors (access denied, not authorized, etc)
	
	// FIXME: store relevance?  $result_doc->score;
	
      } else if ($this->options["return_type"] == "solrEtd") {
	$this->etds[] =  new solrEtd($doc);	
      } else {
	trigger_error("EtdSet return type $return_type unknown", E_USER_ERROR);
      }
    }
  }

  public function __get($name) {
    if (isset($this->solrResponse->$name)) return $this->solrResponse->$name;
    else trigger_error("Unknown attribute $name", E_USER_NOTICE);
  }

  public function __isset($name) {
    return isset($this->solrResponse->$name);
  }


  /**
   * return the number of the last record in the current set
   * @return int 
   */
  public function currentLast() {
    return min($this->solrResponse->start + $this->solrResponse->rows, $this->solrResponse->numFound); 
  }

  /**
   * return a string with the range ofthe current result set
   * @return string (e.g., 1 - 25)
   */
  public function currentRange() {
    $range = (string)($this->solrResponse->start + 1);
    if ($range != $this->currentLast()) {
      $range .= " - " .  $this->currentLast();
    }
    
    return  $range;
  }

  /**
   * check if there more results than returned in the current set
   * @return boolean 
   */
  public function hasMoreResults() {
    return ($this->solrResponse->rows < $this->solrResponse->numFound);
  }

  /**
   * check if there are results in the current set
   * @return boolean
   */
  public function hasResults() {
    return (count($this->etds) > 0);
  }


  /**
   * build an array of human-readable ranges for the result sets for this query
   * @return array (key is start number, value is end value, e.g. 1 => 10, 11 => 15)
   */
  public function resultSets() {
    $results = array();
    for ($i = 1; $i < $this->solrResponse->numFound; $i += $this->solrResponse->rows) {
      $current_end = min($this->solrResponse->numFound, $i + $this->solrResponse->rows - 1);
      $results[$i] = $current_end;
    }

    return $results;
  }

  /**
   * get the next set of results from the last query and initialize etds
   */
  public function next() {
    $this->options["start"] = $this->options["start"] + $this->options["max"];
    $this->find($this->options);
  }



  /**** FIND functions ****/

  
  /**
   * generic etd find with many different parameters
   *
   * @param array $options settings for solr query
   *   sort   : field to sort on
   *   start  : where to begin retrieving record set
   *   max    : maximum number of records to return
   *   query  : preliminary query to which other values may be added
   *   AND    : hash of field-value pairs that should be included in the query with AND
   *   NOT    : hash of field-value pairs that should be included in the query with (AND) NOT
   *   facets : hash with options for facets
   *		clear - if set to true, default facets will be cleared
   * 		limit - number of facets to return
   *		mincount - minimum number of matches for a facet to be included
   * 		add - array of facets to be added
   *   return_type : type of etd object to return, one of etd or solrEtd
   *
   * @return EtdSet  ??
   */
  public function find($options) {
    $solr = Zend_Registry::get('solr');

    $start = isset($options['start']) 	? $options['start'] : null;
    $max = isset($options['max']) 	? $options['max']   : null;
    $sort = null;
    if (isset($options['sort'])) {
      switch($options['sort']) {
      case "author": $sort = "author_facet asc"; break;
      case "modified": $sort = "lastModifiedDate desc"; break;
      case "title": $sort = "title_exact asc"; break;
      case "relevance": $sort = "score desc"; break;
      }
    }

    // preliminary starting query, if set; otherwise return all documents
    //    $query = isset($options['query']) ? $options['query'] : "*:*";
    $query = isset($options['query']) ? $options['query'] : "";

    foreach (array("AND", "OR", "NOT") as $op) {
      if (isset($options[$op])) {
	// field name should match solr index name
	foreach ($options[$op] as $field => $value) {
	  // NOTE: when combined, NOT fields are prefixed with AND
	  // (otherwise Solr may assume OR depending on configuration, with unexpected results)
	  if ($op == "NOT") $prefix = "AND";
	  else $prefix = "";

	  // if query is empty, first 'AND' or 'OR' is not needed; NOT must always be used
	  if (! empty($query) || $op == "NOT") $query .= " $prefix $op ";
	  $query .= $field . ':"' . $value . '"'; 
	}
      }
    }
	
    if ($query == "") $query = "*:*";

    // facet configuration
    if (isset($options['facets'])) {
      if (isset($options['facets']['clear']) && $options['facets']['clear']) $solr->clearFacets();
      if (isset($options['facets']['mincount'])) $solr->setFacetMinCount($options['facets']['mincount']);
      if (isset($options['facets']['add'])) $solr->addFacets($options['facets']['add']);
      if (isset($options['facets']['limit'])) $solr->setFacetLimit($options['facets']['limit']);
    }

    //    print $query . "\n";
    //    if (!isset($options["return_type"])) $options["return_type"] = "etd";

    $this->solrResponse = $solr->query($query, $start, $max, $sort);

    // save options in case of repeat query and for initialization return type
    $this->options = $options;

    $this->initializeEtds();

  }

  /** customized find functions -- wrappers to generic find **/

  /**
   * find published records 
   * @param array $options any filter options to be passed to etd find function
   * @return EtdSet
   */
  public function findPublished($options = array()) {
    $options['AND']['status'] = "published";
    return $this->find($options);
  }

  
  /**
   * find unpublished records by author's netid
   * @param string $username netid of user who created the record
   * @param array $options any filter options to be passed to etd find function
   * @return EtdSet
   */
  public function findUnpublishedByOwner($username, $options = array()) {
    $options['AND']['ownerId'] = strtolower($username);
    $options['NOT']['status'] = "published";		// any status other than published
    return $this->find($options);
  }

  /**
   * find records by program - used for program coordinator view
   * (wrapper for generic etd find with some customized settings)
   *
   * @param string $dept department/program
   * @param int $start starting record (optional, default:0)
   * @param int $max maximum number of records to return at once (optional, default:25)
   * @param array $opts optional filters for query (e.g., facets)
   * @param int $total passed by reference - total records found
   * @param array $facets passed by reference - Solr facets found
   * @return EtdSet
   */
  public function findByDepartment($dept, $options) {
    $options['facets'] = array("clear" => true,    // clear all default filters 
			       "mincount" => 1,    // display even single facets
			       // add the relevant facets-- status, advisor, subfield
			       "add" => array("status", "advisor_facet", "subfield_facet"));
    $options['AND']['program_facet'] = $dept;
    return $this->find($options);
  }

  /**
   * find records with embargo expiring anywhere from today up to the specified date
   * where an embargo notice has *not* already been sent and
   * there is an embargo approved by the graduate school
   * (the extra checks are to ensure that no records are missed if the cron script fails to run)
   *
   * @param string $date expiration date in YYYYMMDD format (e.g., 60 days from today)
   * @return EtdSet
   */
  public function findExpiringEmbargoes($date, $options = array()) {
    // date *must* be in YYYYMMDD format	(FIXME: check date format)
    
    // search for any records with embargo expiring between now and specified embargo end date
    // where an embargo notice has not been sent and an embargo was approved by the graduate school
    $options["query"] = "date_embargoedUntil:[" . date("Ymd") . " TO $date]";
    $options["NOT"] = array("embargo_notice" => "sent", "embargo_duration" => "0 days");
    $options["AND"]["status"] = "published";
    
    return $this->find($options);
    // FIXME: add paging-- need a way to get all results
  }

  /**
   * find embargoed records - has non-zero embargo and that is not yet expired
   * @param array $options options as passed to etd find function
   * @return EtdSet
   */
  public function findEmbargoed($options) {
    $options["query"] = "date_embargoedUntil:[" . date("Ymd") . "TO *] NOT embargo_duration:(0 days)";
    $options["sort"] = "date_embargoedUntil asc";
    return $this->find($options);
  }

  /**
   * find the most recently published records
   * @param array $options options as passed to etd find function
   * @return EtdSet
   */ 
  public function findRecentlyPublished($options) {
    $options["AND"]["status"] = "published";
    $options["sort"] = "dateIssued desc";	// date published, most recent first
    return $this->find($options);
  }


  /**
   * find the most viewed records (= abstract views in statistics);
   * initializes etds as solrEtd for performance reasons
   * 
   * @param array $options
   */
  public function findMostViewed($options = array()) {
    // get the most viewed pids from statistics db
    $stats = new StatObject();
    $pids = $stats->mostViewed();

    // then initialize objects from Solr  (faster than Fedora)
    $options["return_type"] = "solrEtd";
    $pid_filter = array();
    foreach ($pids as $pid) $pid_filter[] = 'PID:"' . $pid . '"';
    $options["query"] = implode(" OR ", $pid_filter);
    // set a default for returning from Solr; will need to modify when filtering by program
    if (!isset($options['max'])) $options['max'] = 10;

    return $this->find($options);
  }


  
}

