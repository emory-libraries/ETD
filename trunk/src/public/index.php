<?
/*
*  
*
*
*/

//Error Reporting
error_reporting(E_ALL|E_STRICT);
ini_set("display_errors", "on");

// ZendFramework
ini_set("include_path", ini_get("include_path") . ":../lib:../lib/ZendFramework:../lib/fedora:../lib/xml-utilities:../app/:../app/modules/");
require("Zend/Loader.php");
Zend_Loader::loadClass("Zend_Controller_Front");

function __autoload($class) {
  Zend_Loader::loadClass($class);
}

//Load Configuration
$env_config	= new Zend_Config_Xml("../config/environment.xml", "environment");
$config 	= new Zend_Config_Xml("../config/config.xml", $env_config->mode);

Zend_Registry::set('fedora-config',
		   new Zend_Config_Xml("../config/fedora.xml", $env_config->mode));

//set default timezone
date_default_timezone_set($config->timezone);

//Setup Controller
$front = Zend_Controller_Front::getInstance();

// Set the default controller directory:
$front->setControllerDirectory(array("default" => "../app/controllers"));
$front->addModuleDirectory("../app/modules");

// Define Layout
Xend_Layout::setup(array('path' => '../app/layouts',));
Xend_Layout::setDefaultLayoutName('site');

//set internal error handler
$front->throwExceptions($env_config->display_exception);

$front->dispatch();
?>

