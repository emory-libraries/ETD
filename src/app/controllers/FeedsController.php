<?php

require_once("models/etd.php");
require_once("models/etd_feed.php");
require_once("models/programs.php");

class FeedsController extends Etd_Controller_Action {
  protected $requires_fedora = true;

  protected $feed;
  
  public function postDispatch() {
    $this->_helper->displayXml($this->feed->saveXml());
  }

  /* recently published ETDs */
  public function recentAction() {
    $etds = etd::findRecentlyPublished();

    // pass in title for the feed, absolute url, and array of etds to be included      
    $this->feed = new Etd_Feed("Emory ETDs: Recently published",
			       $this->_helper->absoluteUrl('recent'),	       
			       $etds);
  }

}