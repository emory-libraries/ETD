#!/usr/bin/php -q
<?php

  /**
	This script cleans known xacml problems on existing records.
	- remove draft rule if status is not draft
	- remove view rule and add new one from current template, to
  	  update graduate coordinator portion of the rule (August 2008)
   */


  // ZendFramework, etc.
ini_set("include_path", "../app/:../config:../app/models:../app/modules/:../lib:../lib/ZendFramework:../lib/fedora:../lib/xml-utilities:js/:/home/rsutton/public_html:" . ini_get("include_path")); 

require("Zend/Loader.php");
Zend_Loader::registerAutoload();

require_once("models/etd.php");
require_once("models/etd_notifier.php");
require_once("api/FedoraConnection.php");

$env_config = new Zend_Config_Xml("../config/environment.xml", "environment");
Zend_Registry::set('env-config', $env_config);
$config = new Zend_Config_Xml("../config/config.xml", $env_config->mode);
Zend_Registry::set('config', $config);
$fedora_cfg = new Zend_Config_Xml("../config/fedora.xml", $env_config->mode);
// needs to connect to Fedora using maintenance account to view and modify unpublished records
$fedora = new FedoraConnection($fedora_cfg->maintenance_account->user,
			       $fedora_cfg->maintenance_account->password,
			       $fedora_cfg->server, $fedora_cfg->port,
			       $fedora_cfg->protocol, $fedora_cfg->resourceindex);
Zend_Registry::set('fedora', $fedora);
 
// set up connection to solr to find records with expiring embargoes
$solr_config = new Zend_Config_Xml("../config/solr.xml", $env_config->mode);
$solr = new Etd_Service_Solr($solr_config->server, $solr_config->port, $solr_config->path);
$solr->addFacets($solr_config->facet->toArray());
Zend_Registry::set('solr', $solr);

$opts = new Zend_Console_Getopt(
	array(
	      'pid|p=s'	    => 'Clean xacml on one etd record only',
	      'verbose|v=s' => 'Output level/verbosity; one of error, warn, notice, info, debug (default: error)',
	      'noact|n'	    => "Test/simulate - don't actually do anything (no actions)",
	      )
	);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname goes through all etd records and cleans object xacml policy
";


try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}


$writer = new Zend_Log_Writer_Stream("php://output");
// minimal output format - don't display timestamp or numeric priority
$format = '%priorityName%: %message%' . PHP_EOL;
$formatter = new Zend_Log_Formatter_Simple($format);
$writer->setFormatter($formatter);
$logger = new Zend_Log($writer);

// set level of output to be displayed based on command line parameter
switch ($opts->verbose) {
 case "warn":    $verbosity = Zend_Log::WARN; break;
 case "notice":  $verbosity = Zend_Log::NOTICE; break;
 case "info":    $verbosity = Zend_Log::INFO; break;
 case "debug":   $verbosity = Zend_Log::DEBUG; break;   
 case "error":   $verbosity = Zend_Log::ERR; break;
 default:
   print "Bad verbose level: must be one of error, warn, notice, info, or debug\n";
   echo $usage;
   exit;
 }
$filter = new Zend_Log_Filter_Priority($verbosity);
$logger->addFilter($filter);


$count = 0;


// if pid is specified, run single record mode
if ($opts->pid) {
  $etd = new etd($opts->pid);
  clean_xacml($etd);
  return;
}


// find *all* records, no matter their status
$start = 0;
$setsize = 30;		// retrieve records in chunks of 100
$total = $setsize;	// set to an initial value to iterate at least once
while ($start < $total) {	// loop through the images in chunks of setsize
  $etds = etd::find($start, $setsize, array(), $total); 
  $logger->info("Processing records $start-" . ($start + $setsize) . " of $total");

  foreach ($etds as $etd) {
    if (clean_xacml($etd)) $count++;
  }

  $start += $setsize;	// move to the next set of records
}

$logger->info($count . " records changed");




/**
 * do the actual work of cleaning the xacml
 * @param etd $etd to be cleaned
 * @return boolean whether or not record was changed
 */
function clean_xacml(etd $etd) {
  global $logger, $opts;

  $logger->debug("Processing " . $etd->pid);
  // update view rules
  //  - advisor & committee members should be listed on view rules for etd & all etdfiles but original
  //  - departmental staff (filtered by department name) is allowed to view
  
  // remove old view role and add new one to get new grad coordinator xacml
  $etd->removePolicyRule("view");
  $etd->addPolicyRule("view");		// should not get added to original files
  
  // add committee chairs & members to policies
  foreach (array_merge($etd->mods->chair, $etd->mods->committee) as $committee) {
    if ($committee->id != "") {
      $logger->debug("Adding committee id " . $committee->id . " to policy view rule");
      foreach (array_merge(array($etd), $etd->pdfs, $etd->supplements) as $obj) {
	$obj->policy->view->condition->addUser($committee->id);	  
      }
    }
  }
  // set department for departmental staff view
  if ($etd->mods->department != "") {
    $logger->debug("Setting department to " . $etd->mods->department . " on policy view rule");
    foreach (array_merge(array($etd), $etd->pdfs, $etd->supplements) as $obj) {
      $obj->policy->view->condition->department = $etd->mods->department;
    }
  }
  
  // if etd is no longer in draft status, make sure draft rule is removed from etd files
  if ($etd->status() != "draft") {
    // draft rule could be present in one of the etdfiles (hard to detect); just force removal
    $logger->debug("Record is not in draft status; removing any draft policy rules.");
    $etd->removePolicyRule("draft");		// remove draft policy on etd & all files
  }
  
  // check if anything has been changed (for reporting purposes)
  $hasChanged = false;
  foreach (array_merge(array($etd), $etd->pdfs, $etd->supplements) as $obj) {
    if ($obj->policy->hasChanged()) $hasChanged = true;
  }
  
  if (!$hasChanged) {
    $logger->info("No changes to policies on " . $etd->pid . " or associated files");
  } elseif (!$opts->noact) {
    $result = $etd->save("cleaned xacml policy - advisor, committee, department on view rule; draft rule");
    if ($result)
      $logger->info("Saved changes to " . $etd->pid . " and associated files");
    else	// save failed
      $logger->err("Error saving changes to " . $etd->pid . " and associated files");
  }
  // NOTE: if etdfiles changed but etd did not, might look like an error here when it is not


  
  // return whether or not the record was changed
  return $hasChanged;
}