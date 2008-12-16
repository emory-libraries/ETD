<?php

require_once("models/etd.php");
require_once("models/stats.php");

class ViewController extends Etd_Controller_Action {

  protected $requires_fedora = true;
  
   // view a full record
   public function recordAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     if ($etd) {
       if (!$this->_helper->access->allowedOnEtd("view metadata", $etd)) return false;
       
       $this->view->etd = $etd;
       $this->view->title = $etd->dc->title;	// need non-formatted version of title
       //$this->view->dc = $etd->dc;		/* DC not as detailed as MODS; using etd DC header template */
       $this->view->messages = $this->_helper->flashMessenger->getMessages();

       // ETD abstract page should have print view link
       $this->view->printable = true;
     }
   }

   // show mods xml - referenced as model for xform
   public function modsAction() {
     $this->_setParam("datastream", "mods");
     $this->_forward("xml");
   }

   // show dublin core xml 
   public function dcAction() {
     $this->_setParam("datastream", "dc");
     $this->_forward("xml");
   }
   

   // show an xml datastream from etd or etdFile object
   public function xmlAction() {
     $type = $this->_getParam("type", "etd");

     if ($type == "etd") {
       $object = $this->_helper->getFromFedora("pid", $type);
       if (!$this->_helper->access->allowedOnEtd("view metadata", $object)) return;
     } elseif ($type == "etdfile") {
       $object = $this->_helper->getFromFedora("pid", "etd_file");
       if (!$this->_helper->access->allowedOnEtdFile("view", $object)) return;
     }

     $datastream = $this->_getParam("datastream");

     // new field added to make PQ submission optional for Masters students (add field if not present)
     // used in edit/rights action
     if (($type == "etd" && $datastream == "mods") && !isset($object->mods->pq_submit)) {
       $object->mods->addNote("", "admin", "pq_submit");
       // PhDs are required to submit to ProQuest
       if ($object->mods->degree->name == "PhD") {
	 $object->mods->pq_submit = "yes";
       }
     }

     $mode = $this->_getParam("mode");
     
     if (isset($object->$datastream)) {	// check that it is the correct type, also?
       $this->_helper->displayXml($object->$datastream->saveXML());
     } else {
       throw new Exception("'$datastream' is not a valid datastream for " . $object->pid);
     }
   }
   

}
?>