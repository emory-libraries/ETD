<?php

require_once("models/etd.php");
require_once("models/EtdSet.php");
//require_once("models/etd_notifier.php");

/**
 * management functions to view & edit email templates
 */
class EmailController extends Etd_Controller_Action {
  protected $requires_fedora = true;
  
   public function indexAction() {
     $this->view->title = "Manage Email Templates";
   }

   public function viewAction() {
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
					"embargo_duration" => "0 days"),
			 "max" => 1, "facets" => array("clear" => true),
			 "return_type" => "solrEtd");
     $etdset->find($searchopts);
     if ($etdset->numFound) 
       $this->view->etds["PhD, no embargo"] = $etdset->etds[0];
     unset($searchopts["AND"]["embargo_duration"]);
     $searchopts["NOT"]["embargo_duration"] = "0 days";
     $etdset->find($searchopts);
     if ($etdset->numFound) 
       $this->view->etds["PhD, embargoed"] = $etdset->etds[0];
     $searchopts["AND"]["degree_name"] = "MS";
     $etdset->find($searchopts);
     if ($etdset->numFound) 
       $this->view->etds["MS, embargoed"] = $etdset->etds[0];
     $searchopts["AND"]["degree_name"] = "BA";
     $etdset->find($searchopts);
     if ($etdset->numFound) 
       $this->view->etds["BS, embargoed"] = $etdset->etds[0];

     // if no pid is specified, just use the first etd in the list of sample records
     if (!$this->_hasParam("pid")) {
       $keys = array_keys($this->view->etds);
       $this->_setParam("pid", $this->view->etds[$keys[0]]->pid());
     }
     
     $this->view->etd = $this->_helper->getFromFedora("pid", "etd");
     
   }

}


