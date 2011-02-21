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

// Top level for New school
$add_school_data = array(
  //array("id" => '#eagles', "label" => 'Philadelphia Eagles School'),
);
     
// departments
$add_dept_data = array(
  //array("school" => 'eagles', "id" => '#phillies', "label" =>'Philadelphia Phillies Department'),
);


// programs 
// department code does not have # because it is only referenced at this point and is referenced without the #
$add_pgms_data = array(
  array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'#uaas', 
      "label"=>'African American Studies', "identifiers"=>array('AASBA')),
  array("school"=>'undergrad', "dept"=>'uhumanities', "id"=>'#uplaywrt',
      "label"=>'Playwriting', "identifiers"=>array('PLAYWRTBA')),
);

$programs = new foxmlPrograms();
$skos = $programs->skos;

if (count($add_school_data) > 0) { // Add any new schools
  foreach ($add_school_data as $school) {
    $s = preg_replace("/^#/", "", $school["id"]);
    if ($skos->findLabelbyId($school["id"]) == null) {
      $logger->info("Adding {$school["label"]}");
      // add new school as another top-level item
      $skos->addCollection($school["id"], $school["label"]);
      $skos->collection->addMember($school["id"]);
    } else {
      $logger->info("Not adding {$school["label"]} - already present in programs list, but update label");
      // set/update label, just to make sure it is set properly
      $skos->{$s}->label = $school["label"];
    }
  }
}
  
if (count($add_dept_data) > 0) {  // Add any new departments
   //add Rollins departments
  foreach ($add_dept_data as $dept) {
    // create collection item if not already present
    if ($skos->findLabelbyId($dept['id']) == null) {
      $skos->addCollection($dept['id'], $dept['label']);
      $logger->info("Adding {$dept['school']} department {$dept['label']} ({$dept['id']})");
    } else {
      $logger->info("{$dept['school']} department {$dept['label']} ({$dept['id']})) is already present");
    }
    // add to Rollins collection if not already included
    if (! $skos->{$dept['school']}->collection->hasMember(preg_replace("/^#/", "", $dept['id']))) {
      $logger->info("{$dept['school']} does not yet include {$dept['id']}, adding as member");
      $skos->{$dept['school']}->collection->addMember($dept['id']);
    }
  }
}

if (count($add_pgms_data) > 0) {  // Add any new programs
  foreach ($add_pgms_data as $program) {
    // create collection item if not already present
    if ($skos->findLabelbyId($program['id']) == null) {
      $skos->addCollection($program['id'], $program['label'], $program['identifiers']);
      $logger->info("Adding school[{$program['school']}] program[{$program['id']}], label[({$program['label']}]");
    } else {
      $logger->info("{$program['school']} program {$program['id']}, ({$program['label']}) is already present");
    }
    if (! $skos->{$program['school']}->{$program['dept']}->collection->hasMember(preg_replace("/^#/", "", $program['id']))) {
      $logger->info("{$program['school']}->{$program['dept']} does not yet include program {$program['id']}, adding as member");
      $skos->{$program['school']}->{$program['dept']}->collection->addMember($program['id']);
    }
    else{
      $logger->info("{$program['school']}->{$program['dept']} {$program['id']}, ({$program['label']}) is already present");
    }
  }
}

// if record has changed, save to Fedora
if ($skos->hasChanged()){
  if (!$opts->noact) {
    $result = $programs->save("updating program list to include new data.");
    if ($result) {
      $logger->info("Successfully updated progams (" . $programs->pid .
        ") at $result");
    } else {
      $logger->err("Error updating programs (" . $programs->pid . ")");
    }
  } else {  // no-act mode, simulate saving
    $logger->info("Updating programs " .  $programs->pid . " (simulated)");
  }

} else {
  $logger->info("Program listing is unchanged, not saving.");
}
