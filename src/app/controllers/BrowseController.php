<?php
/** Zend_Controller_Action */
/* Require models */

require_once("solr.php");
require_once("models/solrEtd.php");
require_once("models/programs.php");

class BrowseController extends Zend_Controller_Action {

  protected $_flashMessenger = null;

  protected $browse_field;

   public function init() {
     $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
     $this->initView();
   }


   // list of terms by field
   public function browseFieldAction() {
     $request = $this->getRequest();
     $field = $request->getParam("field");

     $solr = new solr("mothra.library.emory.edu", "8983");
     $results = $solr->browse($field);
     //     print "<pre>"; print_r($results); print "</pre>";
     $this->view->count = count($results['facet_counts']['facet_fields'][$field]);
     $this->view->values = $results['facet_counts']['facet_fields'][$field];
     $this->view->title = "Browse " . $this->view->mode . "s";
   }

   // FIXME: this doesn't really belong here... where should it go?
   public function searchAction() {
     $request = $this->getRequest();
     $query = $request->getParam("q");
     $solr = new solr("mothra.library.emory.edu", "8983");

     $solr->addFacets(array("advisor_lastnamefirst", "year", "subject_facet",
			    "author_lastnamefirst", "committee_lastnamefirst"));
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

   
   public function __call($method, $args) {
     $request = $this->getRequest();
     $params = $request->getParams();
     $action = $request->getParam("action");	// okay to use this instead of $method ?

     // note: method is authorAction, etc. 
     //     $name = str_replace("Action", "", $method);
     
     switch($action) {
     case "author":
     case "committee":
     case "advisor":
       if (isset($params['value']))
	 $request->setParam("field", $action);
       else
	 $request->setParam("field", $action . "_lastnamefirst");
       break;
     case "status":	// hidden, not really implemented...
     case "year":
     case "program":	// temporary, should be a controlled vocab somehow
       $request->setParam("field", $action);
       $request->setParam("exact", true);
       break;
     case "researchfield":	// temporary, should be a controlled vocab somehow
       if (isset($params['value']))
	 $request->setParam("field", "subject");
       else 
	 $request->setParam("field", "subject_facet");
     }
     $this->view->browse_mode = $action;

     if (isset($params['value'])) {
       $this->_forward("browse");
     } else {
       $this->_forward("browseField");
     }

     /*     print "params are<pre>";
     print_r($params);
     print "</pre>";
     $this->_helper->viewRenderer->setScriptAction("index");*/
   }

   
   // list of records by field + value
   public function browseAction() {
     $request = $this->getRequest();
     $field = $request->getParam("field");
     $_value = $request->getParam("value");	// store original form
     $value = $_value;
     $exact = $request->getParam("exact");
     if (! $exact) 
       $value = strtolower($value);
     
     $value = urlencode($value);
     
     $solr = new solr("mothra.library.emory.edu", "8983");
     $solr->addFacets(array("advisor_lastnamefirst", "year", "subject_facet", "program",
			    "author_lastnamefirst", "committee_lastnamefirst"));

     // note - solr default set to OR (seems to work best)
     //splitting out name parts to get an accurate match here (e.g.,
     //not matching committee members with a common first name)
     if ($exact) {
       $query = "$field:$value";
     } else {
       $queryparts = array();
       foreach (preg_split('/[ +,.-]+/ ', strtolower($_value)) as $part)
	 array_push($queryparts, "$field:$part");
       $query = join($queryparts, '+AND+');
     }
     //       print "query is $query\n";
     //     $results = $solr->query("$field:($value)");
     $results = $solr->query($query);
     
     /*       $results = solrQuery("$mode:$value"); */
     
     $this->view->count = $results['response']['numFound'];

     //
     $etds = array();
     foreach ($results['response']['docs'] as $result_doc) {
       array_push($etds, new solrEtd($result_doc));
     }
     //     $this->view->etds = $results['response']['docs'];
     $this->view->etds = $etds;
     $this->view->facets = $results['facet_counts']['facet_fields'];

     /*     $testetd = new solrEtd($results['response']['docs'][0]);
     print "testing solrEtd\n";
     print "title: " . $testetd->title . "<br>\n";
     print "<pre>"; print_r($testetd); print "</pre>";
     print "<pre>"; print_r($results['response']['docs'][0]); print "</pre>";*/

     /*       $this->view->count = $results->response->numFound;
      $this->view->etds = $results->response->docs;
      $this->view->facets = $results->facet_counts->facet_fields;*/
     
     $this->view->value = $_value;
     $this->view->results = $results;
     
     
     
     $this->view->title = "Browse by " . $field . " : " . $_value;
   }


   public function programsAction() {
     $skos = new DOMDocument();
     $skos->load("../../notes/programs.skos");	// temporary
     $request = $this->getRequest();
     $coll = $request->getParam("coll", "programs");

     $programs = new programs($skos, "#$coll");
     $this->view->collection = $programs;

     $this->view->browse_mode = "program"; // fixme: singular or plural?


     $solr = new solr("mothra.library.emory.edu", "8983");
     $solr->addFacets(array("advisor_lastnamefirst", "year", "subject_facet", "program",
			    "author_lastnamefirst", "committee_lastnamefirst"));

     // get all fields of this collection and its members (all the way down)
     $all_fields = $programs->getAllFields();
     // construct a query that will find any of these
     $queryparts = array();
     foreach ($all_fields as $field)
       array_push($queryparts, "program:(" . urlencode($field) . ")");

     $query = join($queryparts, "+OR+");
     
     $results = $solr->query($query);
     
     $this->view->count = $results['response']['numFound'];

     // sum up totals (FIXME: only goes one level down currently; make recursive?)
     $totals = $results['facet_counts']['facet_fields']['program'];
     foreach ($programs->members as $mem) {
       if (!isset($totals[$mem->label])) {
	 $totals[$mem->label] = 0;
	 foreach ($mem->members as $child) {
	   if (isset($totals[$child->label]))
	     $totals[$mem->label] += $totals[$child->label];
	 }
       }
     }
	      
     $this->view->program_totals = $totals;
     $this->view->results = $results;
     
     $etds = array();
     foreach ($results['response']['docs'] as $result_doc) {
       array_push($etds, new solrEtd($result_doc));
     }
     //     $this->view->etds = $results['response']['docs'];
     $this->view->etds = $etds;
     $this->view->facets = $results['facet_counts']['facet_fields'];

   }

	public function indexAction() {	
		$this->view->assign("title", "Welcome to %project%");
	}

	public function listAction() {
	}

	public function createAction() {
	}

	public function editAction() {
	}

	public function saveAction() {
	}

	public function viewAction() {
	}

	public function deleteAction() {
	}
}
?>