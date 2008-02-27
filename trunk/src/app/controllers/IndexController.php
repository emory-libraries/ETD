<?php
/** Zend_Controller_Action */
/* Require models */

class IndexController extends Zend_Controller_Action {

  protected $_flashMessenger = null;

   public function init() {
     $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
     $this->view->messages = $this->_helper->flashMessenger->getMessages();
     $this->initView();
   }
	public function indexAction() {	
		$this->view->assign("title", "Welcome to etd");
	}

	public function listAction() {
	}

	public function createAction() {
	}

	public function editAction() {
	}

	public function saveAction() {
	}

	public function viewAction() {
	}

	public function deleteAction() {
	}
}
?>