<?php

require_once("models/etd.php");

class ReportController extends Etd_Controller_Action {
	protected $requires_fedora = false;
	protected $params;
	private $etd_pid;
	private $message;

	public function indexAction() {
	}
	 
	public function commencementreportAction() {
		$this->view->title = "Commencement Report";

        //dates in query and human formats
        list($this->view->dateRange, $this->view->lastDate, $this->view->nextDate) = $this->getCommencementDateRange();
		
		$optionsArray = array();
		$optionsArray['query'] = "(degree_name:PhD AND (dateIssued:" . $this->view->dateRange . ") OR (-dateIssued:[* TO *] AND -status:'inactive'))";
		$optionsArray['sort'] = "author";
		$optionsArray['NOT']['status'] = "draft";
		// show ALL records on a single page 
		$optionsArray['max'] = 1000;
		//DEBUG
		$this->view->searchQuery = $optionsArray['query'];

        //remember to update this if the query changes
        $this->view->querySummary="Date Range: " . $this->view->lastDate . " - " . $this->view->nextDate
        . "<br>" . "degree_name = 'PhD'"
        . "<br>" . "status NOT 'inactive' status NOT 'draft'"
        . "<br>" . "row limit 1000"
        ;
  		
		 /*
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
	    $etdSet = new EtdSet();
	    $etdSet->find($optionsArray);
	    $this->view->etdSet = $etdSet;
	
//	    $this->view->list_title = "Found " . count($this->view->etdSet) . " ETDs.";
	    $this->view->list_title = "";
	    $this->view->list_description = "Commencement Report";
	}
	
	public function getCommencementDateRange() {
        //created dates so they can be reformated for the query and people
        $last_date=mktime(0, 0, 0, 6, 1, (date("Y")-1));
        $next_date=mktime(0, 0, 0, 5, 31, date("Y"));

		$last_year_grad_date = date("Ymd", $last_date);
        $next_grad_date = date("Ymd", $next_date);
		
		
        return array(
                        "[" . $last_year_grad_date . " TO " . $next_grad_date ."]", //String formated for query
                        date("Y-m-d", $last_date), //last year's date formated for humans
                        date("Y-m-d", $next_date) //next grad date formated for humans
                    );
	}
}
