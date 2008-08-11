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
require_once("Etd/Service/Solr.php");
$solr_config = new Zend_Config_Xml("../config/solr.xml", $env_config->mode);
$solr = new Etd_Service_Solr($solr_config->server, $solr_config->port, $solr_config->path);
$solr->addFacets($solr_config->facet->toArray());
$solr->setFacetLimit($solr_config->facet_limit);
Zend_Registry::set('solr', $solr);


// ESD needed to get email addresses for publication notification
// create DB object for access to Emory Shared Data
$esdconfig = new Zend_Config_Xml('../config/esd.xml', $env_config->mode);
$esd = Zend_Db::factory($esdconfig);
Zend_Registry::set('esd-db', $esd);
Zend_Db_Table_Abstract::setDefaultAdapter($esd);



$opts = new Zend_Console_Getopt(
  array(
    'verbose|v=s'      => 'Output level/verbosity; one of error, warn, notice, info, debug (default: error)',
    'noact|n'	       => "Test/simulate - don't actually do anything (no actions)",
    )
  );

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
";
//  FIXME: do we need more information here?


try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}

// output logging
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
$logger->debug("Searching for records with embargo expiration of $expiration or before and notice not sent");
$etdset = etd::findExpiringEmbargoes($expiration); 
// FIXME: need to iterate through all records; handle through EtdSet object? 


$logger->info("Found " . $etdset->numFound . " record" . ($etdset->numFound != 1 ? "s" : ""));
foreach ($etdset->etds as $etd) {
  $logger->debug("Processing " . $etd->pid);

  // double-check that embargo notice has not been sent according to MODS record
  // (this should only happen if Solr index does not get updated -- should be fixed by now?)
  if (isset($etd->mods->embargo_notice) && strstr($etd->mods->embargo_notice, "sent")) {
    $logger->warn("Notice has already been sent for " . $etd->pid . "; skipping");
    continue;
  }
  
  if (!$opts->noact) {
    $notify = new etd_notifier($etd);
    // send email about embargo expiration
    $notify->embargo_expiration();
    
    // add an administrative note that embargo expiration notice has been sent,
    // and add notification event to record history log
    $etd->embargo_expiration_notice();
    
    $result = $etd->save("sent embargo expiration 60-day notice");
    if ($result) {
      $logger->debug("Successfully saved " . $etd->pid . " at $result");
    } else {
      $logger->err("Could not save embargo expiration notice sent to record history"); 
    }
  }
}


/* to do :
      send a 7-day warning to etd-admin listserv
      send a day-of notice to author & committee
*/


// results? anything else?



?>
