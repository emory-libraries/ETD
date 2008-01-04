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

//set internal error handler
$front->throwExceptions((boolean)$env_config->display_exception);

// define custom routes
//$routecfg = new Zend_Config_Xml("../config/routes.xml", $env_config->mode);
//$router = $front->getRouter();
//$router->addConfig($routecfg, "routes");

$front->dispatch();
?>

