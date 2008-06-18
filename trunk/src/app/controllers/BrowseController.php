<?php

require_once("models/etd.php");
require_once("models/etd_feed.php");
require_once("models/programs.php");

class BrowseController extends Etd_Controller_Action {
  protected $requires_fedora = true;
  
  protected $browse_field;

  /* browse by author */
  public function authorAction() {
    $request = $this->getRequest();
    $request->setParam("nametype", "author");
    $this->_forward("name");
  }
  /* browse by committee member */
  public function committeeAction() {
    $request = $this->getRequest();
    $request->setParam("nametype", "committee");
    $this->_forward("name");
    $this->view->browse_mode = "Committee Member";
  }

  /* browse by advisor */
  /* (not actually public, but might be nice to have available...) */
  public function advisorAction() {
    $request = $this->getRequest();
    $request->setParam("nametype", "advisor");
    $this->_forward("name");
    $this->view->browse_mode = "advisor";
  }

  /* handle common functionality for "name" fields (author, committee, advisor) */
  public function nameAction() {
    $request = $this->getRequest();
    $type = $request->getParam("nametype");
    $value = $request->getParam("value", null);

    if (!isset($this->view->browse_mode))
      $this->view->browse_mode = $type;
    
    $request->setParam("field", $type . "_facet");
    //$request->setParam("field", $type);

    if (is_null($value)) {
      $this->_forward("browsefield");
    } else {
      // note: using exact match so we don't get confusing results where names partially match
      $request->setParam("exact", true);
      $this->_forward("browse");
    }
  }

  /* browse by year */
  public function yearAction() {
    $request = $this->getRequest();
    $request->setParam("exact_type", "year");
    $this->_forward("exact");
  }

  /* handle common functionality for exact fields */
  public function exactAction() {
    $request = $this->getRequest();
    $type = $request->getParam("exact_type");
    $value = $request->getParam("value", null);
    $request->setParam("exact", true);
     
    if (!isset($this->view->browse_mode))
      $this->view->browse_mode = $type;

    if (is_null($value)) {
      $request->setParam("field", $type);
      $this->_forward("browsefield");
    } else {
      $request->setParam("field", $type);
      $this->_forward("browse");
    }
  }
   


  /* common functionality - get a list of terms by field
   expects field parameter to be set 
  */
  public function browsefieldAction() {
    $request = $this->getRequest();
    $field = $request->getParam("field");

    $solr = Zend_Registry::get('solr');
    $results = $solr->browse($field);
    
    //    print "<pre>"; print_r($results); print "</pre>";
    $this->view->count = count($results->facets->$field);
    $this->view->values = $results->facets->$field;
    $this->view->title = "Browse " . $this->view->browse_mode . "s";
  }



   
  // list of records by field + value
  public function browseAction() {
    $field = $this->_getParam("field");
    $_value = urldecode($this->_getParam("value"));	// store original form
    $value = $_value;
    $value = urldecode($value);


    $start = $this->_getParam("start", 0);
    $max = $this->_getParam("max", 20);	   


    
    $exact = $this->_getParam("exact", false);
    if (! $exact) {
      $value = strtolower($value);
      $value = str_replace(",", "", $value);	// remove commas from names
    }


    $solr = Zend_Registry::get('solr');

    // note - solr default set to OR (seems to work best)
    //splitting out name parts to get an accurate match here (e.g.,
    //not matching committee members with a common first name)
    if ($exact) {
      $query = $field . ':"' . $value . '"';
    } else {
      $queryparts = array();
      foreach (preg_split('/[ +,.-]+/ ', strtolower($value), -1, PREG_SPLIT_NO_EMPTY) as $part) {
	if ($part != "a")	// stop words that cause solr problem
	  array_push($queryparts, "$field:$part");
      }
      $query = join($queryparts, '+AND+');
    }
    //         print "query is $query\n";
    //$results = $solr->query("$field:($value)");
    $results = $solr->queryPublished($query, $start, $max);	// limit to published records
     
    $this->view->count = $results->numFound;

    //
    $etds = array();
    foreach ($results->docs as $result_doc) {
      array_push($etds, new etd($result_doc->PID));
    }
    //     $this->view->etds = $results['response']['docs'];
    $this->view->etds = $etds;
    $this->view->facets = $results->facets;

    // if there's only one match found, forward directly to full record view
    if ($this->view->count == 1) {
      $this->_helper->flashMessenger->addMessage("Only one match found; displaying full record");
      $this->_helper->redirector->gotoRoute(array("controller" => "view",
      						  "action" => "record", "pid" => $etds[0]->pid), "", true);
    }

    $this->view->start = $start;
    $this->view->max = $max;

     
    $this->view->value = $_value;
    $this->view->results = $results;
     
    $this->view->title = "Browse by " . $this->view->browse_mode . " : " . $_value;
  }


