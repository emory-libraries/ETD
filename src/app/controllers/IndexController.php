<?php

class IndexController extends Etd_Controller_Action {

  
  public function indexAction() {	
    $this->view->assign("title", "Welcome");

    $this->view->feed = new Zend_Feed_Atom("http://wilson.library.emory.edu/~rsutton/etd/browse/recentFeed");
    
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