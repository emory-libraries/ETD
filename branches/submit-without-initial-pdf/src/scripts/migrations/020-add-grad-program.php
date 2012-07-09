#!/usr/bin/php -q
<?php
/**
 * Update ETDs program listings to a new school, department or program.
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
 $scriptname updates program list with new schools, departments and programs
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}
// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);

// Get the pid from the config.xml file
if (! Zend_Registry::isRegistered("config")) {
  throw new FoxmlException("Configuration not registered, cannot retrieve pid");
}
$config = Zend_Registry::get("config");
if (! isset($config->programs_collection->pid) || $config->programs_collection->pid == "") {
  throw new FoxmlException("Configuration does not contain vocabulary pid, cannot initialize");
}
$pid = $config->programs_collection->pid;
     
// programs 
$program_data = array(
array("level01"=>'grad', "level02"=>'natlhealthsci', "id"=>'bioeth', "label"=>'Bioethics', "identifiers"=>array('BIOETHMA')),
);

  $programs = null;
  $programs  = new foxmlPrograms(); // access existing or create new object.
  
  if (!$programs) {
    $logger->err("Failed to create/access fedora object [$pid]");
    return;
  }
  $skos = $programs->skos;

  foreach ($program_data as $pgm) {

    if ($skos->findLabelbyId('#'.$pgm["id"]) == null) {
      $logger->info("Adding collection " . '#' . $pgm["id"]);
      
      // Add the collection
      if (isset($pgm["identifiers"])) {
        /**
         * @todo remove the dc identifiers not in this list or any duplicates.
         */      
        $skos->addCollection('#'.$pgm["id"], $pgm["label"], $pgm["identifiers"]);      
      }
      else {     
        $skos->addCollection('#'.$pgm["id"], $pgm["label"]);
      }
    } 
      
    // Set the collection as a member of another collection
    if (isset($pgm["level01"]) && isset($pgm["level02"]) && isset($pgm["level03"])) {
      if (! $skos->{$pgm["level01"]}->{$pgm["level02"]}->{$pgm["level03"]}->collection->hasMember($pgm["id"])) {
        $logger->info("Adding program {$pgm["id"]} to {$pgm["level01"]}->{$pgm["level02"]}->{$pgm["level03"]}");       
        $skos->{$pgm["level01"]}->{$pgm["level02"]}->{$pgm["level03"]}->collection->addMember('#'.$pgm["id"]);
      }
      if ($skos->{$pgm["level01"]}->{$pgm["level02"]}->{$pgm["level03"]}->{$pgm["id"]}->label != preg_replace("/&amp;/", "&", $pgm["label"])) {
        $cur_label = $skos->{$pgm["level01"]}->{$pgm["level02"]}->{$pgm["level03"]}->{$pgm["id"]}->label;
        $logger->info("Set label for [{$pgm["id"]}] old=[$cur_label]  new=[{$pgm["label"]}]\n");      
        $skos->{$pgm["level01"]}->{$pgm["level02"]}->{$pgm["level03"]}->{$pgm["id"]}->label = $pgm["label"];   
      }    
    }
    elseif (isset($pgm["level01"]) && isset($pgm["level02"])) {  
      if (! $skos->{$pgm["level01"]}->{$pgm["level02"]}->collection->hasMember($pgm["id"])) {
        $logger->info("Adding program {$pgm["id"]} to {$pgm["level01"]}->{$pgm["level02"]}");       
        $skos->{$pgm["level01"]}->{$pgm["level02"]}->collection->addMember('#'.$pgm["id"]);
      }
      if ($skos->{$pgm["level01"]}->{$pgm["level02"]}->{$pgm["id"]}->label != preg_replace("/&amp;/", "&", $pgm["label"])) {
        $cur_label = $skos->{$pgm["level01"]}->{$pgm["level02"]}->{$pgm["id"]}->label;
        $logger->info("Set label for [{$pgm["id"]}] old=[$cur_label]  new=[{$pgm["label"]}]\n");      
        $skos->{$pgm["level01"]}->{$pgm["level02"]}->{$pgm["id"]}->label = $pgm["label"];   
      }    
    }
    elseif (isset($pgm["level01"])) { 
      if (! $skos->{$pgm["level01"]}->collection->hasMember($pgm["id"])) {
        $logger->info("Adding program {$pgm["id"]} to {$pgm["level01"]}");       
        $skos->{$pgm["level01"]}->collection->addMember('#'.$pgm["id"]);
      }
      if ($skos->{$pgm["level01"]}->{$pgm["id"]}->label != preg_replace("/&amp;/", "&", $pgm["label"])) {
        $cur_label = $skos->{$pgm["level01"]}->{$pgm["id"]}->label;
        $logger->info("Set label for [{$pgm["id"]}] old=[$cur_label]  new=[{$pgm["label"]}]\n");      
        $skos->{$pgm["level01"]}->{$pgm["id"]}->label = $pgm["label"];   
      }
    }
    else {    
      if (! $skos->collection->hasMember($pgm["id"])) {
        $logger->info("Adding program {$pgm["id"]} to programs collection.");       
        $skos->collection->addMember('#'.$pgm["id"]);
      }
      if ($skos->{$pgm["id"]}->label != preg_replace("/&amp;/", "&", $pgm["label"])) {
        $cur_label = $skos->{$pgm["id"]}->label;
        $logger->info("Set label for [{$pgm["id"]}] old=[$cur_label]  new=[{$pgm["label"]}]\n");      
        $skos->{$pgm["id"]}->label = $pgm["label"];   
      }          
    }
  }

  // if record has changed, save to Fedora; or create fedora object when not found.
  if ($skos->hasChanged()){  
    if (!$opts->noact) {
      try {
        $result = $programs->save("updating program list to include new data.");
      }
      catch (FedoraObjectNotFound $e) {
        $result = $programs->ingest("ingesting program list to include new data.");
      }
      if ($result) {
        $logger->info("Successfully updated programs ($pid) at $result");
      } else {
        $logger->err("Error updating programs $pid)");
      }
    } else {  // no-act mode, simulate saving
      $logger->info("Updating programs $pid (simulated)");
    }

  } else {
    $logger->info("Program listing is unchanged, not saving.");
  }
