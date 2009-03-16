#!/usr/bin/php -q
<?php

// ZendFramework, etc.
ini_set("include_path", "../app/:../config:../app/models:../app/modules/:../lib:../lib/fedora:../lib/xml-utilities:js/:/home/rsutton/public_html:" . ini_get("include_path")); 

$config_dir = "../config/";

require("Zend/Loader.php");
Zend_Loader::registerAutoload();



// common getopt configurations that used by most scripts
$getopts = array('verbose|v=s'  => 'Output level/verbosity; one of error, warn, notice, info, debug (default: error)');

$opts = new Zend_Console_Getopt($getopts);

// extended usage information - based on option list above, but with explanation/examples
$scriptname = basename($_SERVER{"SCRIPT_NAME"});
$usage = $opts->getUsageMessage() . "
 $scriptname checks ETD site configuration
";


try {
  $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
  echo $usage;
  exit;
}

$logger = setup_logging($opts->verbose);


$env = load_config_or_err($config_dir . "environment.xml", "environment");

// test environment
if ($env) {
  $logger->info("** Checking environment.xml");

  $fields = array("mode", "debug", "display_exception");
  foreach ($fields as $field) {
    isset_or_err($env->{$field}, "Environment $field");
  }

  // redundant errors if not set..
  
  // mode
  if (!in_array($env->mode, array('development', 'production', 'testing')))
    $logger->err("Environment mode '" . $env->mode . "' is not valid");
  // debug
  if (!preg_match("/^[01]$/", $env->debug))
    $logger->warn("Environment debug should be 1 or 0; current value: " . $env->debug );
    
  // display exception
  if ($env->display_exception != 0)
    $logger->notice("Recommended setting for environment 'display_exception' is 0");

  // how to show if it is OK ?
  // extend logger or create test class - counts warnings/errors
}

if (isset($env->mode)) {
  $logger->debug("Testing all other configurations with mode = " . $env->mode);  
} else {
  $logger->err("Cannot test other configurations without environment mode; stopping");
  return;
} 


$ldap = load_config_or_err($config_dir . "ldap.xml", $env->mode);
if ($ldap) {
  $logger->info("** Checking ldap.xml");
  $fields = array("host", "port", "baseDn", "username", "password",
		  "accountCanonicalForm", "bindRequiresDn", "useSsl");
  foreach ($fields as $field) {
    isset_or_err($ldap->server->{$field}, "Ldap $field");
  }
}

$fedora = load_config_or_err($config_dir . "fedora.xml", $env->mode);
if ($fedora) {
  $logger->info("** Checking fedora.xml");
  $fields = array("server", "port", "user", "password",
		  "protocol", "resourceindex");
  foreach ($fields as $field) {
    isset_or_err($fedora->{$field}, "fedora $field");
  }

  // maintenance account info is nested
  foreach (array("user", "password") as $field) {
    isset_or_err($fedora->maintenance_account->{$field},
		 "fedora maintenance account $field");
  }

  require_once("api/FedoraConnection.php");
  try {
    $fedoraConnection = new FedoraConnection($fedora->user,
				 $fedora->password,
				 $fedora->server, $fedora->port,
				 $fedora->protocol, $fedora->resourceindex);
  } catch (FedoraNotAvailable $e) {
    $logger->err("Fedora is not available as configured: " . $e->getMessage());
  } 

}

$esd = load_config_or_err($config_dir . "esd.xml", $env->mode);
if ($esd) {
  $logger->info("** Checking esd.xml");
  isset_or_err($esd->adapter, "ESD db adapter");
  $fields = array("username", "password", "host", "port", "dbname");
  foreach ($fields as $field) {
    isset_or_err($esd->params->{$field}, "ESD param $field");
  }
  // fixme: try to initialize esd & check connection? how to test?
}

$persis = load_config_or_err($config_dir . "persis.xml", $env->mode);
if ($persis) {
  $logger->info("** Checking persis.xml");
  $fields = array("url", "username", "password", "domain_id");
  foreach ($fields as $field) {
    isset_or_err($persis->{$field}, "persis $field");
  }
  // try to initialize persis & check connection
  try {
    $persis_svc = new Emory_Service_Persis($persis);
  } catch (PersisServiceUnavailable $e) {
   $logger->err("Persis service is unavailable or misconfigured");
 }
}

$solr = load_config_or_err($config_dir . "solr.xml", $env->mode);
if ($solr) {
  $logger->info("** Checking solr.xml");
  $fields = array("server", "path", "port");
  foreach ($fields as $field) {
    isset_or_err($solr->{$field}, "solr $field");
  }
  plural_or_err($solr->facet, "solr facets");
  
  // initialize solr and run a basic search to test setup
  require_once("Etd/Service/Solr.php");
  $solr_service = new Etd_Service_Solr($solr->server, $solr->port, $solr->path);
  $solr_service->addFacets($solr->facet->toArray());

  try {
    $result = $solr_service->query("*:*", 0, 0);
  } catch (SolrException $e) {
    $logger->err("Could not connect to Solr: " . $e->getMessage());
  }

  // if basic connection test passed, check the facet fields
  if (isset($result)) {
    foreach ($solr->facet->toArray() as $facet) {
      $rsp = $solr_service->browse($facet);
      if (! isset($rsp->facets->{$facet}))
	$logger->warn("No results for solr facet '$facet', is this field valid?");
    }
  }

}

