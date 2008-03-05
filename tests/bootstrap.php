<?

error_reporting(E_ALL);
ini_set("include_path", "../src/app/:../src/app/modules/:../src/lib:../src/lib/ZendFramework:../src/lib/fedora:../src/lib/xml-utilities:"
	. ini_get("include_path"));

require_once('simpletest/unit_tester.php');
require_once('simpletest/reporter.php');

require('Zend/Loader.php');
Zend_Loader::registerAutoload();
require_once("api/FedoraConnection.php");

$mode = "test";
$env = new Zend_Config(array('mode' => $mode));
Zend_Registry::set('env-config', $env);
Zend_Registry::set('environment', $mode);
Zend_Registry::set('debug', false);

// needed for notifier
$config = new Zend_Config_Xml("../src/config/config.xml", $mode);
Zend_Registry::set('config', $config);


$fedora_cfg = new Zend_Config_Xml("../src/config/fedora.xml", $mode);
Zend_Registry::set('fedora-config', $fedora_cfg);

$fedora = new FedoraConnection($fedora_cfg->user, $fedora_cfg->password,
			       $fedora_cfg->server, $fedora_cfg->port);
Zend_Registry::set('fedora', $fedora);

// create DB object for access to Emory Shared Data - needed to test setting advisor/committee fields
$esdconfig = new Zend_Config_Xml('../src/config/esd.xml', $mode);
$esd = Zend_Db::factory($esdconfig->adapter, $esdconfig->params->toArray());
Zend_Registry::set('esd-db', $esd);
Zend_Db_Table_Abstract::setDefaultAdapter($esd);

// set up access controls
require_once("xml_acl.php");
$acl = new Xml_Acl("../src/config/access.xml");
Zend_Registry::set('acl', $acl);
// store acl for use within view also
//$viewRenderer->view->acl = $acl;


// required - zend complains without this
Zend_Session::start();

?>
