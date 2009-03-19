<?php

class IndexController extends Etd_Controller_Action {

  
  public function indexAction() {	
    $this->view->title = "Welcome";

    // contact information for news section
    $config = Zend_Registry::get('config');
    $this->view->contact = $config->contact;

    
    $feed = $this->_getParam("feed", "recent");	// by default, show recently published
    $this->view->feed_type = $feed;
    // FIXME: check that requested feed type is a valid option (?)

    // rss feed of recently published or most-viewed ETD records - for display on sidebar
    try {
      $this->view->feed = new Zend_Feed_Rss($this->_helper->absoluteUrl($feed, 'feeds'));
    } catch (Exception $e) {
      $message = "Could not parse RSS feed '$feed' - " . $e->getMessage();
       trigger_error($message, E_USER_NOTICE);
      $this->logger->err($message);
    }
  }

}
?>