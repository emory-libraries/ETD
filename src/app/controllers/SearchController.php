<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

require_once("models/etd.php");

class SearchController extends Etd_Controller_Action {
  protected $requires_fedora = true;
  
  public function indexAction() {
    $this->view->title = "Search"; 
  }

  /**
   * @todo combine the url_opts with url_params.
   */  
  public function resultsAction() {
    $request = $this->getRequest();
    $query = $request->getParam("query"); // basic keyword/anywhere query
    $url_opts = array("query" => $query);

    // override default sort of author - relevance makes more sense for a search
    if (!$this->_hasParam("sort")) $this->_setParam("sort", "relevance");
    $options = $this->getFilterOptions();
    array_unshift($this->view->sort_fields, "relevance");
    // includes these fields: status, committee, year, program, subject, author, keyword

    
    // non-facet fields included in advanced search
    $extra_fields = array("title", "abstract", "tableOfContents");
    // also include extra fields to be displayed as search terms
    $this->view->search_fields = $extra_fields; 
    foreach ($extra_fields as $field) {
      if ($this->_hasParam($field)) {
        // only include a if the parameter is set and is not blank
        if ($value = $this->_getParam($field)) {
          $field_value = $this->_getParam($field);
          $options['AND'][$field] = $field_value;
          $this->view->url_params[$field] = $field_value;
          $url_opts[$field] = $field_value;          
        }
      }
    }

    if ($query) $this->view->url_params["query"] = $query;


    // escape special charaters that have meaning to solr/lucene
    $query = preg_replace("/:/", "\:", $query);
    
    $options["query"] = $query;

    $unembargoed = $this->_hasParam("unembargoed");
    if ($unembargoed) {
      // set so it will carry over onto next pages, filtered searches
      $this->view->url_params['unembargoed'] = true;
      $url_opts['unembargoed'] = true;      
      // restrict to records that have files available - any embargo has ended
      $today = date("Ymd"); // today's date
      // any embargoes that have ended today or before
      $embargo_query = "date_embargoedUntil:[*  TO $today]";

      // embargo should always be an AND; allow embargo query without other parameters
      if ($query != "") $options["query"] = "($query) AND $embargo_query";
      else $options["query"] = $embargo_query;
    }

    if ($options["query"] == "" && count($options["AND"]) == 0) {
      $this->_helper->flashMessenger->addMessage("Error: no search terms specified");
      // where to redirect?
      $this->_helper->redirector->gotoRouteAndExit(array("controller" => "search",
               "action" => "index"), "", true);
    }


    
    // if status parameter is set and user has permission, allow to search all records
    if ($this->_hasParam("status") && $this->acl->isAllowed($this->current_user, "etd", "view status")) {
      $status = $this->_getParam("status");
      if ($status == "unpublished") {   // special case - find all unpublished records
        $options["NOT"]["status"] = "published";
        unset($options["AND"]["status"]);
      } else {
        $options["AND"]["status"] = $this->_getParam("status");
      }
    } else {
      // otherwise (by default), limit search to published records
      $options["AND"]["status"] = "published";
    }

    $options['return_type'] = "solrEtd";
    
    $etdSet = new EtdSet($options, null, 'find');
    $this->view->etdSet = $etdSet;
    $this->view->url_opts = $url_opts;    
    
    $this->view->title = "Search Results";
     
    // Set the url options for pagination
    if (isset($sort)) $url_opts["sort"] = $sort;
    $this->view->url_opts = $url_opts;
                
    // use paginator to display results in segments
    //Once orig object is paginated the orig objeject can be accessed by getItem function (it has a 1 based index).
    $paginator = new Zend_Paginator($etdSet);
    $paginator->setItemCountPerPage(10);     
     
    // if there's only one match found, forward directly to full record view
      if ($paginator->getTotalItemCount() == 1) {
      $this->_helper->flashMessenger->addMessage("Only one match found for search; displaying full record");
      $this->_helper->redirector->gotoRoute(array("controller" => "view",
              "action" => "record",
              "pid" => $paginator->getItem(1)->pid()), "", true);
    } 
    else {  
      // if page is set, pass to paginator
      if ($this->_hasParam('page')) $paginator->setCurrentPageNumber($this->_getParam('page'));      
      $this->view->paginator = $paginator; 
    }

    $this->view->show_relevance = true;
  }



  public function facultysuggestorAction() {
    if ($this->_hasParam("faculty"))
      $name = $this->_getParam("faculty");
    /* slight hack: need two auto-suggestors on the same page with
     different ids; easiest to just allow two different parameters */
    elseif ($this->_hasParam("advisor"))
      $name = $this->_getParam("advisor");
    else  // no search parameter : can't proceed
      throw new Exception("Either faculty or advisor parameter must be specified");

    $former = $this->_getParam("former", 0);
    $this->view->former = $former;
    $current_only = $former ? false : true;
    
    $p = new esdPersonObject();

    try {
      $this->view->faculty = $p->match_faculty(stripslashes($name),  $current_only);
    } catch (Exception $e) {
      $this->view->esd_error = true;
    }
    
    // disable layouts and view script rendering in order to set content-type header as xml
    $this->_helper->layout->disableLayout();
    $this->getResponse()->setHeader('Content-Type', "text/xml");
  }


  public function suggestorAction() {
    if (!$this->_hasParam("query")) throw new Exception("Required parameter 'query' is not set");
    $query = $this->_getParam("query");

    $solr = Zend_Registry::get('solr');

    // optionally limit to a specific field
    if ($this->_hasParam("field")) {
      $field = $this->_getParam("field");
      $solr->clearFacets();
      $solr->addFacets(array("$field"));
    }

    $mode = $this->_getParam("mode");
    if ($mode == "unpublished") {
      $solr->addFilter("NOT status:published");
    }

    // uses query string as facet prefix and returns any facets that match
    // using currently configured default facets
    $results = $solr->suggest(ucfirst($query));   // FIXME: case sensitive...

    // combine all values into a single array (doesn't matter which facet they came from)
    $matches = array();
    foreach($results->facets as $facet => $values) {
      $matches = array_merge($matches, $values);
    }

    $this->view->matches = $matches;
    // by default, display number of records matching the suggested term, but allow hiding it
    $this->view->show_count = ($this->_getParam("show_count", "yes") == "yes") ? true : false;


    $this->_helper->layout->disableLayout();
    $this->getResponse()->setHeader('Content-Type', "text/xml");
  }
  
}
