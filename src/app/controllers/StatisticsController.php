<?php
/** Zend_Controller_Action */

require_once("models/stats.php");
require_once("countries.php");

class StatisticsController extends Zend_Controller_Action {

  protected $_flashMessenger = null;

   public function init() {
     $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
     $this->initView();
   }

   public function postDispatch() {
     $this->view->messages = $this->_helper->flashMessenger->getMessages();
   }

   // statistics for a single record
   public function recordAction() {
     // fixme: error handling ?
     $pid = $this->_getParam("pid", null);
     $etd = new etd($pid);

     $stats = new StatObject();
     $pids = array();
     $pids[] = $etd->pid;
     foreach ($etd->pdfs as $pdf) $pids[] = $pdf->pid;
     
     $this->view->total = $stats->count($pids);
     $this->view->country = $stats->countByCountry($pids);
     $this->view->countries = new CountryNames;

     
     $this->view->etd = $etd;
     $this->view->title = "Access Statistics for " . $etd->label;


     
   }

  
}
