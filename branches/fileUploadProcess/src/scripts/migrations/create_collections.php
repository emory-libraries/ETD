#!/usr/bin/php -q
<?php
/**
 * Create or update configured collection objects
 *
 * @category Etd
 * @package Etd_Scripts
 * @subpackage Etd_Migration_Scripts
 */

// set working directory to the main scripts directory so all the paths work the same
chdir("..");

// set paths, load config files, set up connection objects for fedora, solr, and ESD
require_once("bootstrap.php");
//require_once("models/FedoraCollection.php");
$opts = new Zend_Console_Getopt($common_getopts);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname creates (if necessary) and updates configured collection objects
";

try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}
// output logging - common setup function in bootstrap
$logger = setup_logging($opts->verbose);


//use maintenance_account to connect
try {
  $fedora_opts = $fedora_cfg->toArray();
  // use default fedora config opts but with maintenance account credentials
  $fedora_opts["username"] = $fedora_cfg->maintenance_account->username;
  $fedora_opts["password"] = $fedora_cfg->maintenance_account->password;
  $maintenance_fedora = new FedoraConnection($fedora_opts);
      } catch (FedoraNotAvailable $e) {
  $this->logger->err("Error connecting to Fedora with maintenance account - " . $e->getMessage());
  return;
}
Zend_Registry::set("fedora", $maintenance_fedora);

//Gei info to create / update collections from schools config
//Schools config is defined in bootstrap

$collections = array(); //All collections
foreach($schools_config as $school){
    $col=array(); // individual collection
    $col['name'] = $school->label;
    $col['pid'] = $school->fedora_collection;
    if(isset($school->acl_id)) $col['school_id'] = $school->acl_id;
    if(isset($school->OAIInfo)) $col['OAI'] = $school->OAIInfo;

    $collections[] = $col; //add each collection to the array
}

$root_collection = array_shift($collections);


// first make sure root ETD collection exists & is up-to-date
$collection = NULL;
try {
  $collection = new FedoraCollection($root_collection['pid']);
} catch (FedoraObjectNotFound $e) {
  $logger->debug("FedoraObjectNotFound for {$root_collection['pid']}: " . $e->getMessage());
}

//root collection does not exists - create it
$label = "ETD {$root_collection['name']} Collection";

if (!$collection) {
  
  $logger->notice("Top-level collection {$root_collection['pid']} not found in Fedora creating it");
  $collection  = new FedoraCollection();
  $collection->pid = $root_collection['pid'];
  $collection->label = $label;
  $collection->owner = 'etdadmin';
  if ($opts->noact) {
      $logger->notice("Ingesting {$root_collection['pid']} into Fedora (simulated)");
      $logger->debug($collection->saveXML());
  } else {
    $success = $collection->ingest("creating ETD collection object");
    if ($success) {
      $logger->notice("Successfully ingested {$root_collection['pid']} into Fedora");
    } else {
      $logger->err("Failed to ingest {$root_collection['pid']} into Fedora");
    }
  }
}
else{
    $logger->notice("Top-level collection {$root_collection['pid']} found");
}

$collection->label = $label;
$collection->owner = "etdadmin";
// set/update OAI setSpec & setName
$logger->notice("Setting SPEC: {$root_collection['OAI']} NAME: {$root_collection['name']}");
$collection->setOAISetInfo($root_collection['OAI'], $root_collection['name']);

if ($collection->rels_ext->hasChanged() || $collection->hasInfoChanged()) {
  if ($opts->noact) {
    $logger->notice("Updating {$root_collection['pid']} in Fedora (simulated)");
    $logger->debug($collection->rels_ext->saveXML());
  } else {
    $success = $collection->save("update OAI set information");
    if ($success) {
      $logger->notice("Successfully updated {$root_collection['pid']}  OAI set information");
    } else {
      $logger->err("Error updating OAI set information for {$root_collection['pid']}");
    }
  }
} else {
  $logger->notice("Collection {$root_collection['pid']} is unchanged, not updating in Fedora");
}

// now make sure all subcollections exist & are set up properly
foreach ($collections as $col) {
  // get the collection from fedora if it already exists
  $collection = NULL;
  try {
    $collection = new FedoraCollection($col['pid']);
  } catch (Exception $e) {
    $logger->debug("FedoraObjectNotFound on {$col['pid']}: " . $e->getMessage());
    unset($collection);
  }

  // create a label
  $label = "ETD {$col['name']} Collection";

  // if collection object was not found, create it
  if (! isset($collection) || !$collection->pid) {
    $logger->notice("Collection {$col['pid']} not found in Fedora; creating it");
    $collection  = new FedoraCollection();
    $collection->pid = $col['pid'];
    $collection->label = $label;
    $collection->owner = "etdadmin";

    if ($opts->noact) {
      $logger->notice("Ingesting {$col['pid']}) into Fedora (simulated)");
      $logger->debug($collection->saveXML());
    } else {
      $success = $collection->ingest("creating collection object");
      if ($success) {
        $logger->notice("Successfully ingested {$col['pid']} into Fedora");
      } else {
        $logger->err("Failed to ingest {$col['pid']} into Fedora");
      }
    }
  }
 else{
    $logger->notice("Collection {$col['pid']} found");
 
 }

 // make sure all sub-collections belong to root ETD collection
   if (! $collection->rels_ext->isMemberOfCollections->includes($fedora->risearch->pid_to_risearchpid($root_collection['pid']))) {
       $logger->notice("Adding {$col['pid']} to collection {$root_collection['pid']}");
     $collection->rels_ext->addRelationToResource("rel:isMemberOfCollection", $root_collection['pid']);
   }

  //update label based on config
  $collection->label = $label;
  $collection->owner = "etdadmin";

  // set/update OAI setSpec & setName on collection object
  // - OAI setSpec must contain only unreserved characters; convert spaces to "-"
  $setid = $root_collection['OAI'] . ":" . $col['school_id'];
  // - check that setSpec is valid; assuming only one level of collection hierarchy (no : delimiters)
  //   setSpec should consist of unreserved characters (alphanum plus mark; see rfc2396)
  if (! preg_match("/^[a-zA-Z0-9]+:[a-zA-Z0-9]+$/", $setid)) {
    $logger->err("Set id for $label ($setid) is not a valid OAI setSpec");
    // skip to the next collection - do not save/update this record
    continue;
  }

  $logger->debug("Set id for $label is $setid");
  $collection->setOAISetInfo($setid, $label);

  if ($collection->rels_ext->hasChanged() || $collection->hasInfoChanged()) {
    if ($opts->noact) {
      $logger->notice("Updating {$col['pid']}  in Fedora (simulated)");
      $logger->debug($collection->rels_ext->saveXML());
    } else {
      $success = $collection->save("update OAI set information");
      if ($success) {
        $logger->notice("Successfully updated {$col['pid']} OAI set information");
      } else {
        $logger->err("Error updating OAI set information for {$col['pid']}");
      }
    }
  } else {
    $logger->notice("Collection {$col['pid']} is unchanged, not updating in Fedora");
  }

}
$logger->notice("DONE!");
