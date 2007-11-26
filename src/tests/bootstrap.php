<?

error_reporting(E_ALL);
ini_set("include_path", ini_get("include_path") . ":../lib:../lib/ZendFramework:../lib/fedora:../lib/xml-utilities:../app/:../app/modules/");

require_once('simpletest/unit_tester.php');
require_once('simpletest/reporter.php');

require('Zend/Loader.php');
Zend_Loader::registerAutoload();

Zend_Registry::set('fedora-config', new Zend_Config_Xml("../config/fedora.xml", "test"));

// required - zend complains without this
Zend_Session::start();

?>