  /* browse hierarchies - programs, research fields */


  /* browse by program */
  public function programsAction() {
    $start = $this->_getParam("start", 0);
    $max = $this->_getParam("max", 10);	
    
    // optional name parameter - find id by full name
    $name = $this->_getParam("name", null);
    if (is_null($name)) {
      $coll = $this->_getParam("coll", "programs");
      $coll = "#$coll";
    } else {
      $prog = new programs();
      $coll = $prog->findIdbyLabel($name);
    }

    try {
      $programs = new programs($coll);
    } catch (XmlObjectException $e) {
      $message = "Error: Program not found";
      if ($this->env != "production")
	$message .= " (<b>" . $e->getMessage() . "</b>)";
      $this->_helper->flashMessenger->addMessage($message);
      $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "notfound"), "", true);
    }
    $this->view->collection = $programs;

    $this->view->browse_mode = "program"; 


    $results = $programs->findEtds($start, $max);  

    $this->view->count = $results->numFound;
    $this->view->results = $results;
     
    $etds = array();
    foreach ($results->docs as $result_doc) {
      array_push($etds, new etd($result_doc->PID));
    }

    $this->view->etds = $etds;
    $this->view->facets = $results->facets;


    $this->view->start = $start;
    $this->view->max = $max;

    $this->view->title = "Browse Programs";
    if ($coll != "#programs") $this->view->title .= " : " . $programs->label;
     
    // shared view script for programs & researchfields
    $this->view->action = "programs";
    $this->_helper->viewRenderer->setScriptAction("collection");

    //    $this->view->feed = new Zend_Feed_Rss($this->_helper->absoluteUrl('recent', 'feeds', null, array("program" => $programs->label)));
  }

  public function researchfieldsAction() {
    $start = $this->_getParam("start", 0);
    $max = $this->_getParam("max", 10);	

    $coll = $this->_getParam("coll", "researchfields");

    try {
      $fields = new researchfields("#$coll");
    } catch (XmlObjectException $e) {
      $message = "Error: Research Field not found";
      if ($this->env != "production")
	$message .= " (<b>" . $e->getMessage() . "</b>)";
      $this->_helper->flashMessenger->addMessage($message);
      $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "notfound"), "", true);
    }

    $this->view->collection = $fields;

    $results = $fields->findEtds($start, $max);

    $this->view->browse_mode = "researchfield"; 

    $this->view->results = $results;
     
    $etds = array();
    foreach ($results->docs as $result_doc) {
      array_push($etds, new etd($result_doc->PID));
    }
    $this->view->etds = $etds;
    $this->view->facets = $results->facets;
    $this->view->count = $results->numFound;
    $this->view->start = $start;
    $this->view->max = $max;
    

    // shared view script for programs & researchfields
    $this->view->action = "researchfields";
    $this->_helper->viewRenderer->setScriptAction("collection");

    $this->view->title = "Browse Research Fields";
    if ($coll != "researchfields") $this->view->title .= " : " . $fields->label;

  }



  // list a user's ETDs
  public function myAction() {
    if (isset($this->current_user)) {
      // expand to find by role, depending on current user - faculty, dept. staff, etc.
      switch ($this->current_user->role) {
      case "student":
      case "student with submission":
	// should it only be unpublished records? or all records?
	$etds = etd::findUnpublishedByAuthor(strtolower($this->current_user->netid));
	break;
      case "staff":
	$this->view->etds = etd::findUnpublishedByDepartment($this->current_user->department);
	break;
      default:
	$etds = array();
      }
    } // FIXME: not authorized if not logged in? what should error here be?

    $this->view->etds = $etds;
      
    // FIXME: need to add paging - count/start/max 
    $this->view->title = "My ETDs";
    $this->_helper->viewRenderer->setScriptAction("list");
    $this->view->show_status = true;
    $this->view->show_lastaction = true;
  }


  public function recentAction() {
    $this->view->title = "Browse Recently Published";
    $this->view->etds = etd::findRecentlyPublished();
    $this->_helper->viewRenderer->setScriptAction("list");
  }


  // list ETDs by department
  public function mydeptAction() {

    $this->view->etds = etd::findUnpublishedByDepartment("English");
      
    $this->view->title = "English ETDs";
    $this->_helper->viewRenderer->setScriptAction("list");
    $this->view->show_status = true;
    $this->view->show_lastaction = true;
  }

  
   
  // nice readable url - redirect to proquest listings for emory
  public function proquestAction() {
    // note: should this url be configurable, stored somewhere else?
    $this->_redirect("http://proquest.umi.com.proxy.library.emory.edu/pqdweb?RQT=305&SQ=LSCHNAME%28%7BEMORY+UNIVERSITY%7D%29&clientId=1917");
  }


  public function indexAction() {
    // FIXME: do we need a main browse page?
    $this->view->assign("title", "Welcome to %project%");
  }

}
?>