#!/usr/bin/php -q
<?php
/* 
 * check ETD objects for datastreams with missing checksums
 */

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

$missing_count = array();

foreach ($all_pids as $pid) {
    $logger->info("Processing " . $pid);

    try {
        $dslist = $fedora->listDatastreams($pid);
        foreach ($dslist as $datastream) {
            if (! isset($missing_count[$datastream->ID])) {
                $missing_count[$datastream->ID] = 0;    // mimetype?
            }
            $checksum = $fedora->compareDatastreamChecksum($pid, $datastream->ID);
            if ($checksum == "none") {
                $logger->info($pid . "/" . $datastream->ID . " is missing checksum");
                $missing_count[$datastream->ID]++;
            }
        }
    } catch (Exception $e) {    // object not found in fedora, etc.
        $logger->err($e->getMessage());
    }
}

// summarize counts
$logger->notice("Totals for datastreams with missing checksums");
foreach ($missing_count as $ds => $count) {
    $logger->notice("$ds : $count");
}

?>
