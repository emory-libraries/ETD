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
     * Action to render gradData date selection form
     *  Start of year is 12/31 of last year, end is 8/31 of current year
     */
    public function gradDataAction(){
         if (!$this->_helper->access->allowedOnEtd("manage")) {return false;}
        // academic start and end months
        $acStart="Dec 31";
        $acEnd="Aug 31";
        $numYears=5;  //number of years to include
        $curDate=strtotime("now"); //current date
                
        //Create first and thus default choice
        //We are looking for completed yeears only!
        //If the report is run durring an academic year we get the most renct complted year: December from 2 years ago and  August from 1 yer ago
        //If it is betwen the end of one and the start of the other: we get Decenber for previous year and August for curent your
        if($curDate < strtotime("$acEnd +1 day", $curDate)){
            $startDate = strtotime("$acStart -2 years" , $curDate);
            $endDate = strtotime("$acEnd -1 year" , $curDate);
        }
        else{
            $startDate = strtotime("$acStart -1 year" , $curDate);
            $endDate = strtotime($acEnd, $curDate);
        }

        $options[date("Ymd", $startDate).":".date("Ymd", $endDate)]=date("Y", $startDate) . " - " . date("Y", $endDate);

        for($i=0; $i < $numYears; $i++){
            $startDate=strtotime("-1 year" , $startDate);
            $endDate=strtotime("-1 year" , $endDate);
            $options[date("Ymd", $startDate).":".date("Ymd", $endDate)]=date("Y", $startDate) . " - " . date("Y", $endDate);
        }

        //#####DEGUG######
        $this->view->curDate=date("Y-m-d", $curDate);
        $this->view->curStart=date("Y-m-d", $startDate);
        $this->view->curEnd=date("Y-m-d", $endDate);
        $this->view->options=$options;
        //################



        /*
        //get dates where submissions exist
        $field = "dateIssued";
        $solr = Zend_Registry::get('solr');
        $results = $solr->browse($field);
        $dates = array_keys($results->facets->$field);
        $this->view->dates=$results->facets->$field; //DEBUG
        */


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
