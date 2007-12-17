<?php
/** Zend_Controller_Action */
/* Require models */
require_once("models/etd.php");
require_once("helpers/ProcessPDF.php");

class ViewController extends Zend_Controller_Action {

  protected $_flashMessenger = null;

   public function init() {
     $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
     $this->initView();
   }
   
   public function indexAction() {	
     $this->view->assign("title", "Welcome to %project%");
   }


   // view a full record
   public function recordAction() {
     $etd = new etd($this->_getParam("pid"));	// fixme: error handling if pid is not specified?

     $this->view->etd = $etd;
     $this->view->title = $etd->label;
     $this->view->dc = $etd->dc;
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
   

   // show etd xml datastream
   public function xmlAction() {
     $pid = $this->_getParam("pid");
     $type = $this->_getParam("type", "etd");

     if ($type == "etd")
       $etd = new etd($pid);
     elseif ($type == "etdfile")
       $etd = new etd_file($pid);

     
     $datastream = $this->_getParam("datastream");
     
     if (isset($etd->$datastream)) {	// check that it is the correct type, also?
       $xml = $etd->$datastream->saveXML();

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