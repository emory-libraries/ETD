#!/usr/bin/php -q
<?php
/**
  * Update all ETDs with collection memberships
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

$etd_pids = $fedora->risearch->findByCModel($config->contentModels->etd);
$logger->notice("Found " . count($etd_pids) . " ETD records");

foreach ($etd_pids as $pid) {
     $logger->info("Processing " . $pid);

    try {
        $etd = new etd($pid);

        // honors ETDs formerly using isMemberOf; convert to isMemberOfCollection
        if (isset($etd->rels_ext->memberOf) &&
            ($etd->rels_ext->memberOf == $config->collections->college_honors)) {
            $logger->info($pid . " - updating isMemberOf honors collection to isMemberOfCollection");
            // remove old isMemberOf relation, replace with new isMemberOfCollection
            $etd->rels_ext->removeRelation("rel:isMemberOf", $config->collections->college_honors);
            $etd->rels_ext->addRelationToResource("rel:isMemberOfCollection", $config->collections->college_honors);
        }

        // if not already a member of master etd collection, add relation
        if (! $etd->rels_ext->isMemberOfCollections->includes($etd->rels_ext->pidToResource($config->collections->all_etd))) {
            $etd->rels_ext->addRelationToResource("rel:isMemberOfCollection", $config->collections->all_etd);
        }
        // if not an honors etd, add to grad school collection
        if (! $etd->rels_ext->isMemberOfCollections->includes($etd->rels_ext->pidToResource($config->collections->college_honors)) &&
            ! $etd->rels_ext->isMemberOfCollections->includes($etd->rels_ext->pidToResource($config->collections->grad_school))) {
            $etd->rels_ext->addRelationToResource("rel:isMemberOfCollection", $config->collections->grad_school);
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