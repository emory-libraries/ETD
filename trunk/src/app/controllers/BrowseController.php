<?php

require_once("models/etd.php");
require_once("models/etd_feed.php");
require_once("models/programs.php");
require_once("models/researchfields.php");

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
    //    $this->view->browse_mode = "Committee Member";
    $this->view->browse_mode = "committee";
  }

  /* browse by committee chair */
  /* (not actually public, but might be nice to have available...) */
  public function chairAction() {
    $request = $this->getRequest();
    $request->setParam("nametype", "chair");
    $this->_forward("name");
    $this->view->browse_mode = "chair";
  }

  /* handle common functionality for "name" fields (author, committee, chair) */
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
     expects 'field' parameter to be set 
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

    // pass browse value to generate remove-facet links
    $this->view->url_params = array("value" => $_value);


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

    $opts = $this->getFilterOptions();
    
    $options = array("query" => $query, "AND" => $opts, "start" => $start, "max" => $max);
    $etdSet = etd::find($options);
    $this->view->etdSet = $etdSet;

    // if there's only one match found, forward directly to full record view
    if ($this->view->count == 1) {
      $this->_helper->flashMessenger->addMessage("Only one match found; displaying full record");
      $this->_helper->redirector->gotoRoute(array("controller" => "view",
      						  "action" => "record", "pid" => $etdSet->etds[0]->pid), "", true);
    }

    $this->view->start = $start;
    $this->view->max = $max;
     
    $this->view->value = $_value;
     
    $this->view->title = "Browse by " . $this->view->browse_mode . " : " . $_value;
  }


  /* browse hierarchies - programs, research fields */


  /* browse by program */
  public function programsAction() {
    $start = $this->_getParam("start", 0);
    $max = $this->_getParam("max", 10);
    $opts = $this->getFilterOptions();
    $options = array("start" => $start, "max" => $max, "AND" => $opts);
    
    // optional name parameter - find id by full name
    $name = $this->_getParam("name", null);
    if (is_null($name)) {
      $coll = $this->_getParam("coll", "programs");
      $this->view->url_params = array("coll" => $coll);
      $coll = "#$coll";
    } else {
      $prog = new programs();
      $coll = $prog->findIdbyLabel($name);
    }

    try {
      $programs = new programs($coll);
    } catch (XmlObjectException $e) {
      $message = "Error: Program not found";
      if ($this->env != "production") $message .= " (<b>" . $e->getMessage() . "</b>)";
      $this->_helper->flashMessenger->addMessage($message);
      $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "notfound"), "", true);
    }
    $this->view->collection = $programs;
    $this->view->browse_mode = "program"; 
    $this->view->etdSet = $programs->findEtds($options);

    $this->view->title = "Browse Programs";
    if ($coll != "#programs") $this->view->title .= " : " . $programs->label;
     
    $this->view->action = "programs";
    // shared view script for programs & researchfields
    $this->_helper->viewRenderer->setScriptAction("collection");

  //        $this->view->feed = new Zend_Feed_Rss($this->_helper->absoluteUrl('recent', 'feeds', null,
  //							      array("program" => $programs->label)));
  }

  public function researchfieldsAction() {
    $start = $this->_getParam("start", 0);
    $max = $this->_getParam("max", 10);	

    $coll = $this->_getParam("coll", "researchfields");
    $opts = $this->getFilterOptions();
    $options = array("start" => $start, "max" => $max, "AND" => $opts);
    
    // needed to generate remove-facet links
    $this->view->url_params = array("coll" => $coll);
    
    try {
      $fields = new researchfields("#$coll");
    } catch (XmlObjectException $e) {
      $message = "Error: Research Field not found";
      if ($this->env != "production")
	$message .= " (<b>" . $e->getMessage() . "</b>)";
      $this->_helper->flashMessenger->addMessage($message);
      $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error",
							 "action" => "notfound"), "", true);
    }

    $this->view->collection = $fields;

    $this->view->etdSet = $fields->findEtds($options);
    $this->view->browse_mode = "researchfield"; 
    

    // shared view script for programs & researchfields
    $this->view->action = "researchfields";
    $this->_helper->viewRenderer->setScriptAction("collection");

    $this->view->title = "Browse Research Fields";
    if ($coll != "researchfields") $this->view->title .= " : " . $fields->label;
  }

  // list a user's ETDs
  public function myAction() {
    $this->view->title = $this->view->list_title = "My ETDs";

    // currently no content to display if user is not logged in (shouldn't see links here...)
    if (!isset($this->current_user)) {
      $etds = array();
      return;
    }

    $start = $this->_getParam("start", 0);
    $max = $this->_getParam("max", 5);
    $opts = $this->getFilterOptions();
    $options = array("start" => $start, "max" => $max, "AND" => $opts);
    
    // if logged in user is a program coordinator, forward to the appropriate action
    if ($this->current_user->program_coord) {
      // FIXME: how to give super-user option to view the student/faculty pages?
      $this->_forward("my-program");
      return;	
    }

    $username = strtolower($this->current_user->netid);
    
    // expand to find by role, depending on current user - faculty, dept. staff, etc.
    switch ($this->current_user->role) {
    case "student":
    case "student with submission":
      // records for this action : owned by current user (author) and not published
      $options["AND"]["ownerId"] = $username;
      // FIXME: is this filter necessary/useful ?
      $options["NOT"]["status"] = "published";		// any status other than published
      break;
    case "faculty":
      $options["OR"]["advisor_id"] = $options["OR"]["committee_id"] = $username;
      // FIXME: display add some kind of label in this mode?
      // something like: "Records on which you are listed as committee chair or member"
      break;
    default:
      // no records to display for this user; simulate an empty result set 
      $etds = array();	
    }

    $this->view->etdSet = etd::find($options); 
      
    $this->_helper->viewRenderer->setScriptAction("list");
    $this->view->show_status = true;
    $this->view->show_lastaction = true;
  }


  // program coordinator view : list unpublished records for the specified department 
  public function myProgramAction() {
    // FIXME: error handling if user is not a program coordinator?

    if (!isset($this->current_user) || empty($this->current_user->program_coord)) {
      $role = isset($this->current_user) ? $this->current_user->getRoleId() : "guest";
      $this->_helper->access->notAllowed("view", $role, "program coordinator view");
      return false;
    }

    $start = $this->_getParam("start", 0);
    $max = $this->_getParam("max", 25);
    $status = $this->_getParam("status", null);
    $opts = $this->getFilterOptions();
    $options = array("start" => $start, "max" => $max, "AND" => $opts);

    $this->view->etdSet = etd::findUnpublishedByDepartment($this->current_user->program_coord, $options);
    $title = "Unpublished Records : " . $this->current_user->program_coord;
    $this->view->title = $title;
    $this->view->list_title = $title;
    $this->_helper->viewRenderer->setScriptAction("list");
    $this->view->show_status = true;
    $this->view->show_lastaction = true;
  }


  public function recentAction() {
    $start = $this->_getParam("start", 0);
    $max = $this->_getParam("max", 25);
    $opts = $this->getFilterOptions();
    $options = array("start" => $start, "max" => $max, "AND" => $opts);

    
    $this->view->title = "Browse Recently Published";
    $this->view->list_title = "Recently Published";
    $this->view->etdSet = etd::findRecentlyPublished($options);
    $this->_helper->viewRenderer->setScriptAction("list");
  }


  // nice readable url - redirect to proquest listings for emory
  public function proquestAction() {
    // note: should this url be configurable, stored somewhere else?
    $this->_redirect("http://proquest.umi.com.proxy.library.emory.edu/pqdweb?RQT=305&SQ=LSCHNAME%28%7BEMORY+UNIVERSITY%7D%29&clientId=1917");
  }


  public function indexAction() {
    // FIXME: should probably create a top-level browse page, in case anyone meddles with the urls...
    $this->view->assign("title", "Welcome to %project%");
  }



}
?>