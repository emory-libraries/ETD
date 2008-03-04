<?php

require_once("models/etd.php");
require_once("models/stats.php");

class ViewController extends Etd_Controller_Action {

   // view a full record
   public function recordAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     
     if (!$this->_helper->access->allowedOnEtd("view metadata", $etd)) return;
     $this->view->etd = $etd;
     $this->view->messages = $this->_helper->flashMessenger->getMessages();
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

     $object = $this->_helper->getFromFedora("pid", $type);

     if ($type == "etd") {
       if (!$this->_helper->access->allowedOnEtd("view metadata", $object)) return;
     } elseif ($type == "etdfile") {
       if (!$this->_helper->access->allowedOnEtdFile("view", $object)) return;
     }

     $datastream = $this->_getParam("datastream");
     $mode = $this->_getParam("mode");
     
     if (isset($object->$datastream)) {	// check that it is the correct type, also?
       $xml = $object->$datastream->saveXML();

       // disable layouts and view script rendering in order to set content-type header as xml
       $this->getHelper('layoutManager')->disableLayouts();
       $this->_helper->viewRenderer->setNoRender(true);
       
       $this->getResponse()->setHeader('Content-Type', "text/xml")->setBody($xml);
     } else {
       $this->_helper->flashMessenger("Error: invalid xml datastream");
       // do something with this message?
     }
   }
   

}
?>