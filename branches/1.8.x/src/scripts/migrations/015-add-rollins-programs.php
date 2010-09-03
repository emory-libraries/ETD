#!/usr/bin/php -q
<?php
/**
 * Update ETDs program listings to add Rollins.
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
 $scriptname updates program list to add Rollins School of Public Health with its subdivisions
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}
// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);


// Top level for Rollins school
$rollins_school = array("id" => "#rollins",
     "label" => "Rollins School of Public Health");


//departments / sub sections for Rollins
$rollins_departments= array(
    '#cmph' => 'Career Masters of Public Health',
    '#bshe' => 'Behavioral Sciences and Health Education',
    '#bb' => 'Biostatistics and Bioinformatics',
    '#eh' => 'Environmental Health',
    '#ep' => 'Epidemiology',
    '#hpm' => 'Health Policy and Management',
    '#ih' => 'Hubert Department of Global Health'

);


// programs for Rollins
// department code does not have # because it is only referenced at this point and is referenced without the #
$rollins_programs = array(
array('id' => '#apepi', 'label' => 'Applied Epidemiology', 'department' => 'cmph', "identifiers" => array('APEPIMPH')),
array('id' => '#hcom',  'label' => 'Health Care Outcomes Management', 'department' => 'cmph', "identifiers" => array('HCOMMPH')),
array('id' => '#mchepi',  'label' => 'Maternal and Child Health Epidemiology', 'department' => 'cmph', "identifiers" => array('MCHEPIMPH')),
array('id' => '#ms',  'label' => 'Management Science', 'department' => 'cmph', "identifiers" => array('MSMPH')),
array('id' => '#ps',  'label' => 'Prevention Science', 'department' => 'cmph', "identifiers" => array('PSMPH')),
array('id' => '#bios',  'label' => 'Biostatistics', 'department' => 'bb',  "identifiers" => array('BIOSMPH','BIOSMSPH')),
array('id' => '#info',  'label' => 'Public Health Informatics', 'department' =>'bb',  "identifiers" => array('INFOMSPH')),
array('id' => '#eoh', 'label' => 'Environmental and Occupational Health', 'department' =>'eh', "identifiers" => array('EOHMPH')),
array('id' => '#eohepi',  'label' => 'Environmental&; Occupational Health Epidemiology', 'department' =>'eh', "identifiers" => array('EOHEPIMPH')),
array('id' => '#eohih', 'label' => 'Global Environmental Health', 'department' =>'eh', "identifiers" => array('EOHIHMPH')),
array('id' => '#epi', 'label' => 'Epidemiology', 'department' =>'ep', "identifiers" => array('EPIMPH', 'EPIMPSH')),
array('id' => '#glepi', 'label' => 'Global Epidemiology', 'department' =>'ep', "identifiers" => array('GLEPIMPH','GLEPIMSPH'))
);


$programs = new foxmlPrograms();
$skos = $programs->skos;

// add Rollins top-level item
// - check if already present (don't add more than once)
if ($skos->findLabelbyId($rollins_school["id"]) == null) {
  $logger->info("Adding Rollins");
  // add Rollins as another top-level item
  $skos->addCollection($rollins_school["id"], $rollins_school["label"]);
  $skos->collection->addMember($rollins_school["id"]);
} else {
  $logger->info("Not adding Rollins - already present in programs list");
  // set/update label, just to make sure it is set properly
  $skos->rollins->label = $rollins_school["label"];
}

$rollins = $programs->skos->rollins; //rollins section of hierarchy

 //add Rollins departments
foreach ($rollins_departments as $id => $label) {
  // create collection item if not already present
  if ($skos->findLabelbyId($id) == null) {
    $skos->addCollection($id, $label);
    $logger->info("Adding Rollins department $label ($id)");
  } else {
    $logger->info("Rollins department $label ($id) is already present");
  }
  // add to Rollins collection if not already included
  if (! $skos->rollins->collection->hasMember(preg_replace("/^#/", "", $id))) {
    $logger->info("Rollins does not yet include $id, adding as member");
    $skos->rollins->collection->addMember($id);
  }
}


//add Rollins programs to departments
foreach ($rollins_programs as $program) {
  // create collection item if not already present
  if ($skos->findLabelbyId($program['id']) == null) {
    $skos->addCollection($program['id'], $program['label'], $program['identifiers']);
    $logger->info("Adding Rollins program {$program['id']}, ({$program['label']})");
  } else {
    $logger->info("Rollins program {$program['id']}, ({$program['label']}) is already present");
  }
  // add to Rollins collection if not already included
  
  //if department is rollins add directly under rollins else add under correct departemnt

      if (! $skos->rollins->{$program['department']}->collection->hasMember(preg_replace("/^#/", "", $program['id']))) {
        $logger->info("rollins->{$program['department']} does not yet include program {$program['id']}, adding as member");
        $skos->rollins->{$program['department']}->collection->addMember($program['id']);
      }
      else{
          $logger->info("rollins->{$program['department']} {$program['id']}, ({$program['label']}) is already present");
      }

 
}

// if record has changed, save to Fedora
if ($skos->hasChanged()){
  if (!$opts->noact) {
    $result = $programs->save("updating program list to include Rollins");
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
