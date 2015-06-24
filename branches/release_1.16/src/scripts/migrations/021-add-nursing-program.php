#!/usr/bin/php -q
<?php
/**
 * Update ETDs program listings to add School of Nursing.
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
 $scriptname updates program list to add Neil Hodgson Woodruff School of Nursing with its subdivisions
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}
// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);


// Top level for School of Nursing school
$nhwson_school = array("id" => "#nhwson",
     "label" => "Neil Hodgson Woodruff School of Nursing");

/**
 * WARNING: There cannot be any duplicate id values.
 */

//departments / sub sections for School of Nursing
$nhwson_departments= array(

);


// programs for School of Nursing
// department code does not have # because it is only referenced at this point and is referenced without the #
$nhwson_programs = array(
array('id' => '#nhwson-dnp', 'label' => 'Doctor of Nursing Practice', "identifiers" => array('NRHSLD','NRPOP')),
);


$programs = new foxmlPrograms();
$skos = $programs->skos;

// add School of Nursing top-level item
// - check if already present (don't add more than once)
if ($skos->findLabelbyId($nhwson_school["id"]) == null) {
  $logger->info("Adding School of Nursing");
  // add School of Nursing as another top-level item
  $skos->addCollection($nhwson_school["id"], $nhwson_school["label"]);
  $skos->collection->addMember($nhwson_school["id"]);
} else {
  $logger->info("Not adding School of Nursing - already present in programs list");
  // set/update label, just to make sure it is set properly
  $skos->nhwson->label = $nhwson_school["label"];
}

$nhwson = $programs->skos->nhwson; //nhwson section of hierarchy

 //add School of Nursing departments
foreach ($nhwson_departments as $id => $label) {
  // create collection item if not already present
  if ($skos->findLabelbyId($id) == null) {
    $skos->addCollection($id, $label);
    $logger->info("Adding School of Nursing department $label ($id)");
  } else {
    $logger->info("School of Nursing department $label ($id) is already present");
  }
  // add to School of Nursing collection if not already included
  if (! $skos->nhwson->collection->hasMember(preg_replace("/^#/", "", $id))) {
    $logger->info("School of Nursing does not yet include $id, adding as member");
    $skos->nhwson->collection->addMember($id);
  }
}


//add School of Nursing programs to departments
foreach ($nhwson_programs as $program) {
  // create collection item if not already present
  if ($skos->findLabelbyId($program['id']) == null) {
    $skos->addCollection($program['id'], $program['label'], $program['identifiers']);
    $logger->info("Adding School of Nursing program {$program['id']}, ({$program['label']})");
  } else {
    $logger->info("School of Nursing program {$program['id']}, ({$program['label']}) is already present");
  }
  // add to School of Nursing collection if not already included

  //if department is nhwson add directly under nhwson else add under correct departemnt

      if (! $skos->nhwson->{$program['department']}->collection->hasMember(preg_replace("/^#/", "", $program['id']))) {
        $logger->info("nhwson->{$program['department']} does not yet include program {$program['id']}, adding as member");
        $skos->nhwson->{$program['department']}->collection->addMember($program['id']);
      }
      else{
          $logger->info("nhwson->{$program['department']} {$program['id']}, ({$program['label']}) is already present");
      }


}

// if record has changed, save to Fedora
if ($skos->hasChanged()){
  if (!$opts->noact) {
    $result = $programs->save("updating program list to include School of Nursing");
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
