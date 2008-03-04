<?php

require_once("models/etd.php");
require_once("models/stats.php");

class ViewController extends Etd_Controller_Action {

   public function indexAction() {	
     $this->view->assign("title", "Welcome to %project%");
   }
   
   // note: copied from AdminController - consolidate?
   private function isAllowed($etd, $action) {
     $role = $etd->getUserRole($this->view->current_user);
     $allowed = $this->view->acl->isAllowed($role, $etd, $action);
     if (!$allowed) {
       $this->_helper->flashMessenger->addMessage("Error: " . $this->view->current_user->netid . " (role=" . $role . 
						  ") is not authorized to $action " . $etd->getResourceId());
       $this->_helper->redirector->gotoRoute(array("controller" => "auth",
						   "action" => "denied"), "", true);
     }
     return $allowed;
   }



   // view a full record
   public function recordAction() {
     // fixme: error handling if pid is not specified?
     $pid = $this->_getParam("pid", null);
     if (is_null($pid)) {
       trigger_error("No record specified", E_USER_WARNING);
       $this->_helper->viewRenderer->setNoRender(true);
     } else {
       try {
	 $etd = new etd($pid);
	 if ($etd->cmodel != "etd") {
	   trigger_error("Wrong type of object - $pid is not an etd", E_USER_WARNING);
	   $this->_helper->viewRenderer->setNoRender(true);
	   return;
	 }
	 
	 
	 $this->view->etd = $etd;
	 $this->view->title = $etd->label;
	 $this->view->dc = $etd->dc;
       } catch (FedoraObjectNotFound $e) {
	 trigger_error("Record not found: $pid", E_USER_WARNING);
	 $this->_helper->viewRenderer->setNoRender(true);
	 /*       } catch (FedoraAccessDenied $e) {
	 trigger_error("Access Denied to record: $pid", E_USER_WARNING);
	 $this->_helper->viewRenderer->setNoRender(true);*/
       }
     }

     if (!$this->isAllowed($etd, "view metadata")) return;

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
   

   // show etd xml datastream
   public function xmlAction() {
     $pid = $this->_getParam("pid");
     $type = $this->_getParam("type", "etd");

     if ($type == "etd") {
       $etd = new etd($pid);
       if (!$this->isAllowed($etd, "view metadata")) return;
     } elseif ($type == "etdfile") {
       $etd = new etd_file($pid);
       // FIXME: how to check if etd_file can be viewed?
     }

     $datastream = $this->_getParam("datastream");
     $mode = $this->_getParam("mode");
     
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