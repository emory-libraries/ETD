#!/usr/bin/php -q
<?php
    /**
      * Update the MODS records for all ETDs to make more complete, easier to convert to marcxml
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
     $scriptname updates MODS for all ETDs
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

            $etd->mods->typeOfResource = "text";
            $etd->mods->originInfo->issuance = "monographic";
            $etd->mods->setMarcGenre();

            if ($etd->mods->hasChanged()) {
                if ($opts->noact) {     // noact mode: simulate success
                    $updated++;
                    $logger->info("Saving $pid (simulated)");
                    $logger->debug($etd->mods->saveXML());
                } else {
                    $result = $etd->save("Cleaning/extending MODS");
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