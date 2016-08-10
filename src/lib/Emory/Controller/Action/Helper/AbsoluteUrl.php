<?php

/**
 * Helper for creating Absolute URLs within the current site
 * @category EmoryZF
 * @package Emory_Controller
 * @package Emory_Controller_Helpers
 */
class Zend_Controller_Action_Helper_AbsoluteUrl extends Zend_Controller_Action_Helper_Abstract {

    /**
     * Perform helper when called as $this->_helper->absoluteUrl() from an action controller
     *
     * all parameters are passed to main url helper
     */
    public function direct($action, $controller = null, $module = null, array $params = null) {
      $request = $this->getRequest();
      
      // protocol: http or https?
      $url  = ($request->getServer("HTTPS") == "") ? "http://" : "https://";
      $url .=  $request->getServer("SERVER_NAME");	// server name where this script is running
      // pass all the parameters to the Zend url helper to build the main part of the url
      $urlHelper = $this->_actionController->getHelper("url");
      $url .=  $urlHelper->simple($action, $controller, $module, $params);
      
      return $url;
    }

}
