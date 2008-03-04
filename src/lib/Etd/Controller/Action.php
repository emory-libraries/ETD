<?php

abstract class Etd_Controller_Action extends Zend_Controller_Action {

  public function init() {
    $this->initView();

    Zend_Controller_Action_HelperBroker::addPath('Emory/Controller/Action/Helper',
						 'Emory_Controller_Action_Helper');
    Zend_Controller_Action_HelperBroker::addPath('Etd/Controller/Action/Helper',
						 'Etd_Controller_Action_Helper');
    
    
    $this->acl = $this->view->acl;	// FIXME: which is better? Zend_Registry::get("acl");
    $this->user = $this->view->current_user;

    // store controller/action  name in view (needed for certain pages)
    $params =  $this->_getAllParams();
    $this->view->controller = $params['controller'];
    $this->view->action = $params['action'];
  }

  public function postDispatch() {
    $this->view->messages = $this->_helper->flashMessenger->getCurrentMessages();
  }
  
}

?>
