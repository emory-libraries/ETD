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
    $start = $request->getParam("start", 1);
    $max = $request->getParam("max", 20);	

     /* fixme: maybe get all params at once and then loop through them ? */
    $this->view->query = array();
    
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
       $query .= " keyword:($keyword)";
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

     $solr = Zend_Registry::get('solr');
     if ($query == "") {
       $this->_helper->flashMessenger->addMessage("Error: no search terms specified");
       // where to redirect?
       $this->_helper->redirector->gotoRoute(array("controller" => "search",
						   "action" => "index"), "", true);
       exit;
     }
     $query = strtolower($query);	// FIXME: any cases where this won't work?
     $results = $solr->query($query, $start, $max);
     
     $this->view->count = $results['response']['numFound'];
     $this->view->etds = $results['response']['docs'];
     
     $this->view->facets = $results['facet_counts']['facet_fields'];
     
     /*       $this->view->count = $results->response->numFound;
      $this->view->etds = $results->response->docs;
      $this->view->facets = $results->facet_counts->facet_fields;*/
     
     $this->view->results = $results;
     $this->view->title = "Search Results"; 

     $this->view->count = $results['response']['numFound'];
     $this->view->start = $start;
     $this->view->max = $max;
     $this->view->post = true;


     //
     $etds = array();
     foreach ($results['response']['docs'] as $result_doc) {
       array_push($etds, new etd($result_doc['PID']));
     }
     $this->view->etds = $etds;



     // if there's only one match found, forward directly to full record view
     if ($this->view->count == 1) {
       $this->_helper->flashMessenger->addMessage("Only one match found for search; displaying full record");
       $this->_helper->redirector->gotoRoute(array("controller" => "view",
						   "action" => "record", "pid" => $etds[0]->pid), "", true);
     } 

   }



  public function facultySuggestorAction() {
    $name = $this->_getParam("faculty");
    $p = new esdPersonObject();

    $this->view->faculty = $p->match_faculty(stripslashes($name));
    
    // disable layouts and view script rendering in order to set content-type header as xml
     $this->_helper->layout->disableLayout();
    //    $this->_helper->viewRenderer->setNoRender(true);
    $this->getResponse()->setHeader('Content-Type', "text/xml");
    // FIXMe: use displayXml helper?

  }
  
}