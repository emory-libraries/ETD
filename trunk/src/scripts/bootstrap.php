<?php

  // common configuration / path settings used by multiple scripts


// ZendFramework, etc.
ini_set("include_path", "../app/:../config:../app/models:../app/modules/:../lib:../lib/fedora:../lib/xml-utilities:js/:/home/rsutton/public_html:" . ini_get("include_path")); 

require("Zend/Loader.php");
Zend_Loader::registerAutoload();

require_once("models/etd.php");
require_once("models/etdfile.php");
require_once("models/etd_notifier.php");
require_once("api/FedoraConnection.php");

$env_config = new Zend_Config_Xml("../config/environment.xml", "environment");
Zend_Registry::set('env-config', $env_config);
$config = new Zend_Config_Xml("../config/config.xml", $env_config->mode);
Zend_Registry::set('config', $config);
$fedora_cfg = new Zend_Config_Xml("../config/fedora.xml", $env_config->mode);

// needs to connect to Fedora using maintenance account to view and modify unpublished records
try {
  $fedora = new FedoraConnection($fedora_cfg->maintenance_account->user,
				 $fedora_cfg->maintenance_account->password,
				 $fedora_cfg->server, $fedora_cfg->port,
				 $fedora_cfg->protocol, $fedora_cfg->resourceindex);
} catch (FedoraNotAvailable $e) {
  trigger_error("Fedora is not available-- cannot proceed", E_USER_ERROR);
  return;
} 
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

// sqlite db for statistics data
$config = new Zend_Config_Xml('../config/statistics.xml', $env_config->mode);
$db = Zend_Db::factory($config);
Zend_Registry::set('stat-db', $db);	

?>