#!/usr/bin/php -q
<?php
 /**
   * Update all ETD Fedora objects to make Dublin Core datastream unversioned
   *
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
 $scriptname runs through all etd-related fedora objects to clean RELS-EXT by 
 converting rdf:description to rdf:Description
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}

// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);

// find all ETD-related objects by content model
$all_pids = all_etd_pids();
// count variables
$success = $unchanged = $err = 0;

foreach ($all_pids as $pid) {
    $logger->info("Processing " . $pid);

    try {
        $obj = new foxml($pid);      
        $obj->rels_ext->cleanDescription();
        if ($obj->rels_ext->hasChanged()) {
            if ($opts->noact) {
                $logger->info("Updated RELS-EXT (simulated)");
                $success++;
            } else {
                $timestamp = $obj->save("correct rdf:Description in rels-ext");
                if ($timestamp) {
                    $logger->info("Updated RELS-EXT at $timestamp");
                    $success++;
                } else {
                    $logger->err("Problem saving RELS-EXT for $pid");
                    $err++;
                }
            }
        } else {
            $logger->debug("RELS-EXT is unchanged; not saving");
            $unchanged++;
        }       
    } catch (Exception $e) {    // object not found in fedora, etc.
        $logger->err($e->getMessage());
        $err++;
    }
}

// summary of what was done
$logger->info("$success objects updated");
if ($unchanged) $logger->info("$unchanged objects unchanged");
if ($err) $logger->info("$err errors");

?>