<?php
/** Zend_Controller_Action */
/* Require models */
require_once("models/etd.php");
require_once("helpers/ProcessPDF.php");

class EtdController extends Zend_Controller_Action {

  protected $_flashMessenger = null;

   public function init() {
     $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
     $this->initView();
   }
   
   public function indexAction() {	
     $this->view->assign("title", "Welcome to %project%");
   }
   
   public function createAction() {
   }
   
   public function editAction() {
   }

   public function editModsAction() {
     $pid = $this->_getParam("pid");
     $etd = new etd($pid);

     $env = Zend_Registry::get('env-config');
     $this->view->site_mode = $env->mode;	// better name for this? (test/dev/prod)
     
    $this->view->etd = $etd;
    $this->view->xforms = true;
    //    $this->view->xforms_model_xml = $etd->mods->saveXML();
    $this->view->xforms_model_uri = $this->view->url(array("controller" => "etd",
							   "action" => "xml", "ds" => "mods", "pid" => $pid));
  }

   // show mods - referenced as model for xform
   // FIXME: maybe make this a more generic 
   public function xmlAction() {
     $etd = new etd($this->_getParam("pid"));

     $datastream = $this->_getParam("ds");
     if (isset($etd->$datastream)) {
       $xml = $etd->$datastream->saveXML();
       $this->getHelper('layoutManager')->disableLayouts();
       //  $this->_helper->viewRenderer->setScriptAction("xml");
       $this->_helper->viewRenderer->setNoRender(true);
       
       //     $this->getResponse()->setHeader('Content-Type', $etdfile->dc->type);
       $this->getResponse()->setHeader('Content-Type', "text/xml")->setBody($xml);
     } else {
       $this->_helper->flashMessenger("invalid xml datastream");
     }
       

   }

   // fixme: not actually saving anything yet...
   public function savemodsAction() {
     global $HTTP_RAW_POST_DATA;
     $xml = $HTTP_RAW_POST_DATA;
    if ($xml == "") {
      // if no xml is submitted, don't modify 
      // forward to a different view?
      $this->view->noxml = true;
    }
    $this->view->xml = $xml;

    $this->view->title = "edit mods";
    
   }

   
   public function saveAction() {
   }
   
   public function viewAction() {
     $etd = new etd($this->_getParam("pid"));
     $this->view->etd = $etd;
     $this->view->title = $etd->label;
     $this->view->dc = $etd->dc;
   }
   
   public function deleteAction() {
   }


   // serve out a file attached to an ETD record from fedora
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