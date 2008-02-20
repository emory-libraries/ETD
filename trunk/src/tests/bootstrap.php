<?

error_reporting(E_ALL);
ini_set("include_path", ini_get("include_path") . ":../app/:../app/modules/:../lib:../lib/ZendFramework:../lib/fedora:../lib/xml-utilities");

require_once('simpletest/unit_tester.php');
require_once('simpletest/reporter.php');

require('Zend/Loader.php');
Zend_Loader::registerAutoload();
require_once("api/FedoraConnection.php");

$mode = "test";

$fedora_cfg = new Zend_Config_Xml("../config/fedora.xml", $mode);
Zend_Registry::set('fedora-config', $fedora_cfg);

$fedora = new FedoraConnection($fedora_cfg->user, $fedora_cfg->password,
			       $fedora_cfg->server, $fedora_cfg->port);
Zend_Registry::set('fedora', $fedora);

// create DB object for access to Emory Shared Data - needed to test setting advisor/committee fields
$esdconfig = new Zend_Config_Xml('../config/esd.xml', $mode);
$esd = Zend_Db::factory($esdconfig->adapter, $esdconfig->params->toArray());
Zend_Registry::set('esd-db', $esd);
Zend_Db_Table_Abstract::setDefaultAdapter($esd);

// required - zend complains without this
Zend_Session::start();

?>
