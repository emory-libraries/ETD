<?php
/** Zend_Controller_Action */
/* Require models */

require_once("models/solrEtd.php");
require_once("models/programs.php");

class BrowseController extends Zend_Controller_Action {

  protected $_flashMessenger = null;

  protected $browse_field;

   public function init() {
     $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
     $this->initView();
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
     $_value = $request->getParam("value");	// store original form
     $value = $_value;
     $exact = $request->getParam("exact");
     if (! $exact) 
       $value = strtolower($value);
     
     $value = urlencode($value);
     $solr = Zend_Registry::get('solr');

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

     $this->view->value = $_value;
     $this->view->results = $results;
     
     $this->view->title = "Browse by " . $field . " : " . $_value;
   }


   /* browse hierarchies - programs, research fields */


   /* browse by program */
   public function programsAction() {
     $skos = new DOMDocument();
     $skos->load("../config/programs.xml");   // better place, better way to initialize?
     $request = $this->getRequest();
     $coll = $request->getParam("coll", "programs");

     $programs = new programs($skos, "#$coll");
     $this->view->collection = $programs;

     $this->view->browse_mode = "program"; // fixme: singular or plural?


     $results = $programs->findEtds();

     $this->view->count = $results['response']['numFound'];
          $this->view->results = $results;
     
     $etds = array();
     foreach ($results['response']['docs'] as $result_doc) {
       array_push($etds, new solrEtd($result_doc));
     }

     $this->view->etds = $etds;
     $this->view->facets = $results['facet_counts']['facet_fields'];
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
       array_push($etds, new solrEtd($result_doc));
     }
     $this->view->etds = $etds;
     $this->view->facets = $results['facet_counts']['facet_fields'];

     // temporary - just for testing;
     // should be able to combine code for programs & researchfields
     $this->_helper->viewRenderer->setScriptAction("programs");
   }



     public function oldprogramsAction() {
     
     $solr = Zend_Registry::get('solr');
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

     $this->view->etds = $etds;
     $this->view->facets = $results['facet_counts']['facet_fields'];

   }




   // list a user's ETDs
   public function myAction() {
     $auth = Zend_Auth::getInstance();
     if ($auth->hasIdentity()) {
       $identity = $auth->getIdentity();
       // should be expanded to find by role, depending on current user - faculty, dept. staff, etc.
       $this->view->etds = etd::findbyAuthor($identity);
      
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