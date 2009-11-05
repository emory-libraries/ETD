<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

require_once("models/stats.php");
require_once("models/etd.php");
require_once("CountryNames.php");

class StatisticsController extends Etd_Controller_Action {

  // FIXME: would be nice to cache this count query results and reuse
  // (esp. country - currently called three times for a single page to build the maps)
  // stats aren't updated that frequently, so caching would make sense...

  private $default_max;
  
  public function preDispatch() {
    $this->view->graph_width =  500 ;  
    $this->default_max = 100;	// initial/default maximum
  }
  
  public function indexAction() {
    $this->view->title = "Access Statistics";

    $lastrun = new LastRunObject();
    $this->view->lastrun = $lastrun->findLast();
    
  }

  // NOTE - not currently using this
  public function countryPieAction() {
    $stats = new StatObject();
    $country = $stats->countByCountry();
    $countries = new CountryNames;

    // test - for now, use abstract totals
    $totals = array();
    $labels = array();
    foreach ($country as $c) {
      if ($c['file'] == 0) continue;
      $totals[] = $c['file'];
      //      $labels[] = $countries->$c['country'];
      $labels[] = $countries->$c['country'] . " (" . $c['country'] . ")";
    }
    $this->_helper->pieChart($totals, $labels);
  }


  // use Google charts API to graph country hits onto a simple world map
  public function countryMapAction() {
    $mode = $this->_getParam("show", "abstract");
    $stats = new StatObject();
    $country = $stats->countByCountry();
    $countries = new CountryNames;

    // FIXME: allow to filter by etd pid?  could display map charts on
    // single record stat page (all the other logic could be
    // re-used...)

    
    if ($mode == "abstract") {	// shades of blue used on the site
      $colors='ffffff,e8eeff,bbc7ed,a1b2e7,7d91d2,657dcb,3957b8,243d8e,14214d';
    } elseif ($mode == "file") { // shades of gold used on the site
      $colors='ffffff,ece0c4,edbf52,efb937,f1b116,f1aa00';
    }
    $url = 'http://chart.apis.google.com/chart?chs=440x220&&cht=t&chtm=world&chco=' . $colors;
    
    $country_codes = '';
    $values = array();
    foreach ($country as $c) {
      // if country-code is not in our list, Google probably doesn't have it either - exclude
      if (!$countries->hasCode($c['country'])) continue;
      $country_codes .= $c['country'];
      $values[] = $c[$mode];
    }


    $url .= "&chld=" . $country_codes . "&chd=t:" . implode(',', $values);
   
    // FIXME: would be good to cache this image rather than hitting google every time...
    // (at least for all-site version, maybe not single pid versions...)
    $this->_redirect($url);
  }

  
  
   // stats for all etds, broken down by country
  public function countryAction() {
     $stats = new StatObject();
     $this->view->country = $stats->countByCountry();
     $this->view->countries = new CountryNames;
     $this->view->title = "Access Statistics by Country";

     $max = $this->default_max;
     foreach ($this->view->country as $c) {
       $max = max($max, $c['abstract'], $c['file']);
     }

     $this->view->graph_max = $max;
     
   }
    
   
   // stats for all etds, broken down by month/year
   public function monthlyAction() {
     $stats = new StatObject();
     $this->view->month = $stats->countByMonthYear();
     $this->view->title = "Access Statistics by Month";
     
     $this->view->params =  $this->_getAllParams();

     $max = $this->default_max;
     foreach ($this->view->month as $row) {
       $max = max($max, $row['abstract'], $row['file']);
     }

     $this->view->graph_max = $max;
   }

   // statistics for a single record
   public function recordAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     if ($etd) {
       if (!$this->_helper->access->allowedOnEtd("view statistics", $etd)) return false;
     }

     $stats = new StatObject();
     $pids = array();
     // search 
     $pids[] = $etd->pid;
     foreach ($etd->pdfs as $pdf) $pids[] = $pdf->pid;
     
     $this->view->total = $stats->count($pids);
     $this->view->country = $stats->countByCountry($pids);
     $this->view->countries = new CountryNames;

     $this->view->month = $stats->countByMonthYear($pids);

     $max = $this->default_max;
     foreach ($this->view->month as $row) {
       $max = max($max, $row['abstract'], $row['file']);
     }
     foreach ($this->view->country as $c) {
       $max = max($max, $c['abstract'], $c['file']);
     }
     $this->view->graph_max = $max;
     
     $this->view->etd = $etd;
     $this->view->title = "Access Statistics for " . $etd->label;
     
   }

  
}
