<?
/*
*  
*
*
*/

// ZendFramework, etc.
ini_set("include_path", "../app/:../config:../app/models:../app/modules/:../lib:../lib/fedora:../lib/xml-utilities:js/:" . ini_get("include_path")); // use local copies first (e.g., Zend)
// NOTE: local models should come before fedora models so that correct
// xml template files will be found first

require("Zend/Loader.php");
Zend_Loader::registerAutoload();


require_once("api/FedoraConnection.php");

$config_dir = "../config/";
Zend_Registry::set("config-dir", $config_dir);

//Load Configuration
$env_config = new Zend_Config_Xml($config_dir . "environment.xml", "environment");
Zend_Registry::set('env-config', $env_config);
Zend_Registry::set('environment', $env_config->mode);
$config = new Zend_Config_Xml($config_dir . "config.xml", $env_config->mode);
Zend_Registry::set('config', $config);
Zend_Registry::set('debug', (boolean)$env_config->debug);

Zend_Registry::set('persis-config',
	  new Zend_Config_Xml($config_dir . "persis.xml", $env_config->mode));
Zend_Registry::set('proquest-config',
	  new Zend_Config_Xml($config_dir . "proquest.xml", $env_config->mode));

$fedora_cfg = new Zend_Config_Xml($config_dir . "fedora.xml", $env_config->mode);
Zend_Registry::set('fedora-config', $fedora_cfg);

try {
  // if there is a logged in user, use their account to connect to Fedora
  $auth = Zend_Auth::getInstance();
  if ($auth->hasIdentity() &&
      /* In some rare cases, Ldap/ESD fails and user is logged in but
         $current_user is not the right kind of object, which means
         access to Fedora will *not* work. If that happens, log them out.  */
      ($current_user = $auth->getIdentity()) instanceof esdPerson) {
      $fedora = new FedoraConnection($current_user->netid,
				     $current_user->getPassword(),
				     $fedora_cfg->server, $fedora_cfg->port,
				     $fedora_cfg->protocol, $fedora_cfg->resourceindex);
      
  } else {
    // clear identity in case user is partially logged in
    $auth->clearIdentity();
    $fedora = new FedoraConnection($fedora_cfg->user, $fedora_cfg->password,
				   $fedora_cfg->server, $fedora_cfg->port,
				   $fedora_cfg->protocol, $fedora_cfg->resourceindex);
  }
  Zend_Registry::set('fedora', $fedora);
} catch (FedoraNotAvailable $e) {

}

// create DB object for access to Emory Shared Data
$esdconfig = new Zend_Config_Xml($config_dir . 'esd.xml', $env_config->mode);
$esd = Zend_Db::factory($esdconfig);
Zend_Registry::set('esd-db', $esd);
Zend_Db_Table_Abstract::setDefaultAdapter($esd);


//set default timezone
date_default_timezone_set($config->timezone);

// keep separate sessions for different ZF projects on the same server
Zend_Session::setOptions(array("name" => $config->session_name));


// set up connection to solr for search & browse
$solr_config = new Zend_Config_Xml($config_dir . "solr.xml", $env_config->mode);
require_once("Etd/Service/Solr.php");
$newsolr = new Etd_Service_Solr($solr_config->server, $solr_config->port, $solr_config->path);
$newsolr->addFacets($solr_config->facet->toArray());
Zend_Registry::set('solr', $newsolr);

// sqlite db for statistics data
$db_config = new Zend_Config_Xml($config_dir . 'statistics.xml', $env_config->mode);
$db = Zend_Db::factory($db_config);
Zend_Registry::set('stat-db', $db);	


//Setup Controller
$front = Zend_Controller_Front::getInstance();

// Set the default controller directory:
$front->setControllerDirectory(array("default" => "../app/controllers", "emory" => "../lib/Emory"));
$front->addModuleDirectory("../app/modules");

// Define Layout
Zend_Layout::startMvc(array("layout" => "site",		// default layout
			    "layoutPath" => "../app/layouts/scripts",	// layout base path
			    ));
//Zend_Layout::setup(array('path' => '../app/layouts',));
//Zend_Layout::setDefaultLayoutName('site');


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


// setup logging
// FIXME: could also write to db -- useful?
$writer = new Zend_Log_Writer_Stream($config->logfile);
// default field names and order, but adding username
$formatter = new Zend_Log_Formatter_Xml('logEntry',
					array(
					      "timestamp" => "timestamp",
					      "message" => "message",
					      "priorityName" => "priorityName",
					      "priority" => "priority",
					      "username" => "username",
					      )
					);
$writer->setFormatter($formatter);
$logger = new Zend_Log($writer);
if (isset($current_user)) $username = $current_user->netid;
else $username = "guest";
// store username with every entry
$logger->setEventItem('username', $username);
// $filter = new Zend_Log_Filter_Priority(priority here...);
// $logger->addFilter($filter);
Zend_Registry::set('logger', $logger);



Zend_Controller_Action_HelperBroker::addPath('Emory/Controller/Action/Helper',
					     'Emory_Controller_Action_Helper');

//set internal error handler
$front->throwExceptions((boolean)$env_config->display_exception);

// define custom routes
//$routecfg = new Zend_Config_Xml("../config/routes.xml", $env_config->mode);
//$router = $front->getRouter();
//$router->addConfig($routecfg, "routes");


$front->dispatch();


