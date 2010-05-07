 <?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

require_once("models/etd.php");
require_once("models/charts.php");
require_once("models/programs.php");
require_once("ofc/php-ofc-library/open-flash-chart.php");

class ReportController extends Etd_Controller_Action {
	protected $requires_fedora = false;
	protected $params;	
	
	/**
	 * copy of fedoraConnection with current user's auth credentials
	 * (to be restored in postDispatch)
	 * @var FedoraConnection
	 */
	protected $_fedoraConnection;

	/**
	 * years present in the ETD data (for use in flash charts)
	 * @var array
	 */
	private $year;
	/**
	 * possible embargo durations, as present in the ETD data (for use in flash charts)
	 * @var array 
	 */
	private $embargo_duration;
	/**
	 * available document types, as present in the ETD data (for use in flash charts)
	 * @var array
	 */
	private $document_type;
	/**
	 *
	 */
	private $report_title;
	
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
	  
	  if (isset($this->current_user)) {
	    
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
            //rint_r($etd);
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
     

    /*** Open Flash Chart reports  ***/

    /**
     * Document length report
     * stacked bar chart for document length, segmented by document
     * type, with optional year & program filters.
     */
    public function pageLengthAction() {
      $report_title = "Document Length";
      $this->view->title = "Report : " . $report_title;

      list($filters, $title_detail) =  $this->segmented_chart_setup();      
      $report_title .= $title_detail;
      $this->view->title .= $title_detail;
      list($x_legend, $data) = $this->pagelength_totals($filters);
      
      list($all_data, $max) = $this->segment_barchart_data($data, count($x_legend));
      $this->view->chart = new stacked_bar_chart($report_title, $x_legend, "Document Length", $all_data, $max);
      $this->render("filtered-chart");
    }

    /**
     * Embargo duration request report
     * stacked bar chart for document length, segmented by document
     * type, with optional year & program filters.
     */
    public function embargoAction() {
      $report_title = "Requested Embargo Duration";
      $this->view->title = "Report : " . $report_title;

      list($filters, $title_detail) =  $this->segmented_chart_setup();
      $embargo_opts = $this->embargo_duration;
      $this->view->title .= $title_detail;
      $report_title .= $title_detail;
      
      $data = $this->embargo_totals($filters);

      list($all_data, $max) = $this->segment_barchart_data($data, count($embargo_opts));
      $this->view->chart = new stacked_bar_chart($report_title, $embargo_opts, 'Embargo Duration',
						 $all_data, $max);
      $this->render("filtered-chart");
    }

    /**
     * common setup for segmented, filterable charts (embargo, page-length)
     * @return array of filters, title detail
     */
    private function segmented_chart_setup() {
      // initialize years, embargo durations, and document types from Solr
      $this->get_chart_fields();
      $this->view->years = $this->year;

      // use program as drill-down filter (just like browse by program)
      $program_id = $this->_getParam("program", "programs");
      $programObject = new foxmlPrograms("#" . $program_id);
      $this->view->program = $programObject->skos;

      $filters = array();
      $title_filters = array();
      // filter by year, if specified
      $current_year = $this->_getParam("year", null);
      if ($current_year) {
	$this->view->current_year = $current_year;
	$filters["year"] = $current_year;
	$title_filters[] = $current_year;
      }
      if ($this->_hasParam('program') && $program_id != "programs") {
	$filters["program"] = $this->view->program;	// pass the skosCollection object
	$title_filters[] = $this->view->program->label;
      }
      // add filters to a title detail to be added to page & report titles
      $title_detail = "";
      if (count($title_filters)) {
	$title_detail = " (" . implode(', ', $title_filters) . ")";
      }
      return array($filters, $title_detail);
    }

    /**
     * convert data into format needed for bar chart, segmented by document type
     * @param array $data
     *  - one entry in the array for each document type, each with a list of $num values
     * @param int $num  number of sets of data
     * @return array data, maximum value
     */
    private function segment_barchart_data($data, $num) {
      $max = 0;
      $all_data = array();
      for ($i = 0; $i < $num; $i++) {
        $bar_data = array();
        foreach ($this->document_type as $doc_type) {
          // don't add zeroes (messes up the tool tips)
          if ($data[$doc_type][$i])  $bar_data[$doc_type] = $data[$doc_type][$i];
        }
        $current_total = array_sum(array_values($bar_data));
        if ($current_total > $max) $max = $current_total;
	$all_data[] = $bar_data;
      }
      return array($all_data, $max);
    }

    /**
     * data used to build and filter the flash charts reports
     * initialize year, embargo duration, and document types from ETD data
     * and store for use
     */
    private function get_chart_fields() {
      $solr = Zend_Registry::get('solr');
      $facets = array("year", "document_type", "embargo_duration");
      $solr->clearFacets()->addFacets($facets);
      $solr->setFacetLimit(-1)->setFacetMinCount(1); 
      $result = $solr->query("*:*", 0, 0);
      
      $data = array();
      foreach ($facets as $facet) {
	$values = array_diff(array_keys($result->facets->$facet), array(0 => ''));
	if ($facet == "embargo_duration") {	// sort by increasing duration (not alphabetically!)
	  usort($values, "sort_embargoes");
	}
	$this->$facet = $values;
      }
    }

    /**
     * generate solr queries to filter by requested parameters
     * @param array associative array of field and value
     * 	currently supports
     *   - year
     *   - program (should pass in a programs skosCollection object at the appropriate level)
     * @return string solr query filter
     */
    private function solr_filters($filters = array()) {
      // build solr query filter based on specified year and program
      if (isset($filters['year']) and $filters["year"] != null) {
          $year_filter = "year:" . $filters['year'] . " AND ";
      } else {
          $year_filter = "";
      }
      if (isset($filters['program'])) {
	$program_filter = $filters['program']->findEtds_query() . " AND ";
      } else {
	$program_filter = "";
      }
      return $year_filter . $program_filter;
    }

    /**
     * get total # of records for each type of embargo
     * @param array optional filters - restrict totals by year or program
     */
    public function embargo_totals($filters = array()) {
      $solr = Zend_Registry::get('solr');
      // only facet on embargo duration, no limit, minimum 0 (include all values)
      $solr->clearFacets()->addFacets(array("embargo_duration"))->setFacetLimit(-1)->setFacetMinCount(0);
      $filter = $this->solr_filters($filters);
      
      // NOTE: using genre/document type instead of degree names to consistently filter across all schools/degrees
      foreach ($this->document_type as $doc) {
        $response = $solr->query("$filter document_type:\"$doc\"", 0, 0);
        $totals[$doc] = $this->clean_embargo_data($response->facets->embargo_duration);
      }
      return $totals;
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
      foreach($this->embargo_duration as $duration) {
	$data[] = $embargo_totals[$duration];
      }
      // if there are any records with no embargo, add to 0 days total
      $data[0] += $embargo_totals[""];
      return $data;
    }

    /**
     * get total # of records by document length
     * @param array optional filters - restrict totals by year or program
     * @return array of page-length labels and array of data for chart
     */
    public function pagelength_totals($filters = array()) {
      $solr = Zend_Registry::get('solr');

      // NOTE: using genre/document type instead of degree names to consistently filter across all schools/degrees
      // only facet on embargo duration, no limit, minimum 0 (include all values)
      $solr->clearFacets()->addFacets(array("document_type"))->setFacetLimit(-1)->setFacetMinCount(1);

      // get solr query filter based on specified year and program
      $filter = $this->solr_filters($filters);

      // get page counts by range, segmented by document type
      $pagelength_labels = array();
      $totals = array();
      foreach ($this->document_type as $doc_type) {
	$totals[$doc_type] = array();
      }
      // generate page length labels and corresponding page-range solr query
      for ($i = 0; $i < 1000; $i += 100) {
	$range = sprintf("%05d TO %05d", $i, $i +100);
	if ($i == 0) $label = ">100";
	else $label = $i . " - " . ($i + 100);
	$pagelength_labels[$label] = $range;
      }
      $pagelength_labels[">1000"] = "01000 TO *";

      foreach ($pagelength_labels as $label => $page_range) {
	$response = $solr->query("$filter num_pages:[$page_range]", 0, 0);
	foreach ($this->document_type as $doc_type) {
	  if (isset($response->facets->document_type[$doc_type]))
	    $count = $response->facets->document_type[$doc_type];
	  else $count = 0;
	  $totals[$doc_type][] = $count;
	}
      }
      return array(array_keys($pagelength_labels), $totals);
    }

}  // end ReportController



/**
 * sort embargo durations logically (days/months/years)
 * - used by summaryStat and chart data for flash charts
 */ 
function sort_embargoes($a, $b) {
  // check if either is blank
  if (trim($a) == "") return -1; // a is less than b
  if (trim($b) == "") return 1; // a is greater than b

  // split into number & time unit
  list($a_num, $a_time) = explode(' ', $a);
  list($b_num, $b_time) = explode(' ', $b);

  // convert time unit to numeric for easy comparison
  // days = 1, months = 2, years = 3
  foreach (array($a_time, $b_time) as $time) {
    switch ($time) {
    case "years":
    case "year":
      $t = 3; break;
    case "months":
      $t = 2; break;
    case "days":
      $t = 1; break;
    }
  }

  if ($a_time == $b_time) { 
    // same time duration - compare by numbers only
    if ($a_num == $b_num) return 0;
    return ($a_num < $b_num) ? -1 : 1;
  } else {
    // otherwise, compare by time unit only
    return ($a_time < $b_time) ? -1 : 1;
  }
}


