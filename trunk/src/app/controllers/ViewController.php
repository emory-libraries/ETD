<?php

require_once("models/etd.php");
require_once("models/stats.php");

class ViewController extends Etd_Controller_Action {

  protected $requires_fedora = true;
  
   // view a full record
   public function recordAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     if ($etd) {
       if (!$this->_helper->access->allowedOnEtd("view metadata", $etd)) return;
       $this->view->etd = $etd;
       $this->view->title = $etd->label;
       //$this->view->dc = $etd->dc;		/* DC not as detailed as MODS; using etd DC header template */
       $this->view->messages = $this->_helper->flashMessenger->getMessages();
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
     $mode = $this->_getParam("mode");
     
     if (isset($object->$datastream)) {	// check that it is the correct type, also?
       $this->_helper->displayXml($object->$datastream->saveXML());
     } else {
       $this->_helper->flashMessenger->addMessage("Error: invalid xml datastream");
       // do something with this message?
     }
   }
   

}
?>