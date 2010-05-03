<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

class IndexController extends Etd_Controller_Action {

  
  public function indexAction() {	
    $this->view->title = "Welcome";

    // contact information for news section
    $config = Zend_Registry::get('config');
    $this->view->contact = $config->contact;

    try {
      $this->view->news = $this->getNews($config);
    } catch (Exception $e) {
      $message = "Error retrieving news: " . $e->getMessage();
      trigger_error($message, E_USER_WARNING);
      $this->logger->err($message);
    }
    
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

  /**
   * get news feed for display on home page
   * @param Zend_Config $config - used for news_feed setting; error if not set
   * @return Zend_Feed_Rss 
   */
  public function getNews(Zend_Config $config) {
    // ETD news - rss feed from drupal site
    if (! isset($config->news_feed)) {
      throw new Exception("News feed is not configured");
    }

    try {
      $news_feed = $config->news_feed;
      $news = new Zend_Feed_Rss($news_feed);
    } catch (Exception $e) {
      throw new Exception("Could not parse ETD news feed '$news_feed' - " . $e->getMessage());
    }

    return $news;
  }

}
?>