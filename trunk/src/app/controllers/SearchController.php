<?php

require_once("models/etd.php");

class SearchController extends Etd_Controller_Action {

  public function indexAction() {
    $this->view->title = "Search"; 
  }

  public function resultsAction() {
    $request = $this->getRequest();
     $query = $request->getParam("query");

     /* fixme: maybe get all params at once and then loop through them ? */
     
     $title = $request->getParam("title");
     if ($title) $query .= " title:($title)";
     $author = $request->getParam("author");
     if ($author) $query .= " author:($author)";
     $abstract = $request->getParam("abstract");
     if ($abstract) $query .= " abstract:($abstract)";
     $committee = $request->getParam("committee");
     if ($committee) $query .= " committee:($committee)";
     $toc = $request->getParam("toc");
     if ($toc) $query .= " tableOfContents:($toc)";
     $keyword = $request->getParam("keyword");
     if ($keyword) $query .= " keyword:($keyword)";
     $subject = $request->getParam("subject");
     if ($subject) $query .= " subject:($subject)";
     $program = $request->getParam("program");
     if ($program) $query .= " program:($program)";

     //     print "DEBUG: query is $query\n";

     
     $solr = Zend_Registry::get('solr');
     $results = $solr->query(urlencode($query));
     
     $this->view->count = $results['response']['numFound'];
     $this->view->etds = $results['response']['docs'];
     
     $this->view->facets = $results['facet_counts']['facet_fields'];
     
     /*       $this->view->count = $results->response->numFound;
      $this->view->etds = $results->response->docs;
      $this->view->facets = $results->facet_counts->facet_fields;*/
     
     $this->view->results = $results;
     $this->view->title = "Search Results"; 

     $this->view->count = $results['response']['numFound'];

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
    $this->getHelper('layoutManager')->disableLayouts();
    //    $this->_helper->viewRenderer->setNoRender(true);
    $this->getResponse()->setHeader('Content-Type', "text/xml");
    // FIXMe: use displayXml helper?

  }
  
}