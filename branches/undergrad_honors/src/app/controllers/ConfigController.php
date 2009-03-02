<?php

class ConfigController extends Etd_Controller_Action {


  // display config xml -- used in various xforms
  public function viewAction() {
    $request = $this->getRequest();
    $id = $request->getParam("id");
    
    switch($id) {
    case "countries":
    case "degrees":  
    case "languages":
      $config_dir = Zend_Registry::get("config-dir");
      $xml = file_get_contents($config_dir . $id . ".xml");

      $this->_helper->displayXml($xml);
      break;
    default:
      // should generate error message here- invalid config id 
    }
  }
  
}
