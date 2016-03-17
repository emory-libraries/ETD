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

      //Create cache for calendar
      $cache = $this->createCache($config->calendar_feed->lifetime, "ETD_calendar_cache");
      Zend_Feed_Reader::setCache($cache);


      //Read the feed
      $calendar = Zend_Feed_Reader::import($calendar_feed);
    } catch (Exception $e) {
      trigger_error("Could not parse ETD calendar feed '$calendar_feed' - " . $e->getMessage(), E_USER_NOTICE);
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
      $cache = $this->createCache($config->news_feed->lifetime, "ETD_news_cache");
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
        $xml = simplexml_load_string($entry->saveXml()); // can only be used for elements without namespaces
        $namespaces = $xml->getNameSpaces(true);
        $xml_gd = $xml->children($namespaces['gd']); // elements that use the gd ns

        $title = (string)$xml->title;  //have to cast this a a string because it is used as an array key
        $start =  $xml_gd->when->attributes()->startTime;
        $end =  $xml_gd->when->attributes()->endTime;
        $where = $xml_gd->where->attributes()->valueString;
        $description = $xml->content;

        //Format start and end times
        //Events with a start and end time will be formated:
        // Jan 15 2011 3:15pm - 4:00pm
        //Events with only a start time (All day event in google) most likely used for a submition deadline
        // are formatted: Jan 15 2011
        //End time is alway formatted: 10:15pm or blank
        if(strlen($start) == 10){ //This means there is not tinme and thus an all-day event
            $start = date("D M j Y", strtotime($start));
            $end = "";
        }
        else{
            $start = date("D M j Y g:ia", strtotime($start));
            $end = date("g:ia", strtotime($end));
        }

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
  public function createCache($lifetime, $prefix){

        //refresh time of cache
        //make sure value is null if value is not set or empty - null value means forever
        $lifetime =  (empty($lifetime) ? null : $lifetime);

        $frontendOptions = array('lifetime' => $lifetime, 'automatic_serialization' => true);
        $backendOptions = array('cache_dir' => '/tmp/', "file_name_prefix" => $prefix);
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
