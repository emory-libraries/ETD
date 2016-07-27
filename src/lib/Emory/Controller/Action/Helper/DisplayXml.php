<?php
/**
 * controller helper for outputting xml
 * - disables layout and view
 * - sets content type
 * 
 * @category EmoryZF
 * @package Emory_Controller
 * @package Emory_Controller_Helpers
 */
class Emory_Controller_Action_Helper_DisplayXml extends Zend_Controller_Action_Helper_Abstract {


  /**
   * controller shortcut to main function
   * @see Emory_Controller_Action_Helper_DisplayXml::displayXml()
   */
  public function direct($xml) {
    return $this->displayXml($xml);
  }

  /**
   * @param string $xml xml content to be displayed, as string
   */
  public function displayXml($xml) {
    // disable layouts and view script rendering in order to set content-type header as xml
    $this->_actionController->getHelper('layout')->disableLayout();
    $this->_actionController->getHelper('viewRenderer')->setNoRender(true);
    $this->getResponse()->setHeader('Content-Type', "text/xml")->setBody($xml);
  }

  
}
