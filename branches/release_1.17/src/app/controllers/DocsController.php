<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

class DocsController extends Etd_Controller_Action {
 
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

  /**
   * Magic/Missing method to catch all the individual topics so that
   * they can be routed to the topicAction with the subject as a param.
   * @param $name - name of the missing method.
   * @param $arguments - any arguments.
   */
  public function __call($name, $arguments) {
    // Remove the Action from the name to pass as a subject to the topicAction
    $len = strlen($name) - strlen("Action");    
    if ($len > 0 && (substr($name, $len) == "Action")) {
      $this->topicAction(substr($name, 0, $len));
    }
    else {
      echo "Could not find " . substr($name, 0, $len) . "<br>";
    }    
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
  public function topicAction($subject)
  {
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
      $rss_data = Zend_Feed_Reader::import($docs_feed);
    } catch (Exception $e) {
      throw new Exception("Could not parse ETD docs feed '$docs_feed' - " . $e->getMessage());
    }
   
    // set the view to the subject extracted from the rss feed.
    $this->view->topic = $this->getTopicSubject($subject, $rss_data);
    $this->render('topic'); // send all the subjects to render on this one view page.
  }  
       
  /**
   * get topic subject will extract the subject portion from the feed for display
   * @param $subject - portion of the rss feed to be extracted.
   * @return the XML extracted data for this subject.
   */
  public function getTopicSubject($subject, $rss_data) {
    $docSubject = "";
    try {
      // Store the XML extracted data for this subject.
      foreach ($rss_data as $part) {  
        // Check if the title string in the feed contains the topic
        if ($this->foundSubjectInFeed($subject,$part->getLink())) {
          $this->view->title = $part->getTitle();
          $docSubject = $part->getDescription();
        }
      }
    } catch (Exception $e) {
      throw new Exception("Could not extract topic '$subject' from feed - " . $e->getMessage());
    }
   
    if (! isset($docSubject)) {
      $message = "Error: Document not found";
      $this->_helper->flashMessenger->addMessage($message);
      $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "notfound"), "", true);      
    }    
    // Return the XML extracted data for this subject.
    return $docSubject;
  }
 
  /**
   * get the text in the title that will identify this subject.
   * @param $subject - the document subject.
   * @return title_subject a word found in the title for the given subject.
   */
  public function foundSubjectInFeed($subject, $url) {
    // compare the subject with the last part of the url    
    $url_subject = substr($url, (strlen($url) - strlen($subject)));
    if ($subject == $url_subject) return true;
    else return false;
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
