#!/usr/bin/php -q
<?php
/**
 * Update ETDs program listings to add Candler.
 *
 * @category Etd
 * @package Etd_Scripts
 * @subpackage Etd_Migration_Scripts
 */

// set working directory to the main scripts directory so all the paths work the same
chdir("..");
// set paths, load config files, set up connection objects for fedora, solr, and ESD
require_once("bootstrap.php");
require_once("models/programs.php");
$opts = new Zend_Console_Getopt($common_getopts);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname updates program list to add Candler School of Theology with its subdivisions
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}
// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);

// new labels
$new_labels = array("grad" => "Laney Graduate School",
		"undergrad" => "Emory College");

// items to be added
$candler = array("id" => "#candler",
		 "label" => "Candler School of Theology");
$candler_programs = array("#cstdivinity" => "Divinity",
			  "#csttheo" => "Theological Studies",
			  "#cstpc" => "Pastoral Counseling");


$programs = new foxmlPrograms();
$skos = $programs->skos;
// updating the existing labels to clearly separate grad, undergrad, and now candler
$logger->debug("Updating existing top-level program labels");
foreach ($new_labels as $id => $label) {
  $skos->$id->label = $label;
}

// add Candler top-level item
// - check if already present (don't add more than once)
if ($skos->findLabelbyId($candler["id"]) == null) {
  $logger->debug("Adding Candler");
  // add Candler as another top-level item
  $skos->addCollection($candler["id"], $candler["label"]);
  $skos->collection->addMember($candler["id"]);
} else {
  $logger->debug("Not adding Candler - already present in programs list");
  // set/update label, just to make sure it is set properly
  $skos->candler->label = $candler["label"];
}

// add Candler programs
foreach ($candler_programs as $id => $label) {
  // create collection item if not already present
  if ($skos->findLabelbyId($id) == null) {
    $skos->addCollection($id, $label);
    $logger->debug("Adding Candler program $label ($id)");
  } else {
    $logger->debug("Candler program $label ($id) is already present");
  }
  // add to candler collection if not already included
  if (! $skos->candler->collection->hasMember(preg_replace("/^#/", "", $id))) {
    $logger->debug("Candler does not yet include $id, adding as member");
    $skos->candler->collection->addMember($id);
  }
}  

// if record has changed, save to Fedora
if ($skos->hasChanged()){
  if (!$opts->noact) {
    $result = $programs->save("updating program list to include Candler");
    if ($result) {
      $logger->info("Successfully updated progams (" . $programs->pid .
		    ") at $result");
    } else {
      $logger->err("Error updating programs (" . $programs->pid . ")");
    }
  } else {	// no-act mode, simulate saving
    $logger->info("Updating programs " .  $programs->pid . " (simulated)");
  }
  
} else {
  $logger->info("Program listing is unchanged, not saving.");
}
