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

    //News Section
    try {
      $this->view->news = $this->getNews($config);
    } catch (Exception $e) {
      $message = "Error retrieving news: " . $e->getMessage();
      trigger_error($message, E_USER_WARNING);
      $this->logger->err($message);
    }
    
    //This section is displayed in the sidebar
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
    if (! isset($config->news_feed->url)) {
      throw new Exception("News feed is not configured");
    }

    try {
      $news_feed = $config->news_feed->url;

      //Set Feed_Reeder to use cache
      $cache = $this->createCache($config->news_feed->lifetime);
      Zend_Feed_Reader::setCache($cache);
      //Zend_Feed_Reader::useHttpConditionalGet(); //may use later for conditional get

      //Read the feed
      $news = Zend_Feed_Reader::import($news_feed);
    } catch (Exception $e) {
      throw new Exception("Could not parse ETD news feed '$news_feed' - " . $e->getMessage());
    }

    return $news;
  }

  /**
   * creates cache for RSS feeds
   * @return Zend_Cache
   */
  public function createCache($lifetime){

        //refresh time of cache
        //make sure value is null if value is not set or empty - null value means forever
        $lifetime =  (empty($lifetime) ? null : $lifetime);

        $frontendOptions = array('lifetime' => $lifetime, 'automatic_serialization' => true);
        $backendOptions = array('cache_dir' => '/tmp/', "file_name_prefix" => "ETD_news_cache");
        $cache = Zend_Cache::factory('Output', 'File', $frontendOptions, $backendOptions);
        return $cache;
  }


}
?>