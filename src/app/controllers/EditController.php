<?php
/** Zend_Controller_Action */

/* Require models */
require_once("models/etd.php");

class EditController extends Zend_Controller_Action {

  // edit main record metadata
  // better name?
  public function modsAction() {
     $pid = $this->_getParam("pid");
     $etd = new etd($pid);

     $env = Zend_Registry::get('env-config');
     $this->view->site_mode = $env->mode;	// better name for this? (test/dev/prod)

     $this->view->title = "Edit Record Information";
    $this->view->etd = $etd;
    // xforms setting - so layout can include needed code in the header
    $this->view->xforms = true;
    //    $this->view->xforms_model_xml = $etd->mods->saveXML();
    // link to xml rather than embedding directly in the page
    $this->view->xforms_model_uri = $this->view->url(array("controller" => "etd",
							   "action" => "xml", "ds" => "mods", "pid" => $pid));
  }

  // formatted/html fields - edit one at a time 
  public function titleAction() {
    $this->_setParam("mode", "title");
    $this->_forward("html");
  }

  public function abstractAction() {
    $this->_setParam("mode", "abstract");
    $this->_forward("html");
  }
  
  public function contentsAction() {
    $this->_setParam("mode", "contents");
    $this->_forward("html");
  }

  // edit formatted fields
  public function htmlAction() {
     $pid = $this->_getParam("pid");
     $mode = $this->_getParam("mode", "title");
     $etd = new etd($pid);

     $this->view->mode = $mode;
     $this->view->etd_title = $etd->mods->title;
     $this->view->edit_content = $etd->html->{$mode};
     $this->view->title = "edit $mode";
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



  
}