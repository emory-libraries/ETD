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
    $program = $this->_request->getParam("program", null);
    $opts = array();
    if ($program) $opts["program"] = strtolower($program);		// filter by program
    $etds = etd::findRecentlyPublished(10, $opts, 'solrEtd');

    // pass in title for the feed, absolute url, and array of etds to be included      
    $this->feed = new Etd_Feed("Emory ETDs: Recently Published",
			       $this->_helper->absoluteUrl('recent', 'feeds', null, $opts),
			       $etds,
			       "Recently Published records from Emory University's Electronic Thesis and Dissertation Repository");
    // description - add program if there is one
  }

}