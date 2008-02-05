<?php

require_once("models/solrEtd.php");

class SearchController extends Zend_Controller_Action {

  public function indexAction() {
    $this->view->title = "Search"; 
  }

  public function resultsAction() {
    $request = $this->getRequest();
     $query = $request->getParam("query");

     
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
       array_push($etds, new solrEtd($result_doc));
     }
     $this->view->etds = $etds;
   }
  
}