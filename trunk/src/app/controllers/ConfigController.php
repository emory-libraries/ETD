<?php

class ConfigController extends Zend_Controller_Action {


  // display config xml -- used in various xforms
  public function viewAction() {
    $request = $this->getRequest();
    $id = $request->getParam("id");

    switch($id) {
    case "countries":
    case "degrees":
    case "programs":
    case "languages":
      $xml = file_get_contents("../config/" . $id . ".xml");
      // FIXME: move logic into a controller helper?
      $this->getHelper('layoutManager')->disableLayouts();
      $this->_helper->viewRenderer->setNoRender(true);
      $this->getResponse()->setHeader('Content-Type', "text/xml")->setBody($xml);
      break;
    default:
      // should generate error message here- invalid config id 
    }
  }
  
}
