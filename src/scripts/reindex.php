#!/usr/bin/php -q
<?php

// set paths, load config files, set up connection objects for fedora, solr, and ESD
require_once("bootstrap.php");
require('fedora/api/FedoraGsearchClient.php');

$getopts = $common_getopts;
// no-act mode irrelevant for this script, since it doesn't change anything
unset($getopts['noact|n']);
$opts = new Zend_Console_Getopt($getopts);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname runs through all GHC-related fedora objects and requests
 Fedora Gsearch to re-index them.

 If pids are specified, only the specified objects will be reindexed, e.g.:
      $scriptname -v info pid1 pid2 pid3
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
// otherwise, check all ETDs
if (!count($pids)) {
    $fedora = Zend_Registry::get('fedora-config');
    $config = Zend_Registry::get('config');
    // find all ETDs by content model
    // NOTE: currently only ETDs are indexed in gsearch - no EtdFile or AuthorInfo objects
    $pids = $fedora->risearch->findByCModel($config->contentModels->etd);
}

$pidcount = count($pids);
$logger->notice("Reindexing $pidcount object(s)");

$fedora_cfg = Zend_Registry::get('fedora-config');
$gsearch_url = $fedora_cfg->protocol . '://' .  $fedora_cfg->server . ':'
                    . $fedora_cfg->port . '/fedoragsearch';
// init gsearch client
$gsearch= new FedoraGsearchClient($gsearch_url,
                $fedora_cfg->gsearch->index, $fedora_cfg->gsearch->repository);

$indexed = 0;
foreach ($pids as $pid) {
    $logger->info("Reindexing $pid");
    try {
        if ($gsearch->index_object($pid)) $indexed++;
    } catch (Exception $e) {
        $logger->err("Error indexing $pid : " . $e->getMessage());
    }
}
$logger->notice("Reindexed $indexed object(s)");
// warn if the number indexed/updated is different than the number requested
if ($indexed != $pidcount) {
    $logger->warn("Attempted to index $pidcount object(s), but only inserted or updated $indexed");
}

?>
