<?php

class IndexController extends Etd_Controller_Action {

  
  public function indexAction() {	
    $this->view->assign("title", "Welcome");

    $feed = $this->_getParam("feed", "recent");	// by default, show recently published
    $this->view->feed_type = $feed;
    // FIXME: check that requested feed type is a valid option (?)

    // rss feed of recently published or most-viewed ETD records - for display on sidebar
    try {
      $this->view->feed = new Zend_Feed_Rss($this->_helper->absoluteUrl($feed, 'feeds'));
    } catch (Exception $e) {
      trigger_error("Could not parse Feed '$feed'", E_USER_NOTICE);
    }
  }

}
?>