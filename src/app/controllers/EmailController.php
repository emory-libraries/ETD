<?php

require_once("models/etd.php");
require_once("models/EtdSet.php");

/**
 * management functions to view & edit email templates
 * 
 * @category Etd
 * @package Etd_Controllers
 */
class EmailController extends Etd_Controller_Action {
  protected $requires_fedora = true;
  
   public function indexAction() {
     $this->view->title = "Manage Email Templates";
   }

   public function viewAction() {
     $config = Zend_Registry::get('config');
    // Contact information
    // Should normally be set in the notifer
    //This is passed to the view.phtml and then to the partial as "contact"
     $this->view->contact = $config->contact;
     $template = $this->_getParam("template");
     $this->view->template = $template;
     $this->view->template_label = ucwords(str_replace("_", " ", $template));
     $this->view->title = "Review " . $this->view->template_label . " Email Template";
     
     // find a set of few different sample records to view the template with
     // FIXME: probably need to be more systematic here; include one from each program, etc.
     // 2nd FIXME: embargo expiration emails should not list records with no embargo
     $this->view->etds = array();
     $etdset = new EtdSet();
     $searchopts = array("AND" => array("degree_name" => "PhD",
					"status" => "published",
					"embargo_duration" => '"0 days"'),
			 "max" => 1, "facets" => array("clear" => true),
			 "return_type" => "solrEtd");
     $etdset->find($searchopts);
     if ($etdset->numFound) 
       $this->view->etds["PhD, no embargo"] = $etdset->etds[0];

     // search for phd with embargo
     unset($searchopts["AND"]["embargo_duration"]);
     $searchopts["NOT"]["embargo_duration"] = '"0 days"';
     $etdset->find($searchopts);
     if ($etdset->numFound) 
       $this->view->etds["PhD, embargoed"] = $etdset->etds[0];

     // search for MS with embargo
     $searchopts["AND"]["degree_name"] = "MS";
     $etdset->find($searchopts);
     if ($etdset->numFound) 
       $this->view->etds["MS, embargoed"] = $etdset->etds[0];

     // ms without embargo
     unset($searchopts["NOT"]["embargo_duration"]);
     $searchopts["AND"]["embargo_duration"] = '"0 days"';
     $etdset->find($searchopts);
     if ($etdset->numFound) 
       $this->view->etds["MS, no embargo"] = $etdset->etds[0];

     // bs without embargo
     $searchopts["AND"]["degree_name"] = "BS";
     $etdset->find($searchopts);
     if ($etdset->numFound) 
       $this->view->etds["BS, no embargo"] = $etdset->etds[0];

     // bs with embargo
     unset($searchopts["AND"]["embargo_duration"]);
     unset($searchopts["AND"]["date_embargoedUntil"]);
     //$searchopts["NOT"]["embargo_duration"] = '"0 days"';
     $searchopts["AND"]["date_embargoedUntil"] = "[" . date("Ymd") . " TO *]";
     $etdset->find($searchopts);
     if ($etdset->numFound) 
       $this->view->etds["BS, embargoed"] = $etdset->etds[0];
     // ThD without embargo
     $searchopts["AND"]["degree_name"] = "ThD";
     $searchopts["AND"]["embargo_duration"] = '"0 days"';
     $etdset->find($searchopts);
     if ($etdset->numFound)
       $this->view->etds["ThD, no embargo"] = $etdset->etds[0];

     // ThD with embargo
     unset($searchopts["AND"]["embargo_duration"]);
     unset($searchopts["AND"]["date_embargoedUntil"]);
     //$searchopts["NOT"]["embargo_duration"] = '"0 days"';
     $searchopts["AND"]["date_embargoedUntil"] = "[" . date("Ymd") . " TO *]";
     $etdset->find($searchopts);
     if ($etdset->numFound)
       $this->view->etds["ThD, embargoed"] = $etdset->etds[0];

       // MPH without embargo
     unset($searchopts["AND"]["date_embargoedUntil"]);
     $searchopts["AND"]["degree_name"] = "MPH";
     $searchopts["AND"]["embargo_duration"] = '"0 days"';
     $etdset->find($searchopts);
     if ($etdset->numFound)
       $this->view->etds["MPH, no embargo"] = $etdset->etds[0];

     // MPH with embargo
     unset($searchopts["AND"]["embargo_duration"]);
     unset($searchopts["AND"]["date_embargoedUntil"]);
     //$searchopts["NOT"]["embargo_duration"] = '"0 days"';
     $searchopts["AND"]["date_embargoedUntil"] = "[" . date("Ymd") . " TO *]";
     $etdset->find($searchopts);
     if ($etdset->numFound)
       $this->view->etds["MPH, embargoed"] = $etdset->etds[0];


// if no pid is specified, just use the first etd in the list of sample records
     if (!$this->_hasParam("pid")) {
       $keys = array_keys($this->view->etds);
       $this->_setParam("pid", $this->view->etds[$keys[0]]->pid());
     }
     
     $this->view->etd = $this->_helper->getFromFedora("pid", "etd");
     
   }

}


