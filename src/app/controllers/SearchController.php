<?php

require_once("models/etd.php");
require_once("Pager/Pager.php");

class SearchController extends Etd_Controller_Action {
  protected $requires_fedora = true;
  
  public function indexAction() {
    $this->view->title = "Search"; 
  }

  public function resultsAction() {
    $request = $this->getRequest();
    $query = $request->getParam("query");	// basic keyword/anywhere query
    $start = $request->getParam("start", 0);
    $max = $request->getParam("max", 20);

    $opts = $this->getFilterOptions();
    // includes these fields: status, committee, year, program, subject, author, keyword

    
    // non-facet fields included in advanced search
    $extra_fields = array("title", "abstract", "tableOfContents");
    $this->view->search_fields = $extra_fields;
    foreach ($extra_fields as $field) {
      if ($this->_hasParam($field)) {
	// only include a if the parameter is set and is not blank
	if ($value = $this->_getParam($field)) {
	  $opts[$field] = $this->_getParam($field);
	  $this->view->url_params[$field] = $opts[$field];
	}
      }
    }

    // generate generic filter query from facets & search fields
    $filter_query = etd::filterQuery($opts);

    // combine simple search query with filter (either one could be blank)
    if ($query) {
      $this->view->url_params["query"] = $query;
      if ($filter_query)	// only include filter if it is not blank
	$query .= " AND " . $filter_query;
    } else {
      $query = $filter_query;
    }

    
     $unembargoed = $this->_hasParam("unembargoed");
     if ($unembargoed) {
     // restrict to records that have files available - any embargo has ended
       $today = date("Ymd");	// today's date
       // any embargoes that have ended today or before
       $embargo_query = " date_embargoedUntil:[*  TO $today]";  

       // embargo should always be an AND; allow embargo query without other parameters
       if ($query != "") $query = "($query) AND $embargo_query";
       $query = $embargo_query;
     }

     if ($query == "") {
       $this->_helper->flashMessenger->addMessage("Error: no search terms specified");
       // where to redirect?
       $this->_helper->redirector->gotoRoute(array("controller" => "search",
						   "action" => "index"), "", true);
       exit;
     }
     
     $solr = Zend_Registry::get('solr');

     // by default, limiting search to published records
     $results = $solr->queryPublished($query, $start, $max);
     
     $this->view->count = $results->numFound;
     $this->view->etds = $results->docs;
     $this->view->facets = $results->facets;
     
     $this->view->results = $results;
     $this->view->title = "Search Results"; 

     $this->view->count = $results->numFound;
     $this->view->start = $start;
     $this->view->max = $max;
     $this->view->post = true;
     

     $etds = array();
     foreach ($results->docs as $result_doc) {
       array_push($etds, new etd($result_doc->PID));
     }
     $this->view->etds = $etds;


     // if there's only one match found, forward directly to full record view
     if ($this->view->count == 1) {
       $this->_helper->flashMessenger->addMessage("Only one match found for search; displaying full record");
       $this->_helper->redirector->gotoRoute(array("controller" => "view",
						   "action" => "record", "pid" => $etds[0]->pid), "", true);
     } 
   }



  public function facultysuggestorAction() {
    if ($this->_hasParam("faculty"))
      $name = $this->_getParam("faculty");
    /* slight hack: need two auto-suggestors on the same page with
       different ids; easiest to just allow two different parameters */
    elseif ($this->_hasParam("advisor"))
      $name = $this->_getParam("advisor");

    $p = new esdPersonObject();

    try {
      $this->view->faculty = $p->match_faculty(stripslashes($name));
    } catch (Exception $e) {
      $this->view->esd_error = true;
    }
    
    // disable layouts and view script rendering in order to set content-type header as xml
     $this->_helper->layout->disableLayout();
    //    $this->_helper->viewRenderer->setNoRender(true);
    $this->getResponse()->setHeader('Content-Type', "text/xml");
    // FIXMe: use displayXml helper?

  }
  
}