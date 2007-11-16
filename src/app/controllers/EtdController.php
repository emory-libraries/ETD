<?php
/** Zend_Controller_Action */
/* Require models */
require_once("models/etd.php");

class EtdController extends Zend_Controller_Action {

  protected $_flashMessenger = null;

   public function init() {
     $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
     $this->initView();
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
     $this->view->etd = new etd($this->_getParam("pid"));
     
   }
   
   public function deleteAction() {
   }

   public function fileAction() {

     // FIXME: write routes or set headers so file will save with original filename
     
     $pid = $this->_getParam("pid");
     $etdfile = new etd_file($pid);

     //     $this->getResponse()->setHeader('Content-Type', $etdfile->dc->type);
     $this->getResponse()->setHeader('Content-Disposition',
				     'attachment; filename="' . $etdfile->dc->description);

     
     $fedora = Zend_Registry::get('fedora-config');
     $url = "http://" . $fedora->server . ":" . $fedora->port . "/fedora/get/$pid/FILE";

     // do a redirect so the file doesn't get loaded into memory by php
     $this->_redirect($url);
     // for debugging redirect url:
     //    $this->_helper->viewRenderer->setNoRender(true);

   }
}
?>