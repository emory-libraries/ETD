<?php

class IndexController extends Etd_Controller_Action {

  
  public function indexAction() {	
    $this->view->assign("title", "Welcome");

    // rss feed of recently published ETD records - for display on sidebar
    $this->view->feed = new Zend_Feed_Rss($this->_helper->absoluteUrl('recent', 'feeds'));
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