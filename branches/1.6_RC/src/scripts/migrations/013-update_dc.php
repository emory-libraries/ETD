#!/usr/bin/php -q
<?php
/**
 * Update all published ETDs with OAI ids
 *
 * @category Etd
 * @package Etd_Scripts
 * @subpackage Etd_Migration_Scripts
 */

// set working directory to the main scripts directory so all the paths work the same
chdir("..");
// set paths, load config files, set up connection objects for fedora, solr, and ESD
require_once("bootstrap.php");
require_once("models/foxml.php");
$opts = new Zend_Console_Getopt($common_getopts);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname adds OAI identifier to RELS-EXT for all published ETDs
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}
// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);


// count records processed
$updated = $unchanged = $error = 0;

$etdSet = new EtdSet();
$etdSet->find(array("AND" => array("status" => "published"),
                   "start" => 0, "max" => 50));
		   //do for both published and unpublished 

for ( ;$etdSet->hasResults(); $etdSet->next()) {
  $plural = ($etdSet->numFound == "1") ? "" : "s";
  $logger->notice("Processing record{$plural} " . $etdSet->currentRange()
		. " of " . $etdSet->numFound);

  foreach ($etdSet->etds as $etd) {
    try {
      $etd->updateDC();
      $logger->debug("Updating DC for " . $etd->pid);
    
      if ($etd->dc->hasChanged()) {
	if (!$opts->noact) {
	  $result = $etd->save("Updated DC.");
	  if ($result) {
            $updated++;
            $logger->info("Successfully updated DC for" . $etd->pid . " at $result");
	  } else {
            $error++;
            $logger->err("Could not update DC for" . $etd->pid);
	  }
	} else {      // noact mode - simulate save
	  $logger->info("Saving " .  $etd->pid . " (simulated)");
	  $updated++;
	}
      } else {	// record was not modified
	$logger->debug($etd->pid . " is unchanged; not saving");
	$unchanged++;
      }
    } catch (Exception $e) {
      $logger->err("Problem updating " . $etd->pid . " - " . $e->getMessage());
      $err++;
    }
  } // end looping through current chunk of etds 
} // end looping through all etdset result chunks

// summary of what was done
if ($updated)   $logger->notice("Updated $updated records");
if ($unchanged) $logger->notice("$unchanged records unchanged");
if ($error)     $logger->notice("Error updating $error records");
?>
