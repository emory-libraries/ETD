#!/usr/bin/php -q
<?php
/**
 * Update all ETD Fedora objects to make Dublin Core datastream unversioned
 * @category Etd
 * @package Etd_Scripts
 * @subpackage Etd_Migration_Scripts
 */

// set working directory to the main scripts directory so all the paths work the same
chdir("..");

// set paths, load config files, set up connection objects for fedora, solr, and ESD
require_once("bootstrap.php");

$opts = new Zend_Console_Getopt($common_getopts);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname runs through all etd-related fedora objects to make DC datastream unversioned
 and remove old revisions
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}

// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);
// dublin core datastream id
$DC = "DC";

// find all ETD-related objects by content model
$all_pids = all_etd_pids();
$success = $err = 0;

foreach ($all_pids as $pid) {
    $logger->info("Processing " . $pid);    
    $error = false;
    try {
        // set DC datastream unversioned
        if ($opts->noact) {
            $logger->debug("Set $DC datastream to unversioned (simulated)");
        } else {
            $timestamp = $fedora->setDatastreamVersionable($pid, $DC, false, "setting DC to unversioned");
            if (!$timestamp) {
                $logger->err("Could not set $pid DC datastream to unversioned");
                $error = true;
            } else {
                $logger->debug("Set $DC datastream to unversioned at $timestamp");
            }
        }

        // if there is more than one datastream version, purge the older revisions
        $history = $fedora->getDatastreamHistory($pid, $DC);
        $version_count = count($history->datastream);
        if ($version_count > 1)  {
            $logger->debug("$DC has " . $version_count . " datastream versions");
            // history has an array of datastreams which each have a createDate
            // datastream versions are listed with the most recent first
            $start = $history->datastream[$version_count - 1]->createDate; // start date-time to purge - oldest version
            $end = $history->datastream[1]->createDate;     // end date-time - version before most recent
            if ($opts->noact) {
                $logger->debug("Purging $DC datastream versions from $start to $end (simulated)");
            } else {
                $purged = $fedora->purgeDatastream($pid, $DC, $start, $end,
                            "remove old revisions of $DC datastream");
                // if multiple versions were removed, result is an array of timestamps for each purged datastream version
                if(is_array($purged)) $result_time = implode(', ', $purged);
                else $result_time = $purged;
                if (!$purged) {
                    $logger->err("Problem purging $pid $DC datastream versions from $start to $end");
                    $error = true;
                } else if (count($purged) != ($version_count - 1)) {
                    $logger->warn("Only purged " . count($purged) . " datastream versions from $pid; should have been " . ($version_count -1));
                } else {
                    $logger->debug("Purged $DC datastream versions from $start to $end: $result_time");
                }
            }
        } else {
            $logger->debug("$pid only has one $DC datastream version; no older revisions to be removed");
        }
        if ($error) $err++;
        else $success++;
    } catch (Exception $e) {
        $logger->err($e->getMessage());
        $err++;
    }
}

$logger->notice("$success objects updated");
if ($err) $logger->notice("$err errors");

?>
