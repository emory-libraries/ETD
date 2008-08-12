<?php

require_once("models/etd.php");
require_once("models/etd_feed.php");
require_once("models/programs.php");

class FeedsController extends Etd_Controller_Action {
  protected $requires_fedora = true;

  /**
   * @var Etd_Feed object
   */
  public $feed;
  
  public function postDispatch() {
    $this->_helper->displayXml($this->feed->saveXml());
  }

  /* recently published ETDs */
  public function recentAction() {
    $opts = $this->getFilterOptions();

    $options = array("max" => 10, "AND" => $opts, "return_type" => "solrEtd");
    /* NOTE: using solrEtd object here because it is significantly
       faster to load (does not pull from Fedora) and has sufficient
       information to generate feed entry.  */

    $etdset = new EtdSet();
    $etdset->findRecentlyPublished($options);

    $feed_title = "Emory ETDs: Recently published";
    if (isset($opts["program"])) $feed_title .= " : " . ucfirst($opts["program"]);
    

    // pass in title for the feed, absolute url, and array of etds to be included      
    $this->feed = new Etd_Feed($feed_title,
			       $this->_helper->absoluteUrl("recent", "feeds", null, $opts),
			       $etdset->etds);
  }

}