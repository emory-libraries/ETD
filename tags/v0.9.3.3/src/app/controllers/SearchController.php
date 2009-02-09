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

     /* fixme: maybe get all params at once and then loop through them ? */
    $this->view->query = array();

    // simple search
    $this->view->query['query'] = $query;
    
     $title = $request->getParam("title");
     if ($title) {
       $query .= " title:($title)";
       $this->view->query['title'] = $title;
     }
     $author = $request->getParam("author");
     if ($author) {
       $query .= " author:($author)";
       $this->view->query['author'] = $author;
     }
     $abstract = $request->getParam("abstract");
     if ($abstract) {
       $query .= " abstract:($abstract)";
       $this->view->query['abstract'] = $abstract;
     }
     $committee = $request->getParam("committee");
     if ($committee) {
       $query .= " committee:($committee)";
       $this->view->query['comittee'] = $committee;
     }
     $toc = $request->getParam("toc");
     if ($toc) {
       $query .= " tableOfContents:($toc)";
       $this->view->query['comittee'] = $committee;
     }
     $keyword = $request->getParam("keyword");
     if ($keyword) {
       $query .= " keyword_facet:($keyword)";
       $this->view->query['keyword'] = $keyword;
     }
     $subject = $request->getParam("subject");
     if ($subject) {
       $query .= " subject:($subject)";
       $this->view->query['subject'] = $subject;
     }
     $program = $request->getParam("program");
     if ($program) {
       $query .= " program:($program)";
       $this->view->query['program'] = $program;
     }
     $year = $request->getParam("year");
     if ($year) {
       $query .= " year:($year)";
       $this->view->query['year'] = $year;
     }

     // restrict to records that have files available - any embargo has ended
     $unembargoed = $this->_hasParam("unembargoed");
     if ($unembargoed) {
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

     // FIXME: turning off lower-casing because now using date_embargoedUntil field
     // -  shouldn't cause problems
     //     $query = strtolower($query);	// FIXME: any cases where this won't work?
     $results = $solr->queryPublished($query, $start, $max);	// limit to published records
     
     $this->view->count = $results->numFound;
     $this->view->etds = $results->docs;
     
     $this->view->facets = $results->facets;
     
     /*       $this->view->count = $results->response->numFound;
      $this->view->etds = $results->response->docs;
      $this->view->facets = $results->facet_counts->facet_fields;*/
     
     $this->view->results = $results;
     $this->view->title = "Search Results"; 

     $this->view->count = $results->numFound;
     $this->view->start = $start;
     $this->view->max = $max;
     $this->view->post = true;
     

     //
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
    elseif ($this->_hasParam("adv"))
      $name = $this->_getParam("adv");

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