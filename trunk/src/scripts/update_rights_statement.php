#!/usr/bin/php -q
<?php

  /**
   Update all ETD records with new useAndReproduction statement.
  */

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

// get rights statement from config file
$config = Zend_Registry::get("config");
$rights_stmt = $config->useAndReproduction;

// iterate through all ETD objects, 100 at a time
$options = array("start" => 0, "max" => 100);
$etdSet = new EtdSet();
$etdSet->find($options);


// count records processed
$updated = 0;
$unchanged = 0;
$blank = 0;
$error = 0;

while ($etdSet->hasResults()) {
  $plural = ($etdSet->numFound == "1") ? "" : "s";
  $logger->info("Processing record{$plural} " . $etdSet->currentRange() . " of " . $etdSet->numFound);

  foreach ($etdSet->etds as $etd) {
    $logger->debug("Processing " . $etd->pid);
    /*  Rights statement could be blank if record is a draft and
        student has not yet selected that they accept the submission
        agreement.  If blank, it should be left that way; otherwise it
        should be updated. */
    if ($etd->mods->rights == "" && $etd->status() == "draft") {
      $blank++;
      $logger->debug($etd->pid . " rights is blank and record is a draft; not updating");
    } else {
      $etd->mods->rights = $rights_stmt;
      if ($etd->mods->hasChanged()) {
	if (!$opts->noact) {
	  $result = $etd->save("updated MODS rights statement");
	  if ($result) {
	    $updated++;
	    $logger->debug("Successfully updated " . $etd->pid . " at $result");
	  } else {
	    $error++;
	    $logger->err("Could not update " . $etd->pid); 
	  }
	}
      } else {	// mods not changed
	$unchanged++;
      }
    }
  } // end looping through current set of etds
  
  $etdSet->next();	// retrieve the next batch
}

// summary of what was done
if ($updated)   $logger->info("Updated $updated records");
if ($blank)     $logger->info("$blank records left as is with no rights statement (draft status)");
if ($unchanged) $logger->info("$unchanged records unchanged");
if ($error)     $logger->info("Error updating $error records");