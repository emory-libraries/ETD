#!/usr/bin/php -q
<?php
// ./update_checksums.php -v info emory:17r1x
/** 
 * script to check ETD objects for datastreams with missing checksums
 *
 * @category ETD
 * @package ETD_Scripts
 */

// set paths, load config files, set up connection objects for fedora, solr, and ESD
require_once("bootstrap.php");
require_once("models/foxml.php");

$getopts = array_merge($common_getopts, // use default verbose and noact opts from bootstrap
            array('update|u' => 'Update the FILE datastream Checksum' )
          );

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
$updated_count = array();

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
            if (! isset($updated_count[$datastream->ID])) {
                $updated_count[$datastream->ID] = 0;
            }
            if (! isset($valid_count[$datastream->ID])) {
                $valid_count[$datastream->ID] = 0;
            }                         
            $checksum = $fedora->compareDatastreamChecksum($pid, $datastream->ID);
            $logger->debug($pid . "/" . $datastream->ID . " compare datastream checksum result is $checksum");
            if ($checksum == "none") {
                $logger->info($pid . "/" . $datastream->ID . " is missing checksum (checksum = $checksum)");
                if ($opts->update && $datastream->ID == 'FILE' 
                      && updateChecksum($pid, $datastream->ID, $fedora)) {
                  $updated_count[$datastream->ID]++;    // successfully update the pid/ds
                }
                else {                  
                  $missing_count[$datastream->ID]++;    // failed to update the pid/ds
                }
            } elseif ($checksum == 'Checksum validation error') {
                if ($datastream->MIMEType == 'text/xml' ||
                    $datastream->MIMEType == 'application/rdf+xml') {
                    $logger->info($pid . "/" . $datastream->ID . " has an invalid checksum");
                } else {
                    $logger->err($pid . "/" . $datastream->ID . " has an invalid checksum");
                }
                if ($opts->update && $datastream->ID == 'FILE' 
                      && updateChecksum($pid, $datastream->ID, $fedora)) {
                  $updated_count[$datastream->ID]++;    // successfully update the pid/ds
                }
                else {                   
                  $invalid_count[$datastream->ID]++;    // failed to update the pid/ds
                }                
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

$updated_summary = "\n\nTotals for datastreams with updated checksums";
foreach ($updated_count as $ds => $count) {
    $updated_summary .= "\n$ds : $count";
}
$logger->warn($updated_summary. "\n");

  /**
  * @return value of specified parameter indexes.
  * @param pid value of pid to update checksum.
  * @param ds value of datastream to update checksum.  
  * @desc Update the datastream checksum for given pid.
  */  
  function updateChecksum($pid, $ds, $fedora) {
    $checksum = $fedora->compareDatastreamChecksum($pid, $ds);    
    $object = new etd_file($pid);
    
    // make sure checksum_type is set to MD5
    if (!isset($object->file->checksum_type) || $object->file->checksum_type != 'MD5') {
      $object->file->checksum_type = 'MD5';
    }
        
    $object->file->checksum = ''; 
    try {
      $save_result = $object->save("update the FILE datastream for a new checksum.");
      if ($save_result) {
        $checksum = $fedora->compareDatastreamChecksum($pid, $ds);
        if ($checksum == "none" || $checksum == 'Checksum validation error') {
          return(false);    // failed to update the checksum
        }
        else return(true);   // successfully updated the checksum
      }
    }
    catch (Exception $err) {
      print "Exception = [" . $err->getMessage() . "]\n";   
    }
    return(false);
  }

?>
