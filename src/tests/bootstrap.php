<?

error_reporting(E_ALL);
ini_set("include_path", ini_get("include_path") . ":../app/:../app/modules/:../lib:../lib/ZendFramework:../lib/fedora:../lib/xml-utilities");

require_once('simpletest/unit_tester.php');
require_once('simpletest/reporter.php');

require('Zend/Loader.php');
Zend_Loader::registerAutoload();
require_once("api/FedoraConnection.php");

$fedora_cfg = new Zend_Config_Xml("../config/fedora.xml", "test");
Zend_Registry::set('fedora-config', $fedora_cfg);

$fedora = new FedoraConnection($fedora_cfg->user, $fedora_cfg->password,
			       $fedora_cfg->server, $fedora_cfg->port);
Zend_Registry::set('fedora', $fedora);


// required - zend complains without this
Zend_Session::start();

?>
