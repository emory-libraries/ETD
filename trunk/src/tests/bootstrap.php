<?
// ZendFramework
ini_set("include_path", ini_get("include_path") . ":../../lib:../../app/:../../app/modules/");
require("Zend/Loader.php");

function __autoload($class) {
  Zend_Loader::loadClass($class);
}

//Load Configuration
$config = new Zend_Config_Xml("../../config/config.xml", "test");

//Create DB object
$db = Zend_Db::factory($config->database->adapter, $config->database->params->toArray());
Zend_Registry::set('db', $db);
Zend_Db_Table_Abstract::setDefaultAdapter($db);

//Store config values to the registry
Zend_Registry::set('authentication_type', $config->authentication->type);

Zend_Session::start();

?>
