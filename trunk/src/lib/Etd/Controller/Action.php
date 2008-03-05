<?php
require_once("xml_acl.php");

abstract class Etd_Controller_Action extends Zend_Controller_Action {

  protected $debug;
  
  public function init() {
    $this->initView();

    Zend_Controller_Action_HelperBroker::addPath('Emory/Controller/Action/Helper',
						 'Emory_Controller_Action_Helper');
    Zend_Controller_Action_HelperBroker::addPath('Etd/Controller/Action/Helper',
						 'Etd_Controller_Action_Helper');
    

    $this->debug = Zend_Registry::get('debug');

    //        $this->acl = $this->view->acl;	// FIXME: which is better? Zend_Registry::get("acl");
    $this->acl = Zend_Registry::get('acl');
    if (Zend_Registry::isRegistered('current_user'))
      $this->current_user = Zend_Registry::get('current_user');
    else $this->current_user = null;	// no user currently logged in (guest?)
    // both acl and current user are also needed in view
    $this->view->acl = $this->acl;
    $this->view->current_user = $this->current_user;

    // store controller/action  name in view (needed for certain pages)
    $params =  $this->_getAllParams();
    // (not set when testing)
    if (isset($params['controller'])) $this->view->controller = $params['controller'];
    if (isset($params['action']))  $this->view->action = $params['action'];
  }

  public function postDispatch() {
    $this->view->messages = $this->_helper->flashMessenger->getMessages();
  }
  
}

?>
