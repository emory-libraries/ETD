<?
/*
*  
*
*
*/

//Error Reporting
error_reporting(E_ALL|E_STRICT);
ini_set("display_errors", "on");

// ZendFramework, etc.
ini_set("include_path", ini_get("include_path") . ":../app/::../app/models:../app/modules/:../lib:../lib/ZendFramework:../lib/fedora:../lib/xml-utilities:/home/rsutton/public_html");
// NOTE: local models should come before fedora models so that correct
// xml template files will be found first

require("Zend/Loader.php");
Zend_Loader::registerAutoload();


require_once("api/FedoraConnection.php");

//Load Configuration
$env_config = new Zend_Config_Xml("../config/environment.xml", "environment");
Zend_Registry::set('env-config', $env_config);
$config = new Zend_Config_Xml("../config/config.xml", $env_config->mode);
Zend_Registry::set('config', $config);

$fedora_cfg = new Zend_Config_Xml("../config/fedora.xml", $env_config->mode);
Zend_Registry::set('fedora-config',
	  new Zend_Config_Xml("../config/fedora.xml", $env_config->mode));

// if there is a logged in user, retrieve their information from fedora so it is available everywhere
$auth = Zend_Auth::getInstance();
if ($auth->hasIdentity()) {
  $username = $auth->getIdentity();
  $password = strtoupper($username);	// because of the way fedora ldap config is hacked right now
  $fedora = new FedoraConnection($username, $password,
				 $fedora_cfg->server, $fedora_cfg->port);

} else {
  $fedora = new FedoraConnection($fedora_cfg->user, $fedora_cfg->password,
				 $fedora_cfg->server, $fedora_cfg->port);
}
Zend_Registry::set('fedora', $fedora);


//set default timezone
date_default_timezone_set($config->timezone);


// set up connection to solr for search & browse
require_once("solr.php");
$solr_config = new Zend_Config_Xml("../config/solr.xml", $env_config->mode);
$solr = new solr($solr_config->server, $solr_config->port);
$solr->addFacets(explode(',', $solr_config->facets)); 	// array of default facet terms
Zend_Registry::set('solr', $solr);


require_once("persis.php");

$persis_config = new Zend_Config_Xml("../config/persis.xml", $env_config->mode);
$persis = new persis($persis_config->url, $persis_config->username,
		     $persis_config->password, $persis_config->domain);
Zend_Registry::set('persis', $persis);


// set up access controls
require_once("xml_acl.php");
$acl = new Xml_Acl();
Zend_Registry::set('acl', $acl);



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
if (isset($username)) {
  // fedora connection  needs to be defined before instantiating user
  $viewRenderer->view->current_user =  user::find_by_username($username);
}





Zend_Controller_Action_HelperBroker::addPath('Emory/Controller/Action/Helper',
					     'Emory_Controller_Action_Helper');


//set internal error handler
$front->throwExceptions((boolean)$env_config->display_exception);

// define custom routes
//$routecfg = new Zend_Config_Xml("../config/routes.xml", $env_config->mode);
//$router = $front->getRouter();
//$router->addConfig($routecfg, "routes");

$front->dispatch();
?>

