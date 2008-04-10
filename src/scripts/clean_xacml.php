#!/usr/bin/php -q
<?php


// ZendFramework, etc.
ini_set("include_path", "../app/:../config:../app/models:../app/modules/:../lib:../lib/ZendFramework:../lib/fedora:../lib/xml-utilities:js/:/home/rsutton/public_html:" . ini_get("include_path")); 

require("Zend/Loader.php");
Zend_Loader::registerAutoload();

require_once("Zend/Console/Getopt.php");

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
require_once("solr.php");
$solr_config = new Zend_Config_Xml("../config/solr.xml", $env_config->mode);
$solr = new solr($solr_config->server, $solr_config->port);
$solr->addFacets(explode(',', $solr_config->facets)); 	// array of default facet terms
Zend_Registry::set('solr', $solr);

$opts = new Zend_Console_Getopt(
  array(
    'verbose|v=s'      => 'Output level/verbosity; one of error, warn, notice, info, debug (default: error)',
    'noact|n'	       => "Test/simulate - don't actually do anything (no actions)",
    )
  );

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
  FIXME: do we need more information here?
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
 case "error": 
 default:
   $verbosity = Zend_Log::ERR; break;
 }
$filter = new Zend_Log_Filter_Priority($verbosity);
$logger->addFilter($filter);




// find ETDs with embargoes that will expire in 60 days
$expiration = date("Ymd", strtotime("+60 days"));	// 60 days from now
$logger->info("Searching for unpublished records");
$etds = etd::findUnpublished(0, 2); 	// enough to get all records in current state
$logger->info("Found " . count($etds) . " record" . ((count($etds) != 1) ? "s" : ""));

foreach ($etds as $etd) {
  $logger->info("Processing " . $etd->pid);
  // update view rules
  //  - advisor & committee members should be listed on view rules for etd & all etdfiles but original
  //  - departmental staff (filtered by department name) is allowed to view
  
  // advisor is allowed to view
  if ($etd->mods->advisor->id != "") {
    $logger->debug("Adding advisor id " . $etd->mods->advisor->id . " to policy view rule");
    foreach (array_merge(array($etd), $etd->pdfs, $etd->supplements) as $obj) {
      $obj->policy->view->condition->addUser($etd->mods->advisor->id);	  
    }
  }
  // committee members are allowed to view
  foreach ($etd->mods->committee as $cm) {
    if ($cm->id == "") continue;	// skip if not yet set
    $logger->debug("Adding committee member id " . $cm->id . " to policy view rule");
    foreach (array_merge(array($etd), $etd->pdfs, $etd->supplements) as $obj) {
      $obj->policy->view->condition->addUser($cm->id);
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
    $logger->debug("Record is not in draft status; removing policy draft rules");
    $etd->removePolicyRule("draft");		// remove draft policy on etd & all files, if it is there
  }
  
  if (!$opts->noact) {
    $hasChanged = false;
    foreach (array_merge(array($etd), $etd->pdfs, $etd->supplements) as $obj) {
      if ($obj->policy->hasChanged()) $hasChanged == true;
    }
    if ($hasChanged) {
      $result = $etd->save("cleaned xacml policy - advisor, committee, department on view rule; draft rule");
      if ($result)
	$logger->info("Saved changes to " . $etd->pid . " and associated files");
      else	// save failed
	$logger->err("Error saving changes to " . $etd->pid . " and associated files");

      // FIXME: if etdfiles changed but etd did not, might look like an error here when it is not
    } else {
      $logger->info("No changes to policies on " . $etd->pid . " or associated files");
    }
  }

}


