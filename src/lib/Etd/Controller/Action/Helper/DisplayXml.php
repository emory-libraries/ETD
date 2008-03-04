<?php

class Etd_Controller_Action_Helper_DisplayXml extends Zend_Controller_Action_Helper_Abstract {


  public function direct($xml) {
    return $this->displayXml($xml);
  }

  public function displayXml($xml) {
    // disable layouts and view script rendering in order to set content-type header as xml
    $this->_actionController->getHelper('layoutManager')->disableLayouts();
    $this->_actionController->getHelper('viewRenderer')->setNoRender(true);
    $this->getResponse()->setHeader('Content-Type', "text/xml")->setBody($xml);
  }

  
}