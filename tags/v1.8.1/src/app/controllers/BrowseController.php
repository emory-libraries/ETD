<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

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
    $field = $this->_getParam("field");

    // special functionality for alphabetical fields
    if ($field != "year")  {
      $this->view->alpha = true;
      // generate an array of all the capitalized letters from A-Z
      $this->view->letters = array();
      for ($i = 65; $i < 91; $i++) {
        $this->view->letters[] = chr($i);
      } 
    }
    $letter = $this->_getParam("letter");
    $this->view->letter = $letter;

    $solr = Zend_Registry::get('solr');
    $results = $solr->browse($field, $letter);
    $this->view->count = count($results->facets->$field);
    $this->view->values = $results->facets->$field;
    $this->view->title = "Browse " . $this->view->browse_mode . "s";
  }

   
  // list of records by field + value
  public function browseAction() {
    $field = $this->_getParam("field");
    $_value = urldecode($this->_getParam("value")); // store original form
    $value = $_value;
    $value = urldecode($value);

    // pass browse value to generate remove-facet links
    $this->view->url_params = array("value" => $_value);
    
    $exact = $this->_getParam("exact", false);
    if (! $exact) {
      $value = strtolower($value);
      $value = str_replace(",", "", $value);  // remove commas from names
    }

    // add year to sort options
    $this->view->sort_fields[] = "year";

    $solr = Zend_Registry::get('solr');

    // note - solr default set to OR (seems to work best)
    //splitting out name parts to get an accurate match here (e.g.,
    //not matching committee members with a common first name)
    if ($exact) {
      $query = $field . ':"' . $value . '"';
    } else {
      $queryparts = array();
      foreach (preg_split('/[ +,.-]+/ ', strtolower($value), -1, PREG_SPLIT_NO_EMPTY) as $part) {
        if ($part != "a") // stop words that cause solr problem
          array_push($queryparts, "$field:$part");
      }
      $query = join($queryparts, '+AND+');
    }

    $options = $this->getFilterOptions();
    $options["query"] = $query;
    $options["return_type"] = "solrEtd";
    
    $etdSet = new EtdSet($options, null, 'findPublished');
    $this->view->etdSet = $etdSet;
    
    // Pagination Code
  /**
   * @todo refactor this paginator code that is repeated
   */      
    // TODO:  find a way to refactor this code that is repeated in this controller.
    $paginator = new Zend_Paginator($etdSet);
    $paginator->setItemCountPerPage(10);
    
    if ($this->_hasParam('page')) $paginator->setCurrentPageNumber($this->_getParam('page'));      
    $this->view->paginator = $paginator;     

    // if there's only one match found, forward directly to full record view
    if ($this->view->count == 1) {
      $this->_helper->flashMessenger->addMessage("Only one match found; displaying full record");
      $this->_helper->redirector->gotoRoute(array("controller" => "view",
                    "action" => "record", "pid" => $etdSet->etds[0]->pid), "", true);
    }

    $this->view->value = $_value;
     
    $this->view->title = "Browse by " . $this->view->browse_mode . " : " . $_value;
  }


  /* browse hierarchies - programs, research fields */


  /* browse by program */
  public function programsAction() {
    $options = $this->getFilterOptions();
    // optional name parameter - find id by full name
    $name = $this->_getParam("name", null);
    if (is_null($name)) {
      $coll = $this->_getParam("coll", "programs");
      $this->view->url_params = array("coll" => $coll);
      $coll = "#$coll";
    } else {
      $programObject = new foxmlPrograms();
      $prog = $programObject->skos;
      $coll = $prog->findIdbyLabel($name);
    }

    // add year to sort options
    $this->view->sort_fields[] = "year";
    
    try {
      $programObject = new foxmlPrograms($coll);
      $programs = $programObject->skos;
    } catch (XmlObjectException $e) {
      $message = "Error: Program not found";
      if ($this->env != "production") $message .= " (<b>" . $e->getMessage() . "</b>)";
      $this->_helper->flashMessenger->addMessage($message);
      $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "notfound"), "", true);
    }
    $this->view->collection = $programs;
    $this->view->browse_mode = "program"; 
    $etdSet = $programs->findEtds($options);
    $this->view->etdSet = $etdSet;    
    
    // Pagination Code
    $paginator = new Zend_Paginator($etdSet);
    $paginator->setItemCountPerPage(10);
    if ($this->_hasParam('page')) $paginator->setCurrentPageNumber($this->_getParam('page'));      
    $this->view->paginator = $paginator; 

    $this->view->title = "Browse Programs";
    if ($coll != "#programs") $this->view->title .= " : " . $programs->label;
     
    $this->view->action = "programs";
    // shared view script for programs & researchfields
    $this->_helper->viewRenderer->setScriptAction("collection");

  //        $this->view->feed = new Zend_Feed_Rss($this->_helper->absoluteUrl('recent', 'feeds', null,
  //                    array("program" => $programs->label)));
  }

  public function researchfieldsAction() {
    $options = $this->getFilterOptions();

    // optional name parameter - find id by full name
    $name = $this->_getParam("name", null);
    if (is_null($name)) {
      $coll = $this->_getParam("coll", "researchfields");

      // needed to generate remove-facet links
      $this->view->url_params = array("coll" => $coll);
      $coll = "#$coll";
    } else {
      $fields = new researchfields();
      $coll = $fields->findIdbyLabel($name);
    }
    
    try {
      $fields = new researchfields("$coll");
    } catch (XmlObjectException $e) {
      $message = "Error: Research Field not found";
      if ($this->env != "production")
      $message .= " (<b>" . $e->getMessage() . "</b>)";
      $this->_helper->flashMessenger->addMessage($message);
      $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error",
               "action" => "notfound"), "", true);
    }

    $this->view->collection = $fields;

    // add year to sort options
    $this->view->sort_fields[] = "year";

    $this->view->browse_mode = "researchfield";
    $etdSet = $fields->findEtds($options);
    $this->view->etdSet = $etdSet;
    
    // Pagination Code
    $paginator = new Zend_Paginator($etdSet);
    $paginator->setItemCountPerPage(10);       
    if ($this->_hasParam('page')) $paginator->setCurrentPageNumber($this->_getParam('page'));         
    $this->view->paginator = $paginator;        
    

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

    $options = $this->getFilterOptions();
    
    // if logged in user is a program coordinator, forward to the appropriate action
    if ($this->current_user->program_coord) {
      // FIXME: how to give super-user option to view the student/faculty pages?
      $this->_forward("my-program");
      return; 
    }

    $username = strtolower($this->current_user->netid);

    $pluralize = false;   // pluralize list description based on results found? (default is not)
    $viewscript = "list"; // default view script to use
    
    // expand to find by role, depending on current user - faculty, dept. staff, etc.
    switch ($this->current_user->role) {
    case "student":
    case "student with submission":
    case "honors student":
    case "honors student with submission":
      // records for this action : owned by current user (author) and not published
      $options["AND"]["ownerId"] = $username;
      // FIXME: is this filter necessary/useful ?
      //  $options["NOT"]["status"] = "published";    // any status other than published
      $this->view->list_description = "Your document";
      // in almost all cases, a student will only have 1 record; 
      // setting a flag to pluralize if necessary
      $pluralize = true;
      break;
    case "faculty":
    case "faculty with submission":
      /* NOTE: searching both advisor and committee fields.
         Advisor field is boosted in the Solr index, and results are
         being sorted by relevance, so advisor matches should be
         listed before committee member matches.       */
      $options["query"] = "(advisor_id:$username OR committee_id:$username)";
      // 2nd NOTE: using this syntax so that facets will work properly
      $options['sort'] = "relevance";
      $this->view->list_description = "Your students' documents";

      /**  NOTE: faculty could also be students
           (either recently changed status or finishing a second degree)
     Also find and list any records where the user is author/owner.  */
      $myopts = $this->getFilterOptions();
      $myopts["AND"]["ownerId"] = $username;
      $myEtds = new EtdSet($myopts, null, 'find'); 
      $this->view->myEtds = $myEtds;
      $this->view->my_description = "Your document";
      if ($myEtds->numFound != 1) $this->view->my_description .= "s";
      // Pagination Code
      $paginator = new Zend_Paginator($myEtds);
      $paginator->setItemCountPerPage(10);       
      if ($this->_hasParam('page')) $paginator->setCurrentPageNumber($this->_getParam('page'));         
      $this->view->paginator = $paginator;       

      // use an alternate view script to display both sets of etd results
      $viewscript = "faculty-my";
      break;
    default:
      // no records to display for this user
      return; // exit now-- don't perform a search
    }

    
    // find records using options set above, according to user role
    $etdSet = new EtdSet($options, null, 'find');
    $this->view->etdSet = $etdSet;    
    // if there is more than one record and pluralize is set, pluralize the list label
    if ($etdSet->numFound != 1 && $pluralize) {
      $this->view->list_description .= "s";
    }
    // Pagination Code
    $paginator = new Zend_Paginator($etdSet);
    $paginator->setItemCountPerPage(10);       
    if ($this->_hasParam('page')) $paginator->setCurrentPageNumber($this->_getParam('page'));         
    $this->view->paginator = $paginator;    
    
    $this->view->show_status = true;
    $this->view->show_lastaction = true;
    $this->_helper->viewRenderer->setScriptAction($viewscript);
  }


  // program coordinator view : list unpublished records for the specified department 
  public function myProgramAction() {
    // make sure user is a program coordinator before displaying any content
    if (!isset($this->current_user) || $this->current_user->program_coord == "") {
      $role = isset($this->current_user) ? $this->current_user->getRoleId() : "guest";
      $this->_helper->access->notAllowed("view", $role, "program coordinator view");
      return false;
    }

    $this->view->list_description = "Documents in your program";
    
    $options = $this->getFilterOptions();
    $options["return_type"] = "solrEtd";
  
    // allow program coordinators to sort on last modified
    $this->view->sort_fields[] = "modified";

    $etdSet = new EtdSet($options, null, 'findByDepartment', $this->current_user->program_coord);
    $this->view->etdSet = $etdSet;    
    
    // Pagination Code
    $paginator = new Zend_Paginator($etdSet);
    $paginator->setItemCountPerPage(10);       
    if ($this->_hasParam('page')) $paginator->setCurrentPageNumber($this->_getParam('page'));         
    $this->view->paginator = $paginator;    
    
    $title = "Program Records : " . $this->current_user->program_coord;
    $this->view->title = $title;
    $this->view->list_title = $title;
    $this->_helper->viewRenderer->setScriptAction("list");
    $this->view->show_status = true;
    // using solrEtd; last event in history not available from solr
    //    $this->view->show_lastaction = true;
  }


  public function recentAction() {
    $options = $this->getFilterOptions();
    
    $this->view->title = "Browse Recently Published";
    $this->view->list_title = "Recently Published";
    $etdSet = new EtdSet($options, null, 'findRecentlyPublished');
    $this->view->etdSet = $etdSet;    
    
    $this->_helper->viewRenderer->setScriptAction("list");
    
    // Pagination Code
    $paginator = new Zend_Paginator($etdSet);
    $paginator->setItemCountPerPage(10);       
    if ($this->_hasParam('page')) $paginator->setCurrentPageNumber($this->_getParam('page'));         
    $this->view->paginator = $paginator;
    
    // don't allow re-sorting by other fields (doesn't make sense)
    $this->view->sort = null;
   }

  public function mostViewedAction() {
    $this->view->title = "Most Viewed Records";
    $this->view->list_title = "Most Viewed Records";

    $options = $this->getFilterOptions();
    $etdSet = new EtdSet($options, null, 'findMostViewed');
    $this->view->etdSet = $etdSet;
    
    // Pagination Code
    $paginator = new Zend_Paginator($etdSet);
    $paginator->setItemCountPerPage(10);       
    if ($this->_hasParam('page')) $paginator->setCurrentPageNumber($this->_getParam('page'));         
    $this->view->paginator = $paginator;    
  
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
