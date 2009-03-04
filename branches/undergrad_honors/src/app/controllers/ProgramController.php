<?php

class ProgramController extends Etd_Controller_Action {


  // display config xml -- used in various xforms
  public function viewAction() {
    $request = $this->getRequest();
    $id = $request->getParam("id");


    
    if ($id=="programs"){
     	$config = Zend_Registry::get("config");
    	$pid=$config->programs_pid;

	    $fedora = Zend_Registry::get('fedora'); 	
	    $this->_helper->displayXml($fedora->getDatastream($pid, 'SKOS'));
	
    }
  }
  
}
