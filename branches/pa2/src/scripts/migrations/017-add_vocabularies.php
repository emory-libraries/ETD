#!/usr/bin/php -q
<?php
/**
 * Add Controlled Vocabulary object and add members for Rollins Partnering Agencies.
 *
 * @category Etd
 * @package Etd_Scripts
 * @subpackage Etd_Migration_Scripts
 */

// set working directory to the main scripts directory so all the paths work the same
chdir("..");
// set paths, load config files, set up connection objects for fedora, solr, and ESD
require_once("bootstrap.php");
require_once("models/vocabularies.php");
$opts = new Zend_Console_Getopt($common_getopts);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname Add Controlled Vocabulary object and add members for Rollins Partnering Agencies
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
if (! isset($config->vocabularies_collection->pid) || $config->vocabularies_collection->pid == "") {
  throw new FoxmlException("Configuration does not contain vocabulary pid, cannot initialize");
}
$pid = $config->vocabularies_collection->pid;
     
// Partnering Agencies collection
$partnering_agencies = array("id" => "#partnering_agencies",
     "label" => "Partnering Agencies");

// Rollins school collection
$rollins = array("id" => "#rollins",
     "label" => "Rollins School of Public Health");

// Rollins Partnering Agencies members
$rollins_partnering_agencies = array(
array('id' => '#na', 'label' => 'Does not apply (no collaborating organization)'),
array('id' => '#cdc',  'label' => 'CDC'),
array('id' => '#usfed',  'label' => 'US (Federal) agency other than CDC'),
array('id' => '#gahealth',  'label' => 'Georgia state or local health department'),
array('id' => '#us',  'label' => 'State or local health department (not in Georgia)'),
array('id' => '#ga',  'label' => 'Georgia state or local agency (not a health department)'),
array('id' => '#statelocal',  'label' => 'State or local governmental agency (not a health department and not in Georgia)'),
array('id' => '#nat', 'label' => 'National or regional non-profit organization (e.g., American Cancer Society)'),
array('id' => '#commun',  'label' => 'Community-based non-profit organization (e.g., AID Atlanta)'),
array('id' => '#inat', 'label' => 'International Non-governmental organization (e.g., CARE, Inc.)'),
array('id' => '#inatgov', 'label' => 'International governmental organization (e.g., Agency for International Development, etc.)'),
array('id' => '#nonus', 'label' => 'Non-US governmental agency (e.g., Minister of Health in Haiti)'),
array('id' => '#emory', 'label' => 'Emory University schools, faculty or affiliated programs'),
array('id' => '#edu', 'label' => 'University, college or educational institution (other than Emory)'),
array('id' => '#health', 'label' => 'Hospital or other health care provider'),
array('id' => '#relig', 'label' => 'Religious-based organization'),
array('id' => '#com', 'label' => 'Industrial or Commercial (for-profit) organization')
);

$vocabs = NULL;


try { // Does the pid exist?
  $vocabs = new foxmlVocabularies("#vocabularies");
} catch (FoxmlException $e) {  
  $logger->debug("FoxmlException for $pid: " . $e->getMessage());
  return;  
} catch (FedoraObjectNotFound $e) {  
  $logger->debug("FedoraObjectNotFound for $pid: " . $e->getMessage());
} catch (FedoraAccessDenied $e) {   
  $logger->err("Error connecting to Fedora with maintenance account - " . $e->getMessage());
  return;
}

if (!$vocabs) { // The fedora object does not exist, so create 
  $logger->notice("Top-level collection {$config->vocabularies_collection->label} ($pid) not found in Fedora; creating it");
  $new_collection  = new foxmlVocabularies(); // no parameter indicates create collection.
  
  // Set the initial values for the fedora collection
  $new_collection->pid = $config->vocabularies_collection->pid;
  $new_collection->label = $config->vocabularies_collection->label;
  $new_collection->owner = $config->etdOwner;  
  
  // Add a model in the RELS-EXT datastream (Subject/Predicate/Object)
  $new_collection->setContentModel($config->vocabularies_collection->model_object);
    
  if ($opts->noact) {   
      $logger->info("Ingesting {$config->vocabularies_collection->label} ($pid) into Fedora (simulated)");
      $logger->debug($new_collection->saveXML());
  } else {
    $success = $new_collection->ingest("creating LSDI collection object");
    if ($success) {     
      $logger->info("Successfully ingested {$config->vocabularies_collection->label} ($pid) into Fedora");
      // fedora object has been create, now load it.
      $vocabs = new foxmlVocabularies("#vocabularies");
    } else {     
      $logger->err("Failed to ingest {$config->vocabularies_collection->label} ($pid) into Fedora");
    }
  }
}

if (!$vocabs) {
  $logger->err("Failed to create the  {$config->vocabularies_collection->label} ($pid) in Fedora");
  return;
}

// Get the vocabularies SKOS datastream
$skos = $vocabs->skos;

// add Partnering Agencies top-level item
// - check if already present (don't add more than once)
if ($skos->findLabelbyId($partnering_agencies["id"]) == null) { 
  $logger->info("Adding Partnering Agencies Collection");
  // add Partnering Agencies as another top-level item
  $skos->addCollection($partnering_agencies["id"], $partnering_agencies["label"]);
  $skos->collection->addMember($partnering_agencies["id"]);
} else {
  $logger->info("Not adding Partnering Agencies - already present in vocabularies list");
  // set/update label, just to make sure it is set properly
  $skos->partnering_agencies->label = $partnering_agencies["label"];
}

// add Rollins top-level item
// - check if already present (don't add more than once)
if ($skos->findLabelbyId($rollins["id"]) == null) { 
  $logger->info("Adding Rollins Collection ");
  // add Rollins as another top-level item
  $skos->addCollection($rollins["id"], $rollins["label"]);
  $logger->info("Adding Rollins Collection added.\n"); 
  $skos->partnering_agencies->collection->addMember($rollins["id"]);  
  $logger->info("Adding Rollins Collection as member.\n");  
} else { 
  $logger->info("Not adding Rollins - already present in vocabularies list");
  // set/update label, just to make sure it is set properly
  $skos->partnering_agencies->rollins->label = $rollins["label"];
}

 //add Rollins partnering agencies
foreach ($rollins_partnering_agencies as $rpa) { 
  // create collection item if not already present
  if ($skos->findLabelbyId($rpa['id']) == null) {
    $skos->addCollection($rpa['id'], $rpa['label']);
    $logger->info("Adding Rollins Partnering Agency (" . $rpa['id'] . ")");
  } else {    
    $logger->info("Rollins Partnering Agency (" . $rpa['id'] . ") is already present");
  }
  // add to Rollins partnering agencies collection if not already included
  if (! $skos->partnering_agencies->rollins->collection->hasMember(preg_replace("/^#/", "", $rpa['id']))) {
    $logger->info("Partnering Agency does not yet include  (" . $rpa['id'] . ") , adding as member");
    $skos->partnering_agencies->rollins->collection->addMember($rpa['id']);
  }
}

// if record has changed, save to Fedora
if ($skos->hasChanged()){
  if (!$opts->noact) {
    $result = $vocabs->save("Updating partnering_agencies list to include Rollins");
    if ($result) {
      $logger->info("Successfully updated partnering_agencies ($pid) at $result");
    } else {
      $logger->err("Error updating partnering_agencies ($pid)");
    }
  } else {  // no-act mode, simulate saving
    $logger->info("Updating partnering_agencies $pid (simulated)");
  }

} else {
  $logger->info("Partnering_agencies listing is unchanged, not saving.");
}

?>
