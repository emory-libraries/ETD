 <?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

//NOTE: "Report viewer"  needs elavated roles (maintenance account) to use Fedora content in the views

require_once("models/etd.php");
require_once("ofc/php-ofc-library/open-flash-chart.php");

class ReportController extends Etd_Controller_Action {
	protected $requires_fedora = false;
	protected $params;	
	private $chartentity;
	/**
	 * copy of fedoraConnection with current user's auth credentials
	 * (to be restored in postDispatch)
	 * @var FedoraConnection
	 */
	protected $_fedoraConnection;


	/**
	 * report viewers do not have special accesses at the fedora
	 * level, which some of these reports required.  Temporarily
	 * overriding fedora connection with a connection that uses
	 * maintenance account credentials.
	 * Note: this will be done before all actions in this
	 * controller, and the default fedora connection will restored
	 * by postDispatch.
	 */
	public function preDispatch() {
	  // store fedoraConnection with user auth credentials - to be restored in postDispatch
	  $this->_fedoraConnection = Zend_Registry::get("fedora");

	  // if current user is a report viewer (do NOT have special access at the fedora level),
	  // temporarily replace fedora connection
	  if ($this->current_user->role == "report viewer") {
	    $fedora_cfg = Zend_Registry::get('fedora-config');
	    try {
	      $fedora_opts = $fedora_cfg->toArray();
	      // use default fedora config opts but with maintenance account credentials
	      $fedora_opts["username"] = $fedora_cfg->maintenance_account->username;
	      $fedora_opts["password"] = $fedora_cfg->maintenance_account->password;
	      $maintenance_fedora = new FedoraConnection($fedora_opts);
	    } catch (FedoraNotAvailable $e) {
	      $this->logger->err("Error connecting to Fedora with maintenance account - " . $e->getMessage());
	      $this->_forward("fedoraunavailable", "error");
	      return;
	    } 
	    Zend_Registry::set("fedora", $maintenance_fedora);
	  }
	}

	/**
	 * restore fedoraConnection with currently-logged in user's credentials
	 */
	public function postDispatch() {
	  Zend_Registry::set("fedora", $this->_fedoraConnection);
	}

	/**
	 * Display list of reports
	 */
	public function indexAction() {
        if(!$this->_helper->access->allowed("report", "view")) {return false;}
        $this->view->title = "Reports";
	}
	 
