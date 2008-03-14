<?php
require_once("xml_acl.php");

abstract class Etd_Controller_Action extends Zend_Controller_Action {

  protected $debug;
  protected $env;
  protected $requires_fedora = false;
  
  public function init() {
    $this->initView();

    Zend_Controller_Action_HelperBroker::addPath('Emory/Controller/Action/Helper',
						 'Emory_Controller_Action_Helper');
    Zend_Controller_Action_HelperBroker::addPath('Etd/Controller/Action/Helper',
						 'Etd_Controller_Action_Helper');
    

    
    $this->debug = Zend_Registry::get('debug');
    $this->view->debug = $this->debug;

    //        $this->acl = $this->view->acl;	// FIXME: which is better? Zend_Registry::get("acl");
    $this->acl = Zend_Registry::get('acl');
    if (Zend_Registry::isRegistered('current_user'))
      $this->current_user = Zend_Registry::get('current_user');
    // no user currently logged in -- leave current_user unset
    //        else $this->current_user = "guest";		

    $this->env = Zend_Registry::get('environment');

    
    // these variables are also needed in the view
    $this->view->acl = $this->acl;
    if (isset($this->current_user)) $this->view->current_user = $this->current_user;
    $this->view->env = $this->env;
    $this->view->browserInfo = get_browser();
    $config = Zend_Registry::get('config');
    $this->view->supported_browsers = explode(',', $config->supported_browsers);

    // store controller/action  name in view (needed for certain pages)
    $params =  $this->_getAllParams();
    // (not set when testing)
    if (isset($params['controller'])) $this->view->controller = $params['controller'];
    if (isset($params['action']))  $this->view->action = $params['action'];

    if (isset($params['layout']) && $params['layout'] == "printable")
      Xend_Layout::setDefaultLayoutName('printable');

    /* if this controller requires fedora and it is not configured (unavailable), redirect to an error page */
    if ($this->requires_fedora && !Zend_Registry::isRegistered('fedora'))
      $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error",
      							 "action" => "fedoraUnavailable"), "", true);
  }

  public function postDispatch() {
    $this->view->messages = $this->_helper->flashMessenger->getMessages();
  }
  
}

?>
