<?php

require_once("models/etd.php");

class ReportController extends Etd_Controller_Action {
	protected $requires_fedora = false;
	protected $params;
	private $etd_pid;
	private $message;

	public function indexAction() {
	}
	 
	public function commencementAction() {
        if (!$this->_helper->access->allowedOnEtd("manage")) {return false;}

		$this->view->title = "Commencement Report";

        //Create dates in query and human formats
        list($startDate, $endDate) = $this->getCommencementDateRange();

        $this->view->startDate = date("Y-m-d", $startDate);
        $this->view->endDate = date("Y-m-d", $endDate);
        $dateRange= "[" . date("Ymd", $startDate) . " TO " . date("Ymd", $endDate) ."]";


		//If Query changes remember to update the query description on in the template
        $optionsArray = array();
		$optionsArray['query'] = "(degree_name:PhD AND (dateIssued:" . $dateRange . ") OR (-dateIssued:[* TO *] AND -status:'inactive'))";
		$optionsArray['sort'] = "author";
		$optionsArray['NOT']['status'] = "draft";
		// show ALL records on a single page 
		$optionsArray['max'] = 1000;
		        
	    $etdSet = new EtdSet();
	    $etdSet->find($optionsArray);
	    $this->view->etdSet = $etdSet;
	
//	    $this->view->list_title = "Found " . count($this->view->etdSet) . " ETDs.";
	    $this->view->list_title = "";
	    //$this->view->list_description = "Commencement Report";
	}

    /**
     * Creates a timestamp for 06-01 of last year and a timestamp of 05-31 of this year
     * Returns both in an Array
     * @return array
     */
	public function getCommencementDateRange() {
        //created dates so they can be reformated for the query and people
        $startDate=mktime(0, 0, 0, 6, 1, (date("Y")-1));
        $endDate=mktime(0, 0, 0, 5, 31, date("Y"));

		return array($startDate, $endDate);
	}
}
