<?php
/**
 * common configuration / path settings used by multiple scripts
 * @category Etd
 * @package Etd_Scripts
 */


// set include path to include local libraries, ZendFramework, etc.
$srcdir = dirname(__FILE__) . '/..';
ini_set("include_path", "$srcdir/app/:$srcdir/config:$srcdir/app/models:" .
    "$srcdir/app/modules/:$srcdir/lib:$srcdir/lib/fedora:$srcdir/lib/xml-utilities:$srcdir/lib/Etd/Util:" .
    "$srcdir/js/:" . ini_get("include_path"));

require("Zend/Loader/Autoloader.php");
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->setFallbackAutoloader(true);

require_once("models/etd.php");
require_once("models/datastreams/etdfile.php");
require_once("models/etd_notifier.php");
require_once("api/FedoraConnection.php");

$env_config = new Zend_Config_Xml("$srcdir/config/environment.xml", "environment");
Zend_Registry::set('env-config', $env_config);
$config = new Zend_Config_Xml("$srcdir/config/config.xml", $env_config->mode);
Zend_Registry::set('config', $config);
$fedora_cfg = new Zend_Config_Xml("$srcdir/config/fedora.xml", $env_config->mode);
Zend_Registry::set('fedora-config', $fedora_cfg);

// needs to connect to Fedora using maintenance account to view and modify unpublished records
try {
    $fedora_opts = $fedora_cfg->toArray();
    // use default fedora config opts but with maintenance account credentials
    $fedora_opts["username"] = $fedora_cfg->maintenance_account->username;
    $fedora_opts["password"] = $fedora_cfg->maintenance_account->password;
    $fedora = new FedoraConnection($fedora_opts);
} catch (FedoraNotAvailable $e) {
  trigger_error("Fedora is not available-- cannot proceed", E_USER_ERROR);
  return;
}
Zend_Registry::set('fedora', $fedora);
$config_dir = "../config/";

// set up connection to solr to find records with expiring embargoes
require_once("Etd/Service/Solr.php");
$solr_config = new Zend_Config_Xml($config_dir . "solr.xml", $env_config->mode);
$solr = new Etd_Service_Solr($solr_config->server, $solr_config->port, $solr_config->path);
$solr->addFacets($solr_config->facet->toArray());
//$solr->setFacetLimit($solr_config->facet_limit);
Zend_Registry::set('solr', $solr);

// ESD needed to get email addresses for publication notification
// create DB object for access to Emory Shared Data
$esdconfig = new Zend_Config_Xml($config_dir . 'esd.xml', $env_config->mode);
Zend_Registry::set('esd-config', $esdconfig);
$esd = Zend_Db::factory($esdconfig);
Zend_Registry::set('esd-db', $esd);
Zend_Db_Table_Abstract::setDefaultAdapter($esd);

// create DB object for access to etd MYSQL database
$etdDBconfig = new Zend_Config_Xml($config_dir . 'etd-db.xml', $env_config->mode);
Zend_Registry::set('etd-db-config', $etdDBconfig);
$etdDB = Zend_Db::factory($etdDBconfig);
Zend_Registry::set('etd-db', $etdDB);


// sqlite db for statistics data
$stat_config = new Zend_Config_Xml($config_dir . 'statistics.xml', $env_config->mode);
$db = Zend_Db::factory($stat_config);
Zend_Registry::set('stat-db', $db);

Zend_Registry::set('persis-config',
    new Zend_Config_Xml($config_dir . "persis.xml", $env_config->mode));

$schools_config = new SchoolsConfig($config_dir . "schools.xml");
Zend_Registry::set('schools-config', $schools_config);

// common getopt configurations that used by most scripts
$common_getopts = array('verbose|v=s'  => 'Output level/verbosity; one of error, warn, notice, info, debug (default: error)',
      'noact|n'      => "Test/simulate - don't actually do anything (no actions)");




// common logging setup function
function setup_logging($level) {
  // output logging
  $writer = new Zend_Log_Writer_Stream("php://output");
  // minimal output format - don't display timestamp or numeric priority
  $format = '%priorityName%: %message%' . PHP_EOL;
  $formatter = new Zend_Log_Formatter_Simple($format);
  $writer->setFormatter($formatter);
  $logger = new Zend_Log($writer);

  // set level of output to be displayed based on command line parameter
  switch ($level) {
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
  return $logger;
}

// build an array of pids for ALL etd-related objects (etd, file, author, etc)
function all_etd_pids() {
    global $fedora, $logger, $config, $schools_config;
    // find all ETD objects by content models
    $etd_pids = $fedora->risearch->findByCModel($config->contentModels->etd);
    $logger->notice("Found " . count($etd_pids) . " ETD objects");
    $etdfile_pids = $fedora->risearch->findByCModel($config->contentModels->etdfile);
    $logger->notice("Found " . count($etdfile_pids) . " EtdFile objects");
    $author_pids = $fedora->risearch->findByCModel($config->contentModels->author);
    $logger->notice("Found " . count($author_pids) . " AuthorInfo objects");

    //Get all school collection pids from the schools config file
    $school_pids = array();
    foreach ($schools_config as $school){
    $school_pids[] = $school->fedora_collection;
}

    // combine all pids into a single array
    $all_pids = array_merge($etd_pids, $etdfile_pids, $author_pids,
        // include pids for special objects
        $school_pids, array($config->programs_pid)
    );

    return $all_pids;
}
