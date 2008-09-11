<?php

class IndexController extends Etd_Controller_Action {

  
  public function indexAction() {	
    $this->view->assign("title", "Welcome");

    // rss feed of recently published ETD records - for display on sidebar
    try {
      $this->view->feed = new Zend_Feed_Rss($this->_helper->absoluteUrl('recent', 'feeds'));
    } catch (Exception $e) {
      trigger_error("Could not parse Feed of recently published records", E_USER_NOTICE);
    }
  }

}
?>