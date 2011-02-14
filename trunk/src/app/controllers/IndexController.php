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

    //Calendar Section
    // ETD calendar - rss feed from goodle calendar
    if (! isset($config->calendar_feed->url)) {
      throw new Exception("Calendar feed is not configured");
    }

    try {
      $calendar_feed = $config->calendar_feed->url;

      //Read the feed
      $calendar = Zend_Feed_Reader::import($calendar_feed);
    } catch (Exception $e) {
      throw new Exception("Could not parse ETD news feed '$calendar_feed' - " . $e->getMessage());
    }

    try {
      $this->view->calendar = $this->getCalendar($calendar);
    } catch (Exception $e) {
      $message = "Error retrieving calendar: " . $e->getMessage();
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

      //Read the feed
      $news = Zend_Feed_Reader::import($news_feed);
    } catch (Exception $e) {
      throw new Exception("Could not parse ETD news feed '$news_feed' - " . $e->getMessage());
    }

    return $news;
  }

  /**
   * Get calendar feed for display on home page
   * Parses each entry and returns array of data
   * Also groups entries with same title
   * @param Zend_Config $config - used for calendar_feed setting; error if not set
   * @return Array
   */
  public function getCalendar($calendar) {
    $entries = array(); //array of reformated calendar entries

    foreach ($calendar as $entry){
        $content = $entry->getContent();
        $title = $entry->getTitle();

        //Get start and end time 
        preg_match( "/When:(.*)/", $content, $matches);
             
        //Set start and end time.  If no end, start will have the whole date
        $start = (isset($matches[1]) ? $matches[1] : "");
        $end = "";
        if (strpos($matches[1], "to")){
            list($start, $end) = split("to", $matches[1]);
        }

        //Get  Location
        preg_match( "/Where:(.*)/", $content, $matches);
        $where = (isset($matches[1]) ? $matches[1] : "");

        //Get  Description
        preg_match( "/Event Description:(.*)/", $content, $matches);
        $description = (isset($matches[1]) ? $matches[1] : "");

        //Events with the same title will be grouped together
        //Each title can have mutiple dates and locations
        $entries[$title]["description"] = trim($description);
        $entries[$title]["whenWhere"][] = array("start" => trim($start), "end" => trim($end), "where" => trim($where));
    }

    //Sort each whenWhere entry by start date
    //Using custom sort function "sortByStartDate"
    // using & to change values in place in the array
    foreach ($entries as &$entry){
        usort($entry["whenWhere"], array(get_class(), "sortByStartDate"));
    }
    unset($entry); //remove reference afer sorting is done

    return $entries;
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

    //function used to sort calendar entries based on start date
  function sortByStartDate($a, $b) {
    //convert dates to time
    $a = strtotime($a["start"]);
    $b = strtotime($b["start"]);


    if ($a == $b) {
        return 0;
    }
    return ($a < $b ? -1 : 1);
}

}
?>
