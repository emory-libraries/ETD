#!/usr/bin/php -q
<?php

/** 
 * script to check ETD objects for datastreams with missing checksums
 *
 * @category ETD
 * @package ETD_Scripts
 */

// set paths, load config files, set up connection objects for fedora, solr, and ESD
require_once("bootstrap.php");
require_once("models/foxml.php");

$getopts = $common_getopts;
// no-act mode irrelevant for this script, since it doesn't change anything
unset($getopts['noact|n']);     
// suppress directory input option (not relevant)
unset($getopts['dir|d=s']);    // FIXME: why is this a common option?
$getopts['ignore-xml-errors|x'] = 'Ignore missing/invalid checksums on XML datastreams';
$opts = new Zend_Console_Getopt($getopts);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname runs through all ETD-related fedora objects and runs the
 compare-datastream-checksum method on each datastream for each object.

 If pids are specified, only the specified objects will be checked, e.g.:
      $scriptname -v info pid1 pid2 pid3

 If --ignore-xml-errors is specified, invalid checksums on XML datastreams
 will be logged as info level instead of error.  These invalid checksums will
 still be reflected in the summary numbers at the end of the script, but they
 will be suppressed from error-level script output.
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}

// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);

// if pids were specified on the command line, check only those objects
$pids = $opts->getRemainingArgs();
// otherwise, check all ETD objects
if (!count($pids)) {
    // find all ETD-related objects by content model
    $pids = all_etd_pids();
}

// totals of missing & invalid checksums by datastream id
$missing_count = array();
$invalid_count = array();
$valid_count = array();

foreach ($pids as $pid) {
    $logger->info("Processing " . $pid);

    try {
        $dslist = $fedora->listDatastreams($pid);
        foreach ($dslist as $datastream) {
            // initialize datastream counts the first time we encounter this type of datastream
            if (! isset($missing_count[$datastream->ID])) {
                $missing_count[$datastream->ID] = 0;
            }
            if (! isset($invalid_count[$datastream->ID])) {
                $invalid_count[$datastream->ID] = 0;
            }
            if (! isset($valid_count[$datastream->ID])) {
                $valid_count[$datastream->ID] = 0;
            }            
            $checksum = $fedora->compareDatastreamChecksum($pid, $datastream->ID);
            $logger->debug($pid . "/" . $datastream->ID . " compare datastream checksum result is $checksum");
            if ($checksum == "none") {
                $logger->info($pid . "/" . $datastream->ID . " is missing checksum (checksum = $checksum)");
                $missing_count[$datastream->ID]++;
            } elseif ($checksum == 'Checksum validation error') {
                if ($datastream->MIMEType == 'text/xml' ||
                    $datastream->MIMEType == 'application/rdf+xml') {
                    $logger->info($pid . "/" . $datastream->ID . " has an invalid checksum");
                } else {
                    $logger->err($pid . "/" . $datastream->ID . " has an invalid checksum");
                }
                $invalid_count[$datastream->ID]++;
            }
            else {
              $valid_count[$datastream->ID]++;              
            }            
        }
    } catch (FedoraAccessDenied $e) {
        $logger->err('Access denied: ' . $e->getMessage());
    } catch (FedoraObjectNotFound $e) {
        $logger->err('Object not found: ' . $e->getMessage());
    } catch (Exception $e) {    // any other fedora error
        $logger->err($e->getMessage());
    }
}

// summarize counts
$missing_summary = "\n\nTotals for datastreams with missing checksums";
foreach ($missing_count as $ds => $count) {
    $missing_summary .= "\n$ds : $count";
}
$logger->notice($missing_summary . "\n");

$invalid_summary = "\n\nTotals for datastreams with invalid checksums";
foreach ($invalid_count as $ds => $count) {
    $invalid_summary .= "\n$ds : $count";
}
$logger->warn($invalid_summary. "\n");

$valid_summary = "\n\nTotals for datastreams with valid checksums";
foreach ($valid_count as $ds => $count) {
    $valid_summary .= "\n$ds : $count";
}
$logger->warn($valid_summary. "\n");

?>
