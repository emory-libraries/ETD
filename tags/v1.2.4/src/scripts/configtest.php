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


$tester = new Emory_Config_Tester();
$tester->log_level($opts->verbose);

// test environment
$tester->load_xmlconfig($config_dir . "environment.xml", "environment");
$tester->check_notblank(array("mode", "debug", "display_exception"));
$tester->check_oneof("mode", array('development', 'production', 'testing'));
$tester->check_pattern("debug", "/^[01]$/");
$tester->check_recommended("display_exception", 0);
$tester->ok();

// all other config files will be loaded in mode specified in environment
$env_mode = $tester->config->mode;
if ($env_mode) {
  $tester->debug("Testing all other configurations with mode = " . $env_mode);  
} else {
  $tester->err("Cannot test other configurations without environment mode; stopping");
  return;
}


// ldap
$tester->load_xmlconfig($config_dir . "ldap.xml", $env_mode);
// configuration fields have to be nested under the server field
$tester->check_notblank(array("server" =>
			      array("host", "port", "baseDn", "username", "password",
				    "accountCanonicalForm", "bindRequiresDn", "useSsl")
			      )
			);
$tester->ok();

// fedora
$tester->load_xmlconfig($config_dir . "fedora.xml", $env_mode);
$tester->check_notblank(array("server", "port", "user", "password",
			      "protocol", "resourceindex",
			      "maintenance_account" => array("user", "password")
			      )
			);
$tester->ok();

// initialize test connection to fedora
require_once("api/FedoraConnection.php");
try {
  $fedoraConnection = new FedoraConnection($tester->config->user,
					   $tester->config->password,
					   $tester->config->server,
					   $tester->config->port,
					   $tester->config->protocol,
					   $tester->config->resourceindex);
  Zend_Registry::set("fedora", $fedoraConnection);
} catch (FedoraNotAvailable $e) {
  $tester->logger->err("Fedora is not available as configured: "  .$e->getMessage());
} 
$tester->ok();

// ESD
$tester->load_xmlconfig($config_dir . "esd.xml", $env_mode);
$tester->check_notblank(array("adapter",
			      "params" => array("username", "password",
						"host", "port", "dbname")
			      )
			);
// fixme: try to initialize esd & check connection? how to test?
$tester->ok();


// persis
$tester->load_xmlconfig($config_dir . "persis.xml", $env_mode);
$tester->check_notblank(array("url", "username", "password", "domain_id"));
// try to initialize persis & check connection
try {
  new Emory_Service_Persis($tester->config);
} catch (PersisServiceUnavailable $e) {
  $tester->err("Persis service is unavailable or misconfigured");
}
$tester->ok();


// solr
$tester->load_xmlconfig($config_dir . "solr.xml", $env_mode);
$tester->check_notblank(array("server", "path", "port"));
$tester->plural("facet");

// initialize solr and run a basic search to test setup
require_once("Etd/Service/Solr.php");
$solr = new Etd_Service_Solr($tester->config->server,
			     $tester->config->port,
			     $tester->config->path);
$solr->addFacets($tester->config->facet->toArray());
try {
  $result = $solr->query("*:*", 0, 0);
} catch (SolrException $e) {
  $tester->err("Could not connect to Solr: " . $e->getMessage());
}
// if basic connection test passed, check the facet fields
if (isset($result)) {
  foreach ($tester->config->facet->toArray() as $facet) {
    $rsp = $solr->browse($facet);
    if (! isset($rsp->facets->{$facet}))
      $tester->warn("No results for solr facet '$facet', is this field valid?");
  }
}
$tester->ok();

// proquest
$tester->load_xmlconfig($config_dir . "proquest.xml", $env_mode);
$tester->check_notblank(array("ftp" => array("server", "user", "password"),
			      "abstract_max_words", "email")
			);
$tester->email("email");
$tester->ok();

// statistics
$tester->load_xmlconfig($config_dir . "statistics.xml", $env_mode);
$tester->check_notblank(array("adapter", "params" => array("dbname")));
$tester->check_file("params/dbname", "rw");
$tester->ok();


// test main config
$tester->load_xmlconfig($config_dir . "config.xml", $env_mode);
$tester->check_notblank(array("session_name", "tmpdir", "logfile", "pdftohtml",
			      "supported_browsers", "useAndReproduction",
			      "honors_collection", "programs_pid",
			      "email" => array("test", "etd/address", "etd/name"),
			      "contact" => array("email")
			      )
			);
$tester->email("email/etd/address");
$tester->email("email/test");
$tester->email("contact/email");
// fixme: check all file read/write as apache user ? (HOW?)
$tester->check_file("tmpdir", "drw");
$tester->check_file("logfile", "rwf");
$tester->check_file("pdftohtml", "fx");
$tester->plural("superusers/user");
$tester->plural("techsupport/user");
$tester->plural("honors_admin/user");

// if fedoraConnection was initialized, test that collection pids are valid
if ($fedoraConnection) {
  
    require_once("models/foxml.php");
    foreach (array("honors collection" => $tester->config->honors_collection,
		   "program hierarchy" => $tester->config->programs_pid)
	     as $label => $pid) {
      if (! trim($pid)) continue;
        // test that configured objects are actually in fedora
        try {
            $obj = new foxml($pid);
        } catch (FedoraObjectNotFound $e) {
            $tester->err("Fedora Object $label '" . $pid .
			 "' not found in configured Fedora instance");
        } catch (Exception $e) {        // could be not authorized, access denied, etc.
            $tester->err("Could not access Fedora object $label '" . $pid ."' in Fedora:" .
                get_class($e) . " " . $e->getMessage());
        }
    }
} else {
    $tester->warn("Fedora connection not available, can not check that configured pids exist in Fedora");
}

$tester->ok();