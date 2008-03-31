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


// ESD needed to get email addresses for publication notification
// create DB object for access to Emory Shared Data
$esdconfig = new Zend_Config_Xml('../config/esd.xml', $env_config->mode);
$esd = Zend_Db::factory($esdconfig);
Zend_Registry::set('esd-db', $esd);
Zend_Db_Table_Abstract::setDefaultAdapter($esd);



$opts = new Zend_Console_Getopt(
  array(
    'verbose|v'	       => 'Verbose output',
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

// find ETDs with embargoes that will expire in 60 days
$expiration = date("Ymd", strtotime("+60 days"));	// 60 days from now
if ($opts->verbose) print "Searching for records with embargo expiration of $expiration\n";
$etds = etd::findExpiringEmbargoes($expiration); 

if ($opts->verbose) print "Found " . count($etds) . " record" .
	  ((count($etds) != 1) ? "s" : "") . "\n";
if (!$opts->noact) {
  foreach ($etds as $etd) {
    $notify = new etd_notifier($etd);
    // send email about embargo expiration
    $notify->embargo_expiration();

    // add an administrative note that embargo expiration notice has been sent,
    // and add notification event to record history log
    $etd->embargo_expiration_notice();
    
    $etd->save("sent embargo expiration 60-day notice");
  }
}


// results? anything else?



?>
