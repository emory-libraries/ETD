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


//Load Configuration
$env_config = new Zend_Config_Xml("../config/environment.xml", "environment");
Zend_Registry::set('env-config', $env_config);
$config = new Zend_Config_Xml("../config/config.xml", $env_config->mode);
Zend_Registry::set('fedora-config',
	  new Zend_Config_Xml("../config/fedora.xml", $env_config->mode));

//set default timezone
date_default_timezone_set($config->timezone);


// set up connection to solr for search & browse
require_once("solr.php");
$solr_config = new Zend_Config_Xml("../config/solr.xml", $env_config->mode);
$solr = new solr($solr_config->server, $solr_config->port);
$solr->addFacets(explode(',', $solr_config->facets)); 	// array of default facet terms
Zend_Registry::set('solr', $solr);


// set up access controls
$acl = new Zend_Acl();

//roles
$guest = new Zend_Acl_Role('guest');
$acl->addRole($guest);
$author = new Zend_Acl_Role('author');
$acl->addRole($author);
$committee = new Zend_Acl_Role('committee');
$acl->addRole($committee);
$admin = new Zend_Acl_Role('admin');
$acl->addRole($admin);

//resources
$acl->add(new Zend_Acl_Resource("etd"));
$acl->add(new Zend_Acl_Resource("published etd"), "etd");
$acl->add(new Zend_Acl_Resource("draft etd"), "etd");

//privileges
$acl->allow("guest", "published etd", "view metadata");

$acl->allow("author", "etd", array("view metadata", "view history", "view status",
				   "download", "edit metadata", "add file"));

$acl->allow("committee", "etd", array("view metadata", "view history", "view status", "download"));
$acl->allow("admin", "etd", array("view metadata", "view history", "view status", "download",
				  "edit history", "edit status"));

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


$auth = Zend_Auth::getInstance();
if ($auth->hasIdentity()) {
  $viewRenderer->view->current_user = user::find_by_username($auth->getIdentity());
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

