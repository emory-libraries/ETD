<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

require_once("models/etd.php");
require_once("ofc/php-ofc-library/open-flash-chart.php");

class ReportController extends Etd_Controller_Action {
	protected $requires_fedora = false;
	protected $params;
	private $etd_pid;
	private $message;
	private $chartentity;
	/**
 	* this is only a list of links
 	*/
	public function indexAction() {
	}
	 
	/**
     *This action creates a form to allows a user to select ETDs to be excluded 
     * from the commencement report
     */
    public function commencementReviewAction() {
        if (!$this->_helper->access->allowedOnEtd("manage")) {return false;}

		$this->view->title = "Commencement Report Review";

        //Create dates in query and human formats
        list($startDate, $endDate) = $this->getCommencementDateRange();
        $dateRange= "[" . date("Ymd", $startDate) . " TO " . date("Ymd", $endDate) ."]";


		$optionsArray = array();
		$optionsArray['query'] = "(degree_name:PhD AND (dateIssued:" . $dateRange . ") OR (-dateIssued:[* TO *] AND -status:'inactive'))";
		$optionsArray['sort'] = "author";
		$optionsArray['NOT']['status'] = "draft";
		// show ALL records on a single page 
		$optionsArray['max'] = 1000;
		$optionsArray['return_type'] = "solrEtd";
		        
	    $etdSet = new EtdSet();
	    $etdSet->find($optionsArray);
	    $this->view->etdSet = $etdSet;
	}


