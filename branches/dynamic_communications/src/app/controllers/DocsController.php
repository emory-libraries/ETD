<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

class DocsController extends Etd_Controller_Action {

  protected $doc_feed_data = "";  // contain the rss feed data for the static docs
  
  public function init() {
    parent::init();

    // all pages in this section should have the print view link
    $this->view->printable = true;    
  }

  public function preDispatch() {
    // contact information used in multiple documents
    $config = Zend_Registry::get('config');
    $this->view->contact = $config->contact;
  }


  public function indexAction() {
    $this->view->title = "ETD Documents";
    // this is the only page that doesn't make sense to be printable
    $this->view->printable = false;
  }
  
   /**
   * extracts the subject content out of the rss feed for documents.
   * @return extracted data from the rss document feed for the subject. 
   */  
  public function topicAction() 
  {
    $request = $this->getRequest();
    $subject = $request->getParam("subject", null);  

    // information for docs section
    $config = Zend_Registry::get('config');
    $rss_data =  "";       
    
    // ETD docs - rss feed from drupal site
    if (! isset($config->docs_feed->url)) {
      throw new Exception("Docs feed is not configured");
    }

    try {
      // set the cache for this rss feed
      $cache = $this->createCache($config->docs_feed->lifetime);
      Zend_Feed_Reader::setCache($cache);
      // read the rss feed
      $docs_feed = $config->docs_feed->url;
      $rss_data = new Zend_Feed_Rss($docs_feed);
    } catch (Exception $e) {
      throw new Exception("Could not parse ETD docs feed '$docs_feed' - " . $e->getMessage());
    }
    
    $this->view->topic = $this->getTopicSubject($subject, $rss_data, $config->docs_feed->url);
  }  
        
  /**
   * get topic subject will extract the subject portion from the feed for display
   * @param $subject - portion of the rss feed to be extracted.
   * @return rss feed content for this subject.
   */
  public function getTopicSubject($subject, $rss_data, $docs_feed_url) {
    try {
      $title_subject = "NOT FOUND";
      switch ($subject) {       
          case "about":  $title_subject = "About";  break;
          case "faq":  $title_subject = "Frequently";  break;
          case "instructions":  $title_subject = "Instructions";  break;
          case "ip":  $title_subject = "Intellectual";  break;                    
          case "policies":  $title_subject = "Policies";  break;
          case "boundcopies":  $title_subject = "Bound";  break;
      }
      $docSubject = "<h3>Subject $subject was not found in the rss feed = " . $docs_feed_url . "</h3>";
      
      foreach ($rss_data as $part) {      
        // Check if the title string in the feed contains the topic
        if (!(strpos($part->title(),$title_subject)===false)) {
          $this->view->title = $part->title();
          $docSubject = "<h3>" . $part->title() . "</h3>" . $part->description();
        }
      }
    } catch (Exception $e) {
      throw new Exception("Could not extract topic '$subject' from feed - " . $e->getMessage());
    }
    return $docSubject;
  }
  
    /**
   * get title subject will take the subject and return a word found in the title.
   * @param $subject - the document subject.
   * @return title_subject a word found in the title for the given subject.
   */
  public function getTitleSubject($subject) {
    $title_subject = "NOT FOUND";
    switch ($subject) {       
        case "about":  $title_subject = "About";  break;
        case "faq":  $title_subject = "Frequently";  break;
        case "instructions":  $title_subject = "Instructions";  break;
        case "ip":  $title_subject = "Intellectual";  break;                    
        case "policies":  $title_subject = "Policies";  break;
        case "boundcopies":  $title_subject = "Bound";  break;
    }
    return $title_subject;
  }

  
  public function createCache($lifetime){

    //refresh time of cache
    //make sure value is null if value is not set or empty - null value means forever
    $lifetime =  (empty($lifetime) ? null : $lifetime);
    $frontendOptions = array('lifetime' => $lifetime, 'automatic_serialization' => true);
    $backendOptions = array('cache_dir' => '/tmp/', "file_name_prefix" => "ETD_docs_cache");
    $cache = Zend_Cache::factory('Output', 'File', $frontendOptions, $backendOptions);
    return $cache; 
  }
}