$pq = load_config_or_err($config_dir . "proquest.xml", $env->mode);
if ($pq) {
  $logger->info("** Checking proquest.xml");
  // ftp settings
  $fields = array("server", "user", "password");
  foreach ($fields as $field) {
    isset_or_err($pq->ftp->{$field}, "proquest $field");
  }
  isset_or_err($pq->abstract_max_words, "proquest abstract_max_words");

  email_or_warn($pq->email, "proquest email");
}

$stat = load_config_or_err($config_dir . "statistics.xml", $env->mode);
if ($stat) {
  $logger->info("** Checking statistics.xml");
  isset_or_err($stat->adapter, "statistics db adapter");
  isset_or_err($stat->params->dbname, "statistics dbname");

  if ($stat->params->dbname) {
    if (!file_exists($stat->params->dbname))
      $logger->err("statistics db '" . $stat->params->dbname . "' does not exist");
    if (!is_readable($stat->params->dbname))
      $logger->warn("statistics db '" . $stat->params->dbname . "' is not readable");
    if (!is_writable($stat->params->dbname))
      $logger->warn("statistics db '" . $stat->params->dbname . "' is not writable");
  }
}



// test main config

$config = load_config_or_err($config_dir . "config.xml", $env->mode);
if ($config) {
  $logger->info("** Checking config.xml");

  $fields = array("session_name", "tmpdir", "logfile", "pdftohtml",
		  "supported_browsers", "useAndReproduction",
		  "honors_collection", "programs_pid");
  foreach ($fields as $field) {
    isset_or_err($config->{$field}, "Config $field");
  }

  email_or_warn($config->email->etd->address, "config etd-admin email");
  email_or_warn($config->email->test, "config test email");
  email_or_warn($config->contact->email, "config contact email");

  
  // fixme: check all  read/write as apache user ?
  
  if (!is_dir($config->tmpdir))
    $logger->err("tmpdir '" . $config->tmpdir . "' is not a directory");
  if (!is_writable($config->tmpdir)) // only warn - may run as different user
    $logger->warn("tmpdir '" . $config->tmpdir . "' is not writable");

  if (!is_file($config->logfile))
        $logger->err("logfile '" . $config->logfile . "' is not a file");
  if (!is_readable($config->logfile))
    $logger->warn("logfile '" . $config->logfile . "' is not readable");
  if (!is_writable($config->logfile))
    $logger->warn("logfile '" . $config->logfile . "' is not writable");

  if (!file_exists($config->pdftohtml))
        $logger->err("pdftohtml '" . $config->pdftohtml . "' is not a file");
  if (!is_executable($config->pdftohtml))
    $logger->err("pdftohtml '" . $config->pdftohtml . "' is not executable");


  // user lists *MUST* be multiple
  plural_or_err($config->superusers->user, "config superusers");
  plural_or_err($config->techsupport->user, "config techsupport");
  plural_or_err($config->honors_admin->user, "config honors_admin");

  // test that fedora pids are available in configured environment 
  if (isset($fedoraConnection)) {
    if ($config->honors_collection) {
      try {
	$fedoraConnection->getObjectProfile($config->honors_collection);
      } catch (FedoraObjectNotFound $e) {
	$logger->err("honors collection object not found in Fedora (pid = " .
		     $config->honors_collection . ")");
      }
    }

    if ($config->programs_pid) {
      try {
	$fedoraConnection->getObjectProfile($config->programs_pid);
      } catch (FedoraObjectNotFound $e) {
	$logger->err("programs object not found in Fedora (pid = " .
		     $config->programs_pid . ")");
      }
    }
    
  }
}


function load_config_or_err($path, $mode) {
  global $logger;
  try {
    return new Zend_Config_Xml($path, $mode);
  } catch (Zend_Config_Exception $e) {
    $logger->err("Could not load $path in mode $mode");
    $logger->debug($e->getMessage());
  }
}


// error if var is not set or if it is blank
function isset_or_err($var, $name) {
  global $logger;
  if (! isset($var)) $logger->err($name . " is not set");
  elseif ($var == "") $logger->err($name . " is blank");

}

function plural_or_err($field, $name) {
  global $logger;
  if (!is_object($field))   // if not an object, get a fatal error on the toArray call
    $logger->err("$name - must be multiple (cannot convert to array)");
  elseif ( !is_array($field->toArray())) {
    $logger->err("$name - cannot convert to array");
  }
}
  
function email_or_warn($field, $name) {
  global $logger;
  if (!preg_match("/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i", $field))
    $logger->warn("$name is not a valid email: " . $field);
}


// FIXME: copied from bootstrap - how to share?
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
