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


// Partnering Agencies members
$partnering_agencies_data = array(
array('id' => '#pa-na', 'label' => 'Does not apply (no collaborating organization)'),
array('id' => '#pa-cdc',  'label' => 'CDC'),
array('id' => '#pa-usfed',  'label' => 'US (Federal) agency other than CDC'),
array('id' => '#pa-gahealth',  'label' => 'Georgia state or local health department'),
array('id' => '#pa-us',  'label' => 'State or local health department (not in Georgia)'),
array('id' => '#pa-ga',  'label' => 'Georgia state or local agency (not a health department)'),
array('id' => '#pa-statelocal',  'label' => 'State or local governmental agency (not a health department and not in Georgia)'),
array('id' => '#pa-nat', 'label' => 'National or regional non-profit organization (e.g., American Cancer Society)'),
array('id' => '#pa-commun',  'label' => 'Community-based non-profit organization (e.g., AID Atlanta)'),
array('id' => '#pa-inat', 'label' => 'International Non-governmental organization (e.g., CARE, Inc.)'),
array('id' => '#pa-inatgov', 'label' => 'International governmental organization (e.g., Agency for International Development, etc.)'),
array('id' => '#pa-nonus', 'label' => 'Non-US governmental agency (e.g., Minister of Health in Haiti)'),
array('id' => '#pa-emory', 'label' => 'Emory University schools, faculty or affiliated programs'),
array('id' => '#pa-edu', 'label' => 'University, college or educational institution (other than Emory)'),
array('id' => '#pa-health', 'label' => 'Hospital or other health care provider'),
array('id' => '#pa-relig', 'label' => 'Religious-based organization'),
array('id' => '#pa-com', 'label' => 'Industrial or Commercial (for-profit) organization')
);

  $vocabs = NULL;
  $vocabs = new foxmlVocabularies(); // access existing or create new object. 

  if (!$vocabs) {
    $logger->err("Failed to create/access fedora object [$pid]");
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

   //add partnering agencies
  foreach ($partnering_agencies_data as $rpa) { 
    // create collection item if not already present
    if ($skos->findLabelbyId($rpa['id']) == null) {
      $skos->addCollection($rpa['id'], $rpa['label']);
      $logger->info("Adding Partnering Agency (" . $rpa['id'] . ")");
    } else {    
      $logger->info("Partnering Agency (" . $rpa['id'] . ") is already present");
    }
    // add to partnering agencies collection if not already included
    if (! $skos->partnering_agencies->collection->hasMember(preg_replace("/^#/", "", $rpa['id']))) {
      $logger->info("Partnering Agency does not yet include  (" . $rpa['id'] . ") , adding as member");
      $skos->partnering_agencies->collection->addMember($rpa['id']);
    }
  }

  // if record has changed, save to Fedora; or create fedora object when not found.
  if ($skos->hasChanged()){
    if (!$opts->noact) {
        try {
          $result = $vocabs->save("updating partnering_agencies list to include new data.");
        }
        catch (FedoraObjectNotFound $e) {
          $result = $vocabs->ingest("ingesting partnering_agencies list to include new data.");
        }    
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
