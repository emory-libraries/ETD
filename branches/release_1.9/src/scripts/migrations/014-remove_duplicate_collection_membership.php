#!/usr/bin/php -q
<?php
/**
 * Remove duplicated collection memberships
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
 $scriptname adds collection membership to RELS-EXT for all ETDs
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

$etd_pids = $fedora->risearch->findByCModel($schools_config->all_schools->fedora_collection);
$logger->notice("Found " . count($etd_pids) . " ETD records");

foreach ($etd_pids as $pid) {
     $logger->info("Processing " . $pid);

    try {
        $etd = new etd($pid);
        // if a member of master etd collection, remove  relation to ETD-collection
        if ( $etd->rels_ext->isMemberOfCollections->includes($etd->rels_ext->pidToResource($schools_config->all_schools->fedora_collection))) {
	  if ($opts->noact) {     // noact mode: simulate success                                                                              
              $updated++;
	      $logger->info("Removing rel:isMemberOfCollection for 'emory-control:ETD-collection'" . " $pid (simulated)");
	    } else {
	      $updated++;
	      $etd->rels_ext->removeRelation("rel:isMemberOfCollection", $schools_config->all_schools->fedora_collection);
	    }  
	} 

        if ($etd->rels_ext->hasChanged()) {
            if ($opts->noact) {     // noact mode: simulate success
                $updated++;
                $logger->info("Saving $pid (simulated)");                
                $logger->debug($etd->rels_ext->saveXML());
            } else {
                $result = $etd->save("Adding relations to ETD collections");
                if ($result) {
                    $updated++;
                    $logger->info("Successfully updated " . $etd->pid . " at $result");
                } else {
                    $error++;
                    $logger->err("Could not update " . $etd->pid);
                }
            }
        } else {
            $logger->debug("No change; not saving");
            $unchanged++;
        }

    } catch (Exception $e) {    // object not found in fedora, etc.
        $logger->err($e->getMessage());
        $error++;
    }
}

// summary of what was done
$logger->info("$updated records updated");
if ($unchanged) $logger->info("$unchanged records unchanged");
if ($error) $logger->info("$error errors");

?>