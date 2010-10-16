#!/usr/bin/php -q
<?php
/**
 * Update all ETD records with program and subfield ids.
 *
 * @category Etd
 * @package Etd_Scripts
 * @subpackage Etd_Migration_Scripts
 */

// set working directory to the main scripts directory so all the paths work the same
chdir("..");

// set paths, load config files, set up connection objects for fedora, solr, and ESD
require_once("bootstrap.php");
require_once("models/foxmlCollection.php");

$opts = new Zend_Console_Getopt($common_getopts);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname runs through all etds and updates program/subfield ids
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}

// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);

// iterate through all ETD objects, 100 at a time
$options = array("start" => 0, "max" => 100);
$etdSet = new EtdSet();
$etdSet->find($options);


// count records processed
$updated = 0;
$unchanged = 0;
$error = 0;

// program hierarchy
// - initialize separately to avoid getting the wrong ids where label
//   collisions occur between grad and undergrad programs
$programObj = new foxmlCollection();
$programs = $programObj->skos;

for ( ;$etdSet->hasResults(); $etdSet->next()) {
  $plural = ($etdSet->numFound == "1") ? "" : "s";
  $logger->info("Processing record{$plural} " . $etdSet->currentRange()
    . " of " . $etdSet->numFound);

  foreach ($etdSet->etds as $etd) {
    // search within the appropriate section of the programs hierarchy
    if ($etd->isHonors()) {
      $programs_section = $programs->undergrad;
    } else {
      $programs_section = $programs->grad;
    }
    // if record has a department, get id & store in rels_ext
    if ($etd->mods->department != "") { 
      $program_id = $programs_section->findDescendantIdbyLabel($etd->mods->department);
      $logger->debug("Processing " . $etd->pid . "; program id for " . $etd->mods->department .
         " is " . $program_id);
      if ($program_id) $etd->rels_ext->program = $program_id;
      else $logger->warn("Could not find id for '" . $etd->mods->department . "'");
    } elseif ($etd->status() != "draft" && $etd->status() != "inactive") {
      // department should be set in any status beyond draft, so warn if that's not the case
      $logger->warn($etd->pid . " has no program set and status is not draft or inactive");
    }
    
    // same logic for subfield as program, except field is optional
    if (isset($etd->mods->subfield) && $etd->mods->subfield != "") {
      $subfield_id = $programs_section->findDescendantIdbyLabel($etd->mods->subfield);
      $logger->debug("Processing " . $etd->pid . "; subfield id for " . $etd->mods->subfield .
         " is " . $subfield_id);
      if ($subfield_id) $etd->rels_ext->subfield = $subfield_id;
      else $logger->warn("Could not find id for '" . $etd->mods->subfield . "'");
    }
    
    if ($etd->rels_ext->hasChanged()) {
      if (!$opts->noact) {
  $result = $etd->save("updated program/subfield ids");
  if ($result) {
    $updated++;
    $logger->info("Successfully updated " . $etd->pid . " at $result");
  } else {
    $error++;
    $logger->err("Could not update " . $etd->pid); 
  }
      }
    } else {  // record was not modified
      $unchanged++;
    }
  }

}

// summary of what was done
if ($updated)   $logger->info("Updated $updated records");
if ($unchanged) $logger->info("$unchanged records unchanged");
if ($error)     $logger->info("Error updating $error records");

