<?php
/**
 * @category Etd
 * @package Etd_Models
 * @subpackage Etd
 */

require_once("api/risearch.php");

require_once("solrEtd.php");
require_once("etd.php");
require_once("stats.php");

/**
 * container object for a group of etds retrieved from Solr, with relevant metadata
 * find functions for retrieving etds
 *
 */
class EtdSet implements Zend_Paginator_Adapter_Interface {
  
  /**
   * @var array of etds objects (class etd or solrEtd)
   */ 
  public $etds;

  /**
   * @var Emory_Service_Solr_Response
   */
  protected $solrResponse;

  protected $options;
  
  // Zend_Paginator_Adapter_Interface variables
  private $total;
  private $query_opts;
  private $query_facets;  
  
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

  /**
   * create a new paginator
   * @param array $query_opts - the options for the query for the paginator query.
   * @param array $query_facets - the facets for the query for the paginator query.
  * @param String $type The type of the paginator query function to be run.
   * @param String $param Parameter to the paginator query function to be run. 
   * @see EtdSet::find
   */
  /**
   * @todo remove the separate findby* functions so the constructor would just take an optional filter
   */  
  public function __construct($query_opts=null, $query_facets=null, $type=null, $param=null, $config=null) {
    $this->query_opts = $query_opts;
    $this->query_facets = $query_facets;  
    $this->type = $type;  
    $this->param = $param;
    $this->config = $config;    
    if (isset($type)) {
      $this->getItems(1, 0);   
    }
  }  
  
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
          trigger_error("Record not found: " . $doc->PID, E_USER_WARNING);
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
  
      } else if ($this->options["return_type"] == "solrEtd") {
        $this->etds[] =  new solrEtd($doc); 
      } else {
        trigger_error("EtdSet return type $return_type unknown", E_USER_ERROR);
      }
    }
  }

  public function __get($name) {
    if (isset($this->solrResponse) && isset($this->solrResponse->$name))
      return $this->solrResponse->$name;
    else trigger_error("Unknown attribute $name", E_USER_NOTICE);
  }

  public function __isset($name) {
    return isset($this->solrResponse->$name);
  }

  
  /**** Zend_Paginator_Adapter_Interface functions ****/  
  
  /**
   * return total number of records
   * @return int
   */
  public function count() {
    return $this->total;
  } 
  
  /**
   * get records for pagination, according to query opts used when creating paginator
   * @param int $start
   * @param int $max
   * @return array
   * @see EtdSet::find
   */
  public function getItems($start, $max) {     
    if (isset($this->query_opts)) {
      $this->query_opts['start'] = $start;
      $this->query_opts['max'] = $max;
      $facets = null; 
      switch ($this->type) {  
        case 'findByDepartment':
          return $this->findByDepartment($this->param, $this->query_opts);
          break; 
        case 'findMostViewed':
          return $this->findMostViewed($this->query_opts);
          break;                               
        case 'findPublished':
          return $this->findPublished($this->query_opts);
          break; 
        case 'findRecentlyPublished':
          return $this->findRecentlyPublished($this->query_opts);
          break;
        case 'findEmbargoed':
          return $this->findEmbargoed($this->query_opts);
          break;
        case 'findExpiringEmbargoes':
          return $this->findExpiringEmbargoes($this->param, $this->query_opts, $this->config);
          break;
        case 'findUnpublishedByOwner':
          return $this->findUnpublishedByOwner($this->param, $this->query_opts);
          break;                     
        case 'find':
          return $this->find($this->query_opts);
          break;
      }
    }
  }


  /**** FIND functions ****/
  
  /**
   * generic etd find with many different parameters
   *
   * @param array $options settings for solr query
   *  - sort   : field to sort on
   *  - start  : where to begin retrieving record set
   *  - max    : maximum number of records to return
   *  - query  : preliminary query to which other values may be added
   *  - AND    : hash of field-value pairs that should be included in the query with AND
   *  - NOT    : hash of field-value pairs that should be included in the query with (AND) NOT
   *  - facets : hash with options for facets
   *  - facets[clear] : if set to true, default facets will be cleared
   *  - facets[limit] : number of facets to return
   *  - facets[mincount] : minimum number of matches for a facet to be included
   *  - facets[add] : array of facets to be added
   *  - return_type : type of etd object to return, one of etd or solrEtd
   *
   * @return EtdSet  ??
   */
  public function find($options) {
    $solr = Zend_Registry::get('solr');

    $start = isset($options['start'])   ? $options['start'] : null;
    $max = isset($options['max'])   ? $options['max']   : null;
    $sort = null;
    if (isset($options['sort'])) {
      switch($options['sort']) {
      case "author": $sort = "author_facet asc"; break;
      case "modified": $sort = "lastModifiedDate desc"; break;
      case "title": $sort = "title_exact asc"; break;
      case "relevance": $sort = "score desc"; break;
      case "year": $sort = "dateIssued asc"; break;
      default:
        // certain searches use custom sorting (e.g., embargoes or finding recently published records)
        $sort = $options['sort'];
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
          // using (...) -- all terms, but not exact phrase "..."
          $query .= $field . ':("' . $value . '")'; 
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
    
    $this->total = $this->solrResponse->numFound;   // set total for pagination   

    return $this->etds;
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
   * find approved records 
   * @param array $options any filter options to be passed to etd find function
   * @return EtdSet
   */
  public function findApproved($options = array()) {
    $options['AND']['status'] = "approved";
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
    $options['NOT']['status'] = "published";    // any status other than published
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
             "add" => array("status",
                "advisor_facet",
                "subfield_facet",
                "year"));
    $options['AND']['program'] = 'program:"' . $dept . '"';
    return $this->find($options);
  }

  /**
   * find records with embargo expiring anywhere from today up to the specified date
   * where an embargo notice has *not* already been sent and
   * there is an embargo approved by the graduate school
   * (the extra checks are to ensure that no records are missed if the cron script fails to run)
   *
   * @param string $date expiration date in YYYYMMDD format (e.g., 60 days from today)
   * @param array $options options as passed to EtdSet::find function
   * @param array $config additional configuration options
   *  - notice_unsent : filter on embargo notice unsent? (default : true)
   *  - exact_date    : true = exact date, false = date range from today (default)
   * @return EtdSet
   */
  public function findExpiringEmbargoes($date, $options = array(), $config = array()) {  
    // date *must* be in YYYYMMDD format  (FIXME: check date format)

    // use default configuration if not specified
    if (!isset($config["notice_unsent"])) $config["notice_unsent"] = true;
    if (!isset($config["exact_date"])) $config["exact_date"] = false;
    
    // search for any records with embargo expiring between now and specified embargo end date
    // where an embargo notice has not been sent and an embargo was approved by the graduate school
    if ($config["exact_date"]) {
      $options["query"] = "date_embargoedUntil:$date";
    } else {
      $options["query"] = "date_embargoedUntil:[" . date("Ymd") . " TO $date]";
    }
    $options["NOT"] = array("embargo_duration" => "0 days");
    $options["AND"]["status"] = "published";
    
    // optionally filter on records where embargo notice has not been sent
    if ($config["notice_unsent"]) $options["NOT"]["embargo_notice"] = "sent";
    
    return $this->find($options);
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
    // sort by publication date with the most recent first
    // (NOTE: this date will be the same for all documents in each semester)
    // secondary sort on last modified, most recent first
    $options["sort"] = "dateIssued desc,lastModifiedDate desc";
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
    $pids = $stats->mostViewed(20);

    // then initialize objects from Solr  (faster than Fedora)
    $options["return_type"] = "solrEtd";
    $pid_filter = array();
    foreach ($pids as $pid) $pid_filter[] = 'PID:"' . $pid . '"';
    $options["query"] = implode(" OR ", $pid_filter);
    // set a default for returning from Solr; will need to modify when filtering by program
    if (!isset($options['max'])) $options['max'] = 10;

    $this->find($options);

    // sort etds according to most viewed (order returned from statistics query)
    $sorted_etds = array();
    foreach ($pids as $pid) {
      foreach ($this->etds as $etd) {
  if ($etd->pid() == $pid) $sorted_etds[] = $etd;
      }
    }
    $this->etds = $sorted_etds;
  }


 
  /**
   * Get totals for etds by status, with optional filter.
   * Result includes all statuses (with zeroes if no matches were found); statuses are
   * returned in the order given by etd_rels getStatusList 
   *
   * @param string solr query to filter results (optional)   
   * @return array of statuses and counts for each
   */
  public function totals_by_status($filter = null) {
    $solr = Zend_Registry::get('solr');
    if ($filter) {
      $solr->addFilter($filter);
    }
    $results = $solr->_browse("status");  

    $totals = array();
    $statuses = $results->facets->status;
    foreach (etd_rels::getStatusList() as $status) {
      $totals[$status] = isset($statuses[$status]) ? $statuses[$status] : 0;
    }
     
    return $totals;
  }

}

