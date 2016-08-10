<?php

/**
 * Helper for creating Absolute URLs within the current site

 * @category EmoryZF
 * @package Emory_View
 * @subpackage Emory_View_Helpers
 */
class Zend_View_Helper_AbsoluteUrl {
	
	public $view;
	
    /**
     * Perform helper when called as $this->absoluteUrl() from a view script
     *
     * all parameters are passed to view url helper
     */
	public function absoluteUrl(array $options=array(), $route = '', $reset = false) {
      
	  $front = Zend_Controller_Front::getInstance();
	  $request = $front->getRequest();
      
      // protocol: http or https?
      $url  = ($request->getServer("HTTPS") == "") ? "http://" : "https://";
      $url .=  $request->getServer("SERVER_NAME");	// server name where this script is running
      // pass all the parameters to the url view helper to build the main part of the url
      $url .= $this->view->url($options, $route, $reset);
      return $url;
    }
	
	/**
	* Set the view object (so url helper can be used)
	*
	* @param Zend_View_Interface $view
	* @return void
	*/
  public function setView(Zend_View_Interface $view) {
    $this->view = $view;
  }


}