    /**
     * This action produces the commencement report and filters out the excluded
     *  pids from the previous form
     */
    public function commencementAction() {
        if (!$this->_helper->access->allowedOnEtd("manage")) {return false;}

		$this->view->title = "Commencement Report";

       //Get the list of PIDs to exclude
       $inputField="exclude";

       if($this->_hasParam($inputField)){
            $exclude = $this->_getParam($inputField);
       }

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
		$optionsArray['return_type'] = "solrEtd";

		        
	    $etdSet = new EtdSet();
	    $etdSet->find($optionsArray);
        
        //remove ETDs by pid or calculate & save grad semester indicator
        foreach($etdSet->etds as $index => $etd){
	  if(is_array($exclude) && in_array($etd->pid(), $exclude)){
               unset($etdSet->etds[$index]);
            } else {
	    	$etdSet->etds[$index]->semester = $this->getSemesterDecorator($etd->pubdate());
            }
        }

	$this->view->etdSet = $etdSet;

	// get dojo cdn from config
	$config = Zend_Registry::get('config');
	$this->view->dojo_config = $config->dojo;
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
        //If it is betwen the end of one and the start of the next: we get Decenber for previous year and August for curent your
        if($curDate < strtotime("$acEnd +1 day", $curDate)){
            $startDate = strtotime("$acStart -2 years" , $curDate);
            $endDate = strtotime("$acEnd -1 year" , $curDate);
        }
        else{
            $startDate = strtotime("$acStart -1 year" , $curDate);
            $endDate = strtotime($acEnd, $curDate);
        }

        $options[date("Ymd", $startDate).":".date("Ymd", $endDate)]=date("Y/m", $startDate) . " - " . date("Y/m", $endDate);

        for($i=0; $i < $numYears; $i++){
            $startDate=strtotime("-1 year" , $startDate);
            $endDate=strtotime("-1 year" , $endDate);
            $options[date("Ymd", $startDate).":".date("Ymd", $endDate)]=date("Y/m", $startDate) . " - " . date("Y/m", $endDate);
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

    /*
     * Action to create CSV file from submitted date range
     */
    public function gradDataCsvAction(){
        if (!$this->_helper->access->allowedOnEtd("manage")) {return false;}

        //get start and end dates from post
        $inputField="academicYear";

        if($this->_hasParam($inputField)){
            list($start, $end) = split(":", $this->_getParam($inputField));
        }
       
        //Query solr
        $optionsArray = array();
		$optionsArray['query'] = "(degree_name:PhD OR degree_name:MS OR degree_name:MA) AND dateIssued:[$start TO $end]";
		$optionsArray['sort'] = "author";
		$optionsArray['max'] = 100;

	    $etdSet = new EtdSet();
	    $etdSet->find($optionsArray);

        //Create HeaderRow for CSV file
        $csvHeaderRow=array("Author Netid", "Author Full Name",
                            "Chair1 Netid", "Chair1 Full Name",
                            "Chair2 Netid", "Chair2 Full Name",
                            "Committee1 Netid", "Committee1 Full Name",
                            "Committee2 Netid", "Committee2 Full Name",
                            "Committee3 Netid", "Committee3 Full Name",
                            "Committee4 Netid", "Committee4 Full Name",
                            "Non-emory Committee1 Full Name", "Non-emory Committee1 Assocation",
                            "Non-emory Committee2 Full Name", "Non-emory Committee2 Assocation",
                            "Non-emory Committee3 Full Name", "Non-emory Committee3 Assocation",
                            "Non-emory Committee4 Full Name", "Non-emory Committee4 Assocation",
                            "Type", "Program", "Publication Date"
                            );

        $data[] = $csvHeaderRow; //add to data array

        //Create data
        foreach($etdSet->etds as $etd){
            //print "<pre>";
            //print_r($etd);
            //print "</pre>";
            $line = array();
            $line[] = $etd->mods->author->id;
            $line[] = $etd->mods->author->full;
            $line=array_merge($line, $this->addCSVFields($etd,  "chair", array("id", "full"), 2));
            $line=array_merge($line, $this->addCSVFields($etd, "committee", array("id", "full"), 4));
            $line=array_merge($line, $this->addCSVFields($etd, "nonemory_committee", array("full", "affiliation"), 4));
            $line[] = $etd->mods->genre;
            $line[] = $etd->program();
            $line[] = $etd->mods->originInfo->issued;

            $data[] = $line;
        }
        
        $this->view->data = $data;

       
       //set HTML headers in response to make output downloadable
       $this->_helper->layout->disableLayout();
       $filename = "GradReport-".date("Ymd", strtotime($start))."-".date("Ymd", strtotime($end)).".csv";
       $this->getResponse()->setHeader('Content-Type', "text/csv");
       $this->getResponse()->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
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


    /*
     * This returns new fields to be added to the current CSV line, from the dataset $etdSet
     * It retreives the fields spracified in $fields from the section of the
     * response specified by $group.  $max limits the number of sets to be added
     * Example:  addCSVFields($etd, "chair", array("id", "full"), 2):
     * Would add $etd->mods->chair->id and $etd->mods->chair->full fields to the
     * CSV line from the first two entries in the $etd->mods->chair array.
     *  @param etdSet $etd - The etd result set
     * @paam string $group - The group of the field that is being added
     * @param array $fields - List of fields from the group
     * @param int $max - Max number of fields to add
     * @return array - The origanal line with new fields added
     */
    public function addCSVFields($etd, $group, $fields, $max){
        for($i = 0; $i < $max; $i++){
            if( isset($etd->mods->{$group}[$i]) ){
	      foreach($fields as $field) {
		$value = $etd->mods->{$group}[$i]->$field;
		// ignore ids with underscores -- some hand-entered non-Emory advisor ids have this
		if ($field == "id" && preg_match("/_/", $value)) $value = "";
		$line[] = $value;
	      }
            }
            else{
                foreach($fields as $field){
                    $line[] = "";
                }
            }
        }
        return $line;
    }


        /**
         * Action to create CSV file with Embargo data
         */
    public function embargoCsvAction(){
      if (!$this->_helper->access->allowedOnEtd("manage")) {return false;}

        //Query solr
        $optionsArray = array();
		$optionsArray['query'] = "(degree_name:PhD OR degree_name:MS OR degree_name:MA)";
		$optionsArray['sort'] = "author";
		$optionsArray['max'] = 300;

	    $etdSet = new EtdSet();
	    $etdSet->findEmbargoed($optionsArray);

        //Create HeaderRow for CSV file
        $csvHeaderRow=array("Author", "Author Email",
                            "Advisor 1", "Advisor 1 Email", "Advisor 2", "Advisor 2 Email",
                            "URL", "Publication Date", "Embargo End Date");

        $data[] = $csvHeaderRow; //add to data array

        //Create data
        foreach($etdSet->etds as $etd){
            $line = array();
            $line[] = $etd->mods->author->full;
            $line[] = $etd->authorInfo->mads->permanent->email;

            //Get advisor and advisor emails
            $max=2;
            for($i = 0; $i < $max; $i++){

            if( isset($etd->mods->chair[$i]) ){
                $line[] = $etd->mods->chair[$i]->full;

                $esd = new esdPersonObject();
                $person = $esd->findByUsername($etd->mods->chair[$i]->id);
		if ($person) $line[] = $person->email;
		else $line[] = "";
            }
            else{
                for($i = 0; $i < $max; $i++){
                    $line[] = "";
                }
            }
        }

            $line[] = $etd->ark();
            $line[] = $etd->mods->originInfo->issued;
	    if (isset($etd->mods->embargo_end)) $line[] = $etd->mods->embargo_end;
	    else $line[] = "";
            

            $data[] = $line;
        }

        $this->view->data = $data;


       //set HTML headers in response to make output downloadable
       $this->_helper->layout->disableLayout();
       $filename = "EmbargoReport.csv";
       $this->getResponse()->setHeader('Content-Type', "text/csv");
       $this->getResponse()->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
 * Function to retun a decorator to be used with the author name
 *
 * @param Date $grad_date - grad date of curent ETD
 * @return String
 */
function getSemesterDecorator($grad_date) {
		$date_grad_date = strtotime($grad_date);
		$decorator = "";
		if(intval(date("m", $date_grad_date))>=1 && intval(date("m", $date_grad_date))<=5) { // SPRING!
			$decorator = "";
		} else if(intval(date("m", $date_grad_date))>5 && intval(date("m", $date_grad_date))<=8) { // SUMMER!
			$decorator = "*";
		} else if(intval(date("m", $date_grad_date))>8 && intval(date("m", $date_grad_date))<=12) { // FALL!
			$decorator = "**";
		}
		return $decorator;
}


/**
 * This function is used to generate page length report by degrees
 */
public function pagelengthbydegreeAction() {
  $this->_setParam("reporttype", "bydegree");
  $this->pagelengthreports();
}

/**
 * This function is used to generate page length report by programs
 */
public function pagelengthbyprogramAction() {
  $this->firstReport = true;
  $programs = $this->progcolls();
  $this->view->collection = $programs;
  $this->pagelengthbyprogramspecificAction();
}

/**
 * It is used to support AJAX when generating page length report by the selected program
 */
public function pagelengthbyprogramspecificAction() {
  $this->_setParam("reporttype", "byprogram");
  $this->pagelengthreports();
}  

public function pagelengthreports() {
  $report_type = $this->_getParam("reporttype", "byprogram");

  $solr = Zend_Registry::get('solr');
  $solr->clearFacets();
  $solr->addFacets(array("num_pages"));
  $solr->setFacetLimit(-1);  // no limit
  $solr->setFacetMinCount(0);        // minimum one match
  $this->view->facets = $result->facets;
  $this->view->title = "Report by number of pages" . $coll;

  $page_len_chart = new open_flash_chart();
  $vector = array();
  $x_label_array = $this->page_length_report_x_array();
  
  if ($report_type == "byprogram") {
    //program report specific 
    $vector = $this->pagelengthbyprogram($solr);
    $progtext = $this->_getParam("nametext", "Humanities");
    $title = new title( "Document Length Report By ". $progtext . " Program");
  }
  if ($report_type == "bydegree") {
    $vector = $this->pagelengthbydegree($solr);
    $title = new title( "Document Length Report by Degrees" );
  } 

  $this->addchartelements($page_len_chart, $vector);
  $x_legend_text = 'Pages';
  $y_legend_text = 'Documents';
  $max_page_num = max($vector["Total"]);
  $this->addchartartifacts($page_len_chart, $x_label_array, $max_page_num, $x_legend_text, $y_legend_text);
  $title->set_style( '{font-size: 14px; color: #333333; font-weight:bold}' );
  $page_len_chart->set_title( $title);
  
//program report specific 
  if ($report_type == "byprogram") {
    $this->sendchart($page_len_chart);  
  }
  if ($report_type == "bydegree") {
    $this->view->flashchart = $page_len_chart;
  }
}

/* it returns a vector for page length report by year*/
public function pagelengthbydegree(&$solr) {
  $response = $solr->query("*:*", 0, 0);
  $response = $solr->query("num_pages:[* TO *]", 0, 0);
  $vector["Total"] = $this->group_page_length($response->facets->num_pages);
  $response = $solr->query("degree_name:B*", 0, 0);
  $vector["Honors Theses"] = $this->group_page_length($response->facets->num_pages);
  $response = $solr->query("degree_name:M*", 0, 0);
  $vector["Masters Theses"] = $this->group_page_length($response->facets->num_pages);
  $response = $solr->query("{!q.op=AND}*:* -degree_name:M* -degree_name:B*", 0, 0);
  $vector["Dissertations"] = $this->group_page_length($response->facets->num_pages);
  return $vector;
}

/* it returns a vector for page length report by program */
public function pagelengthbyprogram(&$solr) {
  $progname = $this->_getParam("programname", "humanities");
  $progtext = $this->_getParam("nametext", "Humanities");
  $this->view->progtext = $progtext;
  $vector = array();

  $totalNum = array();
  $totalNumMs = array();
  $totalNumPhd = array();
  $programs = $this->progcolls("#" . $progname);
  foreach ($programs->members as $member) {
      $progname = str_replace("#", '', $member->id);
      $criteria = sprintf("{!q.op=AND}num_pages:[* TO *] program_facet:%s", $progname);
      $response = $solr->query($criteria, 0, 0);
      $totalNum = $this->combinearrays($response->facets->num_pages, $totalNum);
      $criteria = sprintf("{!q.op=AND}program_facet:%s degree_name:M*", $progname);
      $response = $solr->query($criteria, 0, 0);
      $totalNumMs = $this->combinearrays($response->facets->num_pages, $totalNumMs);
      $criteria = sprintf("{!q.op=AND}program_facet:%s -degree_name:M* -degree_name:B*", $progname);
      $response = $solr->query($criteria, 0, 0);
      $totalNumPhd = $this->combinearrays($response->facets->num_pages, $totalNumPhd);
  }
  $vector["Total"] = $this->group_page_length($totalNum);
  $vector["Masters Theses"] = $this->group_page_length($totalNumMs);
  $vector["Dissertations"] = $this->group_page_length($totalNumPhd);
  return $vector;
}

/* 
 * support functions for page length reports 
 */

/* it groups page lengths into ranges. e.g 101 and 116 should be grouped into 101 - 200 */
private function group_page_length(&$page_lengths) {
  $results = array();
  $results = array_fill(0, 11, 0);
  foreach ($page_lengths as $pl => $count) {
    if ($pl >= 1000) {
      $i = 10;
    } else {
      $i = (int)($pl / 100);
    }
    $results[$i] = $results[$i] + $count;
  }
  return $results;
}

private function page_length_report_x_array() {
  $x_label_array = array();
  $x_label_array[0] = "<100";
  for ($i = 1; $i < 1000; $i += 100) {
      $x_label_array[] = $i . " - " . ($i + 100);
  }
  $x_label_array[10] = ">1000";
  return $x_label_array;
}


/* 
 * embargo flash chart reports
 */

/* It's used to generate embargo duration reports by year */
public function embargobyprogramAction() {
  $programs = $this->progcolls();
  $this->view->collection = $programs;
  $this->firstReport = true;
  $this->embargobyprogramspecificAction();
}  

/**
 * It is used to support AJAX when generating embargo duration report by the selected program
 */
public function embargobyprogramspecificAction() {
  $report_type = $this->_setParam("report_type", "byprogram");
  $this->embargoreports();
}

/* It's used to generate embargo duration reports by year */
public function embargobyyearAction() {
  $solr = Zend_Registry::get('solr');
  $solr->clearFacets();
  $solr->addFacets(array("year"));
  $solr->setFacetLimit(-1);  // no limit
  $solr->setFacetMinCount(0);        // minimum one match
  $result = $solr->query("*:*", 0, 0);
  $this->view->years = $result->facets->year;
  $this->firstReport = true;
  $this->embargobyyearspecificAction();
}  

/**
 * It is used to support AJAX when generating embargo duration report by the selected year
 */
public function embargobyyearspecificAction() {
  $report_type = $this->_setParam("report_type", "byyear");
  $this->embargoreports();
}

public function embargoreports() {
  $report_type = $this->_getParam("report_type", "byprogram");
  
  $solr = Zend_Registry::get('solr');
  $solr->clearFacets();
  $solr->addFacets(array("embargo_duration"));
  $solr->setFacetLimit(-1);  // no limit
  $solr->setFacetMinCount(0);        // minimum one match
  $result = $solr->query("*:*", 0, 0);
  $this->view->title = "Report by Embargo Durations";
  $embargo_chart = new open_flash_chart();
  $embargo_len = $this->embargoduration_x_array();
  $x_label_array = $embargo_len;

  $vector = array(); 
  if ($report_type == "byprogram") {
    $progtext = $this->_getParam("nametext", "Humanities");
    $vector = $this->embargobyprogram($solr);
    $title = new title( "Embargo Duration Report By ". $progtext . " Program");
  } else {
    $useyear = $this->_getParam("year", "2007");
    $vector = $this->embargobyyear($solr);
    $title = new title( "Embargo Duration Report ". $useyear);
  }
  $this->addchartelements($embargo_chart, $vector);
  $x_legend_text = 'Embargo Durations';
  $y_legend_text = 'Documents';
  $max_page_num = max($vector["Total"]);
  $this->addchartartifacts($embargo_chart, $x_label_array, $max_page_num, $x_legend_text, $y_legend_text);
  $title->set_style( '{font-size: 14px; color: #333333; font-weight:bold}' );
  $embargo_chart->set_title( $title);
  $this->sendchart($embargo_chart);  
}

/* it returns a vector for embargo duration report by program */
private function embargobyprogram(&$solr) {
  $progname = $this->_getParam("programname", "humanities");
  $programs = $this->progcolls("#" . $progname);
  foreach ($programs->members as $member) {
    $progname = str_replace("#", '', $member->id);
    $criteria = sprintf("{!q.op=AND} program_facet:%s", $progname);
    $response = $solr->query($criteria, 0, 0);
    $vector["Total"] =  $this->combinearrays($this->mergeblank2zero($response->facets->embargo_duration), $vector["Total"]);
    $criteria = sprintf("{!q.op=AND} program_facet:%s degree_name:M*", $progname);
    $response = $solr->query($criteria, 0, 0);
    $vector["Masters Theses"] =  $this->combinearrays($this->mergeblank2zero($response->facets->embargo_duration), $vector["Masters Theses"]);
    $criteria = sprintf("{!q.op=AND} program_facet:%s -degree_name:M* -degree_name:B*", $progname);
    $response = $solr->query($criteria, 0, 0);
    $vector["Dissertations"] =  $this->combinearrays($this->mergeblank2zero($response->facets->embargo_duration), $vector["Dissertations"]);
  }
  return $vector;
}

/* it returns a vector for embargo duration report by year*/
public function embargobyyear(&$solr) {
  $useyear = $this->_getParam("year", "2007");
  if($useyear == "Overall") {
    $year = "[* TO *]";
  } else {
    $year = $useyear;
  }
  $criteria = sprintf("{!q.op=AND} year:%s", $year);
  $response = $solr->query($criteria, 0, 0);
  $vector["Total"] =  $this->mergeblank2zero($response->facets->embargo_duration);
  $criteria = sprintf("{!q.op=AND} year:%s degree_name:B*", $year);
  $response = $solr->query($criteria, 0, 0);
  $vector["Honors Theses"] = $this->mergeblank2zero($response->facets->embargo_duration);
  $criteria = sprintf("{!q.op=AND}year:%s degree_name:M*", $year);
  $response = $solr->query($criteria, 0, 0);
  $vector["Masters Theses"] = $this->mergeblank2zero($response->facets->embargo_duration);
  $criteria = sprintf("{!q.op=AND}year:%s -degree_name:M* -degree_name:B*", $year);
  $response = $solr->query($criteria, 0, 0);
  $vector["Dissertations"] = $this->mergeblank2zero($response->facets->embargo_duration); 
  return $vector;
}

private function embargoduration_x_array() {
  return array("0 days", "6 months", "1 year", "2 years", "6 years");
}

/* this function merges the [] to [0 days] in the embargo_duration array */
private function mergeblank2zero(&$a) {
  $result = array();
  foreach($this->embargoduration_x_array() as $len) {
    $result[] = $a[$len];
  }
  $result[0] += $a[""];
  return $result;
}


/* common supports to flash chart generation */

private function sendchart(&$chart = null) {
  if($this->firstReport == true) {
    $this->firstReport = false;
    $this->view->flashchart = $chart;
  } else {
    $this->_helper->layout->disableLayout();
    $this->_helper->viewRenderer->setNoRender(true);
    echo $chart->toPrettyString();
  }
}
private function addchartelements(&$chart, $vector) {
  $colors = array ("Total" => '#000000', "Masters Theses" => '#33A02C', "Dissertations" => '#FF7F00', "Honors Theses" => '#1F78B4'); 
  foreach ($vector as $key => $values) {
    $bar = new bar_glass();
    $bar->set_colour( $colors[$key] );
    $bar->key($key, 10);
    $bar->set_values($values);
    $chart->add_element($bar);
  }
}
private function addchartartifacts(&$chart, $x_label_array, $max_page_num, $x_legend_text, $y_legend_text) {
  if ($max_page_num > 50) {
    $steps = ceil($max_page_num / 50);
    $scaled_max_num = $steps * 50;
  } else {
    $scaled_max_num = 50;
  }
  $x_labels = new x_axis_labels();
  $x_labels->set_vertical();
  $x_labels->set_labels( $x_label_array );
  $x_legend = new x_legend( $x_legend_text );
  $x_legend->set_style( '{font-size: 12px; color: #333333; font-weight:bold}' );
  $chart->set_x_legend( $x_legend );
  $y_legend = new y_legend( $y_legend_text );
  $y_legend->set_style( '{font-size: 14px; color: #333333; font-weight:bold}' );
  $chart->set_y_legend( $y_legend );
  $x = new x_axis();
  $x->set_labels( $x_labels );
  $chart->set_x_axis( $x ); 
  $y = new y_axis();
  $y->set_range( 0, $scaled_max_num, 50);
  $chart->add_y_axis( $y );
}
/**
 * This function returns the programs in the high level program specified by $coll
 */
private function progcolls($coll = "#grad") {

    try {
      $programObject = new foxmlPrograms($coll);
      $programs = $programObject->skos;
    } catch (XmlObjectException $e) {
      $message = "Error: Program not found";
      if ($this->env != "production") $message .= " (<b>" . $e->getMessage() . "</b>)";
      $this->_helper->flashMessenger->addMessage($message);
      $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "notfound"), "", true);
    }
    return $programs;
}

private function combinearrays(&$array1, &$array2) {
  $combined_array = array();
  foreach(array_keys($array1) as $key) {
    $combined_array[$key] = $array1[$key] + $array2[$key];
  }
  return $combined_array;
}
}
