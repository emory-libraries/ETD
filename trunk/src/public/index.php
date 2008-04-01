<?
/*
*  
*
*
*/

// ZendFramework, etc.
ini_set("include_path", "../app/:../config:../app/models:../app/modules/:../lib:../lib/ZendFramework:../lib/fedora:../lib/xml-utilities:js/:" . ini_get("include_path")); // use local copies first (e.g., Zend)
// NOTE: local models should come before fedora models so that correct
// xml template files will be found first

require("Zend/Loader.php");
Zend_Loader::registerAutoload();


require_once("api/FedoraConnection.php");

//Load Configuration
$env_config = new Zend_Config_Xml("../config/environment.xml", "environment");
Zend_Registry::set('env-config', $env_config);
Zend_Registry::set('environment', $env_config->mode);
$config = new Zend_Config_Xml("../config/config.xml", $env_config->mode);
Zend_Registry::set('config', $config);

Zend_Registry::set('debug', (boolean)$env_config->debug);

$fedora_cfg = new Zend_Config_Xml("../config/fedora.xml", $env_config->mode);
Zend_Registry::set('fedora-config',
	  new Zend_Config_Xml("../config/fedora.xml", $env_config->mode));

try {
  // if there is a logged in user, retrieve their information from fedora so it is available everywhere
  $auth = Zend_Auth::getInstance();
  if ($auth->hasIdentity()) {
    $current_user = $auth->getIdentity();
    $password = strtoupper($current_user->netid);	// because of the way fedora ldap config is hacked right now
    $fedora = new FedoraConnection($current_user->netid,
				   $current_user->getPassword(),
				   $fedora_cfg->server, $fedora_cfg->port,
				   $fedora_cfg->protocol, $fedora_cfg->resourceindex);
    
  } else {
    $fedora = new FedoraConnection($fedora_cfg->user, $fedora_cfg->password,
				   $fedora_cfg->server, $fedora_cfg->port,
				   $fedora_cfg->protocol, $fedora_cfg->resourceindex);
  }
  Zend_Registry::set('fedora', $fedora);
} catch (FedoraNotAvailable $e) {

}

// create DB object for access to Emory Shared Data
$esdconfig = new Zend_Config_Xml('../config/esd.xml', $env_config->mode);
$esd = Zend_Db::factory($esdconfig);
Zend_Registry::set('esd-db', $esd);
Zend_Db_Table_Abstract::setDefaultAdapter($esd);


//set default timezone
date_default_timezone_set($config->timezone);


// set up connection to solr for search & browse
require_once("solr.php");
$solr_config = new Zend_Config_Xml("../config/solr.xml", $env_config->mode);
$solr = new solr($solr_config->server, $solr_config->port);
$solr->addFacets(explode(',', $solr_config->facets)); 	// array of default facet terms
Zend_Registry::set('solr', $solr);

// sqlite db for statistics data
$config = new Zend_Config_Xml('../config/statistics.xml', $env_config->mode);
$db = Zend_Db::factory($config);
Zend_Registry::set('stat-db', $db);	


//Setup Controller
$front = Zend_Controller_Front::getInstance();

// Set the default controller directory:
$front->setControllerDirectory(array("default" => "../app/controllers", "emory" => "../lib/ZendFramework/Emory"));
$front->addModuleDirectory("../app/modules");

// Define Layout
Xend_Layout::setup(array('path' => '../app/layouts',));
Xend_Layout::setDefaultLayoutName('site');


// add new helper path to view
$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');
$viewRenderer->initView();
$viewRenderer->view->addHelperPath('Emory/View/Helper', 'Emory_View_Helper');
if (isset($current_user)) {
  Zend_Registry::set('current_user', $current_user);
  // fedora connection  needs to be defined before instantiating user
  //  $viewRenderer->view->current_user = $current_user;
}


// set up access controls
require_once("xml_acl.php");
$acl = new Xml_Acl();
Zend_Registry::set('acl', $acl);



Zend_Controller_Action_HelperBroker::addPath('Emory/Controller/Action/Helper',
					     'Emory_Controller_Action_Helper');

//set internal error handler
$front->throwExceptions((boolean)$env_config->display_exception);

// define custom routes
//$routecfg = new Zend_Config_Xml("../config/routes.xml", $env_config->mode);
//$router = $front->getRouter();
//$router->addConfig($routecfg, "routes");


$front->dispatch();


