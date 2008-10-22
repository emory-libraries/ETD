#!/usr/bin/php -q
<?php

// set paths, load config files, set up connection objects for fedora, solr, and ESD
require_once("bootstrap.php");

$opts = new Zend_Console_Getopt(
  array(
    'verbose|v=s'      => 'Output level/verbosity; one of error, warn, notice, info, debug (default: error)',
    'noact|n'	       => "Test/simulate - don't actually do anything (no actions)",
    )
  );

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname sends out emails for ETDs with expiring embargoes
   ETDs with embargoes expiring in 60 days (or less and notice not sent): send 60-day notice
   ETDs expiring in 7 days: send summary email to ETD-ADMIN
   ETDs expiring today: send expiration notice

";


try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}

// output logging
$writer = new Zend_Log_Writer_Stream("php://output");
// minimal output format - don't display timestamp or numeric priority
$format = '%priorityName%: %message%' . PHP_EOL;
$formatter = new Zend_Log_Formatter_Simple($format);
$writer->setFormatter($formatter);
$logger = new Zend_Log($writer);

// set level of output to be displayed based on command line parameter
switch ($opts->verbose) {
 case "warn":    $verbosity = Zend_Log::WARN; break;
 case "notice":  $verbosity = Zend_Log::NOTICE; break;
 case "info":    $verbosity = Zend_Log::INFO; break;
 case "debug":   $verbosity = Zend_Log::DEBUG; break;   
 case "error": 
 default:
   $verbosity = Zend_Log::ERR; break;
 }
$filter = new Zend_Log_Filter_Priority($verbosity);
$logger->addFilter($filter);


// find ETDs with embargoes that will expire in 60 days
$expiration = date("Ymd", strtotime("+60 days"));	// 60 days from now
$logger->debug("Searching for records with embargo expiration of $expiration or before and notice not sent");

$options = array("start" => 0, "max" => 100);
$etdSet = new EtdSet();
$etdSet->findExpiringEmbargoes($expiration, $options);

for ($count = 0; $etdSet->hasResults(); $etdSet->next()) {
  $plural = ($etdSet->numFound == "1") ? "" : "s";
  $logger->info("Processing record{$plural} " . $etdSet->currentRange() . " of " . $etdSet->numFound);

  foreach ($etdSet->etds as $etd) {
    $logger->debug("Processing " . $etd->pid . " (embargo expires " . $etd->mods->embargo_end .
		   " - duration of " . $etd->mods->embargo . ")");

    /** sanity checks - skip any records returned from Solr that shouldn't be here **/

    // double-check that embargo is actually 60-days or less away from expiration date
    if (strtotime($etd->mods->embargo_end) > strtotime($expiration)) {
      $logger->warn("ETD embargo for " . $etd->pid . " ends on " . $etd->mods->embargo_end .
		   "; does not expire in 60 days, skipping");
      continue;
    // embargo end date should not be blank
    } elseif ($etd->mods->embargo_end == "") {
      $logger->warn("ETD embargo end date for " . $etd->pid . " is not set; skipping");
      continue;
    // should not find records with no embargo
    } elseif ($etd->mods->embargo == "0 days") {
      $logger->warn("ETD record " . $etd->pid . " has no embargo (duration is 0 days); skipping");
      continue;
    }
    
    // double-check that embargo notice has not been sent according to MODS record
 // (this should only happen if Solr index does not get updated -- should be fixed by now?)
    if (isset($etd->mods->embargo_notice) && strstr($etd->mods->embargo_notice, "sent")) {
      $logger->warn("Notice has already been sent for " . $etd->pid . "; skipping");
      continue;
    }

    if (!$opts->noact) {
      $notify = new etd_notifier($etd);
      // send email about embargo expiration
      try {
	$notify->embargo_expiration();
      } catch (Zend_Db_Adapter_Exception $e) {
	 // if ESD is not accessible, cannot look up faculty email addresses - notification will fail
	$logger->crit("Error accessing ESD (needed for faculty email addresses); cannot proceed");

	// if ESD is down, it will fail for *ALL* records being processed
	// -- exit now, don't mark any records as embargoed, etc.
	return;
      }
      
      // add an administrative note that embargo expiration notice has been sent,
      // and add notification event to record history log
      $etd->embargo_expiration_notice();
      
      $result = $etd->save("sent embargo expiration 60-day notice");
      if ($result) {
	$logger->debug("Successfully saved " . $etd->pid . " at $result");
      } else {
	$logger->err("Could not save embargo expiration notice sent to record history"); 
      }
    }

    $count++;
  }	// end looping through current set of etds
}

$logger->info("Sent " . $count . " 60-day notification" . (($count != 1) ? "s" : ""));

/** send 7-day warning to etd-admin **/

// find ETDs with embargoes that will expire in 7 days
$expiration = date("Ymd", strtotime("+7 days"));
$logger->debug("Searching for records with embargo expiration of $expiration");
$options = array("start" => 0, "max" => 100, "return_type" => "solrEtd");
$etdSet->findExpiringEmbargoes($expiration, $options,
			       array("notice_unsent" => false, // don't filter on embargo notice unsent
				     "exact_date" => true)	// exact date instead of a date range
			       );	
$logger->debug("Found " . $etdSet->numFound . " record(s) expiring in 7 days");
// only send the email if records are found
if ($etdSet->numFound && !$opts->noact) {
  $notify = new etdSet_notifier($etdSet);
  $notify->embargoes_expiring_oneweek();
}

/**  send day-of notice to author & committee */

// find ETDs with embargoes that will expire TODAY
$expiration = date("Ymd");
$logger->debug("Searching for records with embargo expiring today ($expiration)");
$options = array("start" => 0, "max" => 100);
$etdSet->findExpiringEmbargoes($expiration, $options,
			       array("notice_unsent" => false, // don't filter on embargo notice unsent
				     "exact_date" => true)	// exact date instead of a date range
			       );

// loop through ETDs in batches of 100
for ($count = 0; $etdSet->hasResults(); $etdSet->next()) {
  $plural = ($etdSet->numFound == "1") ? "" : "s";
  $logger->info("Processing record{$plural} " . $etdSet->currentRange() . " of " . $etdSet->numFound);

  foreach ($etdSet->etds as $etd) {
    $logger->debug("Processing " . $etd->pid . " (embargo expires " . $etd->mods->embargo_end .
		   " - duration of " . $etd->mods->embargo . ")");

    // FIXME: do we need any sanity checks here? (see 60-day notice logic above)

    if (!$opts->noact) {
      $notify = new etd_notifier($etd);
      // send email about embargo ending
      try {
	$notify->embargo_end();
      } catch (Zend_Db_Adapter_Exception $e) {
	 // if ESD is not accessible, cannot look up faculty email addresses - notification will fail
	$logger->crit("Error accessing ESD (needed for faculty email addresses); cannot proceed");

	// if ESD is down, it will fail for *ALL* records being processed
	// -- exit now, don't mark any records as embargoed, etc.
	return;
      }

      // add 0-day notification event to record history log
      $etd->premis->addEvent("notice",
			     "Embargo Expiration Notification sent by ETD system",
			     "success",  array("software", "etd system"));
      $result = $etd->save("sent embargo expiration 0-day notice");
      if ($result) {
	$logger->debug("Successfully saved " . $etd->pid . " at $result");
      } else {
	$logger->err("Could not save embargo expiration 0-day notice sent to record history"); 
      }
    }

      // FIXME: need to add 0-day notice to the history log 
    $count++;
  }	// end looping through current set of etds
}


$logger->info("Sent " . $count . " 0-day notification" . (($count != 1) ? "s" : ""));



?>
