<?php
/** Zend_Controller_Action */
/* Require models */

require_once("models/etd.php");
require_once("models/programs.php");

class BrowseController extends Zend_Controller_Action {

  protected $_flashMessenger = null;

  protected $browse_field;

   public function init() {
     $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
     $this->initView();

          $params =  $this->_getAllParams();
     
     $this->view->controller = $params['controller'];
     $this->view->action = $params['action'];

   }

   public function postDispatch() {
     $this->view->messages = $this->_helper->flashMessenger->getMessages();
   }

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

     if (is_null($value)) {
       $request->setParam("field", $type . "_lastnamefirst");
       $this->_forward("browseField");
     } else {
       $request->setParam("field", $type);
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
       $this->_forward("browseField");
     } else {
       $request->setParam("field", $type);
       $this->_forward("browse");
     }
   }
   


   /* common functionality - get a list of terms by field
      expects field parameter to be set 
    */
   public function browseFieldAction() {
     $request = $this->getRequest();
     $field = $request->getParam("field");

     $solr = Zend_Registry::get('solr');
     $results = $solr->browse($field);
     //     print "<pre>"; print_r($results); print "</pre>";
     $this->view->count = count($results['facet_counts']['facet_fields'][$field]);
     $this->view->values = $results['facet_counts']['facet_fields'][$field];
     $this->view->title = "Browse " . $this->view->browse_mode . "s";
   }



   
   // list of records by field + value
   public function browseAction() {
     $request = $this->getRequest();
     $field = $request->getParam("field");
     $_value = urldecode($request->getParam("value"));	// store original form
     $value = $_value;
     $value = urldecode($value);
     $exact = $request->getParam("exact", false);
     if (! $exact) {
       $value = strtolower($value);
       $value = str_replace(",", "", $value);	// remove commas from names
     }
     
     $solr = Zend_Registry::get('solr');

     // note - solr default set to OR (seems to work best)
     //splitting out name parts to get an accurate match here (e.g.,
     //not matching committee members with a common first name)
     if ($exact) {
       $query = "$field:$value";
     } else {
       $queryparts = array();
       foreach (preg_split('/[ +,.-]+/ ', strtolower($value), -1, PREG_SPLIT_NO_EMPTY) as $part) {
	 if ($part != "a")	// stop words that cause solr problem
	   array_push($queryparts, "$field:$part");
       }
       $query = join($queryparts, '+AND+');
     }
     //     print "query is $query\n";
     //$results = $solr->query("$field:($value)");
     $results = $solr->query($query);
     
     /*       $results = solrQuery("$mode:$value"); */
     
     $this->view->count = $results['response']['numFound'];

     //
     $etds = array();
     foreach ($results['response']['docs'] as $result_doc) {
       array_push($etds, new etd($result_doc['PID']));
     }
     //     $this->view->etds = $results['response']['docs'];
     $this->view->etds = $etds;
     $this->view->facets = $results['facet_counts']['facet_fields'];

     // if there's only one match found, forward directly to full record view
     if ($this->view->count == 1) {
       $this->_helper->flashMessenger->addMessage("Only one match found; displaying full record");
       $this->_helper->redirector->gotoRoute(array("controller" => "view",
						   "action" => "record", "pid" => $etds[0]->pid), "", true);
     } 

     
     $this->view->value = $_value;
     $this->view->results = $results;
     
     $this->view->title = "Browse by " . $field . " : " . $_value;
   }


   /* browse hierarchies - programs, research fields */


   /* browse by program */
   public function programsAction() {
     $skos = new DOMDocument();
     $skos->load("../config/programs.xml");   // better place, better way to initialize?

     // optional name parameter - find id by full name
     $name = $this->_getParam("name", null);
     if (is_null($name)) {
       $coll = $this->_getParam("coll", "programs");
       $coll = "#$coll";
     } else {
       $prog = new programs($skos);
       $coll = $prog->findIdbyLabel($name);
     }

     $programs = new programs($skos, $coll);
     $this->view->collection = $programs;

     $this->view->browse_mode = "program"; // fixme: singular or plural?


     $results = $programs->findEtds();

     $this->view->count = $results['response']['numFound'];
          $this->view->results = $results;
     
     $etds = array();
     foreach ($results['response']['docs'] as $result_doc) {
       array_push($etds, new etd($result_doc['PID']));
     }

     $this->view->etds = $etds;
     $this->view->facets = $results['facet_counts']['facet_fields'];
     
     // shared view script for programs & researchfields
     $this->view->action = "programs";
     $this->_helper->viewRenderer->setScriptAction("collection");
   }

   public function researchfieldsAction() {
     $skos = new DOMDocument();
     $skos->load("../config/umi-researchfields.xml");   // better place?
     $request = $this->getRequest();
     $coll = $request->getParam("coll", "researchfields");

     $fields = new researchfields($skos, "#$coll");

     $this->view->collection = $fields;

     $results = $fields->findEtds();

     $this->view->browse_mode = "researchfield"; 

     $this->view->count = $results['response']['numFound'];
          $this->view->results = $results;
     
     $etds = array();
     foreach ($results['response']['docs'] as $result_doc) {
       array_push($etds, new etd($result_doc['PID']));
     }
     $this->view->etds = $etds;
     $this->view->facets = $results['facet_counts']['facet_fields'];

     // shared view script for programs & researchfields
     $this->view->action = "researchfields";
     $this->_helper->viewRenderer->setScriptAction("collection");
   }



   // list a user's ETDs
   public function myAction() {
     $auth = Zend_Auth::getInstance();
     if ($auth->hasIdentity()) {
       $user = $auth->getIdentity();
       // should be expanded to find by role, depending on current user - faculty, dept. staff, etc.
       $this->view->etds = etd::findbyAuthor(strtolower($user->netid));
      
     }
     $this->view->title = "My ETDs";
     $this->_helper->viewRenderer->setScriptAction("list");
   }

   
   // nice readable url - redirect to proquest listings for emory
   public function proquestAction() {
     // note: should this url be configurable, stored somewhere else?
     $this->_redirect("http://proquest.umi.com.proxy.library.emory.edu/pqdweb?RQT=305&SQ=LSCHNAME%28%7BEMORY+UNIVERSITY%7D%29&clientId=1917");
   }



      // FIXME: this doesn't really belong here... where should it go?
   public function searchAction() {
     $request = $this->getRequest();
     $query = $request->getParam("q");

     $solr = Zend_Registry::get('solr');
     $results = $solr->query(urlencode($query));
     
     $this->view->count = $results['response']['numFound'];
     $this->view->etds = $results['response']['docs'];
     
     $this->view->facets = $results['facet_counts']['facet_fields'];
     
     /*       $this->view->count = $results->response->numFound;
      $this->view->etds = $results->response->docs;
      $this->view->facets = $results->facet_counts->facet_fields;*/
     
     $this->view->results = $results;
     $this->view->title = "Search"; 
     $this->_helper->viewRenderer->setScriptAction("browse");
   }

   public function indexAction() {
     // FIXME: do we need a main browse page?
		$this->view->assign("title", "Welcome to %project%");
   }

}
?>