	/**
	 * commencement review - allow user to select ETDs to be excluded 
	 * from the commencement report
	 */
	public function commencementReviewAction() {
	  if(!$this->_helper->access->allowed("report", "view")) {return false;}
	  
	  $this->view->title = "Reports : Commencement Report Review";
	  
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
     *  Produce the commencement report, filtering out any user-selected records
     *  set to be excluded on the commencement-review page
     */
    public function commencementAction() {
        if(!$this->_helper->access->allowed("report", "view")) {return false;}
      
        $this->view->title = "Reports : Commencement Report";

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
		/* FIXME: should this really be solrEtd ? loses title formatting, but is much faster... */
		$optionsArray['return_type'] = "solrEtd";

		        
	    $etdSet = new EtdSet();
	    $etdSet->find($optionsArray);
        
        //remove ETDs by pid or calculate & save grad semester indicator
        foreach ($etdSet->etds as $index => $etd) {
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
     *  Display an academic year date selection form for grad-data report
     *  Start of year is 12/31 of last year, end is 8/31 of current year
     */
    public function gradDataAction(){
        if(!$this->_helper->access->allowed("report", "view")) {return false;}
        $this->view->title = "Reports : Graduate Schol Academic Year";

        // academic start and end months
        $acStart="Dec 31";
        $acEnd="Aug 31";
        $numYears=2;  //number of years to include before most recent academic year
        $curDate=strtotime("now"); //current date
                
        //Create first and thus default choice
        //We are looking for completed yeears only!
        //If the report is run durring an academic year we get the most renct complted year: December from 2 years ago and  August from 1 yer ago
        //If it is betwen the end of one and the start of the next: we get Decenber for previous year and August for curent your
        if ($curDate < strtotime("$acEnd +1 day", $curDate)){
            $startDate = strtotime("$acStart -2 years" , $curDate);
            $endDate = strtotime("$acEnd -1 year" , $curDate);
        } else{
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
     * generate a CSV file of graduate school data for the requested date range
     */
    public function gradDataCsvAction(){
        if(!$this->_helper->access->allowed("report", "view")) {return false;}

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
     * Returns an array of start, end
     * @return array
     */
    public function getCommencementDateRange() {
      //created dates so they can be reformated for the query and people
      $startDate = mktime(0, 0, 0, 6, 1, (date("Y")-1));
      $endDate = mktime(0, 0, 0, 5, 31, date("Y"));
      
      return array($startDate, $endDate);
    }


    /*
     * This returns new fields to be added to the current CSV line, from the dataset $etdSet
     * It retreives the fields specified in $fields from the section of the
     * response specified by $group.  $max indicates the number of sets to be added
     * Example:  addCSVFields($etd, "chair", array("id", "full"), 2):
     * Would add $etd->mods->chair->id and $etd->mods->chair->full fields to the
     * CSV line from the first two entries in the $etd->mods->chair array.
     * Will add empty fields according to the specified maximum, so CSV columns
     * will line up no matter how many entries are present.
     *
     * @param etdSet $etd - The etd result set
     * @paam string $group - The group of the field that is being added
     * @param array $fields - List of fields from the group
     * @param int $max - Max number of fields to add
     * @return array - The origanal line with new fields added
     */
    public function addCSVFields($etd, $group, $fields, $max){
        for($i = 0; $i < $max; $i++){
            if (isset($etd->mods->{$group}[$i]) ){
                foreach($fields as $field) {
                $value = $etd->mods->{$group}[$i]->$field;
                // ignore ids with underscores -- some hand-entered non-Emory advisor ids have this
                if ($field == "id" && preg_match("/_/", $value)) $value = "";
                    $line[] = $value;
                }
            } else {
                foreach($fields as $field){
                    $line[] = "";
                }
            }
        }
        return $line;
    }


    /**
     * generate a CSV file with information about embargoed records
     */
    public function embargoCsvAction(){
      if(!$this->_helper->access->allowed("report", "view")) {return false;}

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
     * generate a CSV file with student name, emory email, and permanent email
     */
   public function exportemailsAction() {
     if(!$this->_helper->access->allowed("report", "view")) {return false;}
     $etdSet = new EtdSet();
     // FIXME: how do we make sure to get *all* the records ?
     $etdSet->find(array("AND" => array("status" => "approved"), "start" => 0, "max" => 200));

     // date/time this output was generated to be included inside the file
     $date = date("Y-m-d H:i:s");

     $data[] = array("Name", "Emory email address", "Permanent email address",
			"Program", "Output Generated " . $date);

     foreach ($etdSet->etds as $etd){
         $data[] = array($etd->authorInfo->mads->name->__toString(),
			$etd->authorInfo->mads->current->email,
			$etd->authorInfo->mads->permanent->email,
			$etd->program());
     }

     $this->view->data = $data;

     $this->_helper->layout->disableLayout();
     // add date to the suggested output filename
     $filename = "ETD_approved_emails_" . date("Y-m-d") . ".csv";
     $this->getResponse()->setHeader('Content-Type', "text/csv");
     $this->getResponse()->setHeader('Content-Disposition',
				     'attachment; filename="' . $filename . '"');
     
   }

   /**
    * summary statistics based on facets in the solr index
    */
   public function summaryStatAction() {
     if(!$this->_helper->access->allowed("report", "view")) {return false;}
     $this->view->title = "Reports : Summary Statistics";

     $solr = Zend_Registry::get('solr');
     $solr->clearFacets();
     $solr->addFacets(array("program_facet", "year", "dateIssued", "embargo_duration", "num_pages",
			    "degree_level", "degree_name"));
     // would be nice to also have: degree (level?), embargo duration
     $solr->setFacetLimit(-1);	// no limit
     $solr->setFacetMinCount(1);	// minimum one match
     $result = $solr->query("*:*", 0, 0);	// find facets on all records, return none
     $this->view->facets = $result->facets;
     uksort($this->view->facets->embargo_duration, "sort_embargoes");

     // how to do page counts by range?
     for ($i = 0; $i < 1000; $i += 100) {
       $range = sprintf("%05d TO %05d", $i, $i +100);
       if ($i == 0) $label = ">100";
       else $label = $i . " - " . ($i + 100);
       $response = $solr->query("num_pages:[$range]", 0, 0);
       $pages[$label] = $response->numFound;
     }
     $response = $solr->query("num_pages:[01000 TO *]", 0, 0);
     $pages[">1000"] = $response->numFound;

     /*     $response = $solr->query("num_pages:[00000 TO 00100]");
     $pages[">100"] = $response->numFound;
     $response = $solr->query("num_pages:[00100 TO 00200]");
     $pages["100-200"] = $response->numFound;
     $response = $solr->query("num_pages:[00200 TO 00300]");
     $pages["200-300"] = $response->numFound;
     */
     $this->view->pages = $pages;

   }

    /**
     * Function to retun a decorator to be used with the author name
     *
     * @param Date $grad_date - grad date of curent ETD
     * @return String
     * @todo convert this to a view helper
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
  } else {
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
  } else {
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
  // get a list of years present in all records
  $this->view->years = $result->facets->year;
  $this->firstReport = true;  // unused?
  $this->embargobyyearspecificAction();  // calls embargoreports with report type of year
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
  $this->view->title = "Report : Requested Embargo Duration";
  $embargo_chart = new open_flash_chart();
  $embargo_len = $this->embargo_durations();	// list of embargo durations, in order (better place to get these?)
  $x_label_array = $embargo_len;

  $vector = array(); 
  if ($report_type == "byprogram") {
    $progtext = $this->_getParam("nametext", "Humanities");
    $vector = $this->embargobyprogram($solr);
    $title = new title( "Requested Embargo Durations By ". $progtext . " Program");
  } else {
    $useyear = $this->_getParam("year", null);
    $vector = $this->embargobyyear($solr);
    $title = new title("Requested Embargo Durations" . ($useyear ? " ($useyear)" : ""));
  }
  $this->addchartelements($embargo_chart, $vector);
  $x_legend_text = 'Embargo Durations';
  $y_legend_text = 'Number or Records';
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
    $vector["Total"] =  $this->combinearrays($this->clean_embargo_data($response->facets->embargo_duration), $vector["Total"]);
    $criteria = sprintf("{!q.op=AND} program_facet:%s degree_name:M*", $progname);
    $response = $solr->query($criteria, 0, 0);
    $vector["Master's Thesis"] =  $this->combinearrays($this->clean_embargo_data($response->facets->embargo_duration), $vector["Master's Thesis"]);
    $criteria = sprintf("{!q.op=AND} program_facet:%s -degree_name:M* -degree_name:B*", $progname);
    $response = $solr->query($criteria, 0, 0); 
    $vector["Dissertation"] =  $this->combinearrays($this->clean_embargo_data($response->facets->embargo_duration), $vector["Dissertation"]);
  }
  return $vector;
}

/* it returns a vector for embargo duration report by year*/
public function embargobyyear(&$solr) {
  $useyear = $this->_getParam("year", "");	// default to all years
  if($useyear == "") {
    //$year = "[* TO *]";		// no date filter
    $year_filter = "";
  } else {
    $year = $useyear;
    $year_filter = "year:$useyear AND ";
  }
  // FIXME: total query unneeded, should just add  other numbers (or better, do a stacked bar graph)
  // NOTE: using genre/document type instead of degree names, to consistently filter across all schools/degrees

  // FIXME: pull genres/document types from a common config? (create one if it does not yet exist)
  $document_types = array("Honors Thesis", "Master's Thesis", "Dissertation");
  foreach ($document_types as $doc) {
    $response = $solr->query("$year_filter document_type:\"$doc\"", 0, 0);
    //    print "DEBUG: embargoes for $doc<pre>"; print_r($response->facets->embargo_duration); print "</pre>";
    $new_vector[$doc] = $this->clean_embargo_data($response->facets->embargo_duration);
  }
  // for now, generate total by summing up others (hopefully total can go away...)
  $totals = array(0, 0, 0, 0, 0);
  foreach($new_vector as $type => $values) {
    for ($i = 0; $i < count($values); $i++) {
      $totals[$i] += $values[$i];
    }
  }
  $new_vector["Total"] = $totals;
  return $new_vector;
  /*  
  
  $criteria = sprintf("{!q.op=AND} year:%s", $year);
  $response = $solr->query($criteria, 0, 0);
  $vector["Total"] =  $this->clean_embargo_data($response->facets->embargo_duration);
  $criteria = sprintf("{!q.op=AND} year:%s degree_name:B*", $year);
  $response = $solr->query($criteria, 0, 0);
  $vector["Honors Theses"] = $this->clean_embargo_data($response->facets->embargo_duration);
  $criteria = sprintf("{!q.op=AND}year:%s degree_name:M*", $year);
  $response = $solr->query($criteria, 0, 0);
  $criteria = sprintf("{!q.op=AND}year:%s document_type:Master*", $year);
  $response = $solr->query($criteria, 0, 0);
  $vector["Masters Theses"] = $this->clean_embargo_data($response->facets->embargo_duration);
  $criteria = sprintf("{!q.op=AND}year:%s -degree_name:M* -degree_name:B*", $year);
  $response = $solr->query($criteria, 0, 0);
  $vector["Dissertations"] = $this->clean_embargo_data($response->facets->embargo_duration);
  return $vector;*/
}

// possible embargo durations, in the order we want them (FIXME: get these from a config file?)
private function embargo_durations() {
  return array("0 days", "6 months", "1 year", "2 years", "6 years");
}

/**
 * clean up embargo data returned from solr so it can be used for chart data
 * - converts key => value array to a list of totals in duration order
 * - some early records have no embargo request; combines those totals with "0 days" duration
 * @param array $embargo_totals key => value of embargo duration => total, as returned by solr facet
 * @return array list of totals, indexed in order to match ascending embargo duration
 */
private function clean_embargo_data($embargo_totals) {
  $data = array();
  foreach($this->embargo_durations() as $duration) {
    $data[] = $embargo_totals[$duration];
  }
  // if there are any records with no embargo, add to 0 days total
  $data[0] += $embargo_totals[""];
  return $data;
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
#  $colors = array ("Total" => '#000000', "Masters Theses" => '#33A02C', "Dissertations" => '#FF7F00', "Honors Theses" => '#1F78B4');
  // NOTE: pull these from a common genre list
  $colors = array ("Total" => '#000000', "Master's Thesis" => '#33A02C', "Dissertation" => '#FF7F00', "Honors Thesis" => '#1F78B4'); 
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