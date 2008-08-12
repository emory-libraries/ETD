<?

error_reporting(E_ALL);
ini_set("include_path", "../../src/app/:../../src/app/modules/:../../src/lib:../../src/lib/ZendFramework:../../src/lib/fedora:../../src/lib/xml-utilities:..:../../src/config:"
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

$config_dir = "../../src/config/";
Zend_Registry::set("config-dir", $config_dir);

// needed for notifier
$config = new Zend_Config_Xml($config_dir . "config.xml", $mode);
Zend_Registry::set('config', $config);
Zend_Registry::set('ldap-config',
	  new Zend_Config_Xml($config_dir . "ldap.xml", $mode));

$fedora_cfg = new Zend_Config_Xml($config_dir . "fedora.xml", $mode);
Zend_Registry::set('fedora-config', $fedora_cfg);

$fedora = new FedoraConnection($fedora_cfg->user, $fedora_cfg->password,
			       $fedora_cfg->server, $fedora_cfg->port);
Zend_Registry::set('fedora', $fedora);

// create DB object for access to Emory Shared Data - needed to test setting advisor/committee fields
$esdconfig = new Zend_Config_Xml($config_dir . 'esd.xml', $mode);
$esd = Zend_Db::factory($esdconfig->adapter, $esdconfig->params->toArray());
Zend_Registry::set('esd-db', $esd);
Zend_Db_Table_Abstract::setDefaultAdapter($esd);

// set up access controls
require_once("xml_acl.php");
$acl = new Xml_Acl($config_dir . "access.xml");
Zend_Registry::set('acl', $acl);
// store acl for use within view also
//$viewRenderer->view->acl = $acl;

// mock objects for Solr interaction
require_once('simpletest/mock_objects.php');
Mock::generate('Etd_Service_Solr');
Mock::generate('Emory_Service_Solr_Response');


$front = Zend_Controller_Front::getInstance();
//$front = $this->view->getFrontController();
$front->setControllerDirectory(array("default" => "../app/controllers", "emory" => "../lib/ZendFramework/Emory"));
$front->addModuleDirectory("../app/modules");
$front->setControllerDirectory("../../src/app/controllers", "default");
// set a dummy request for any functionality that requires it
$front->setRequest(new Zend_Controller_Request_Http());

// add new helper path to view
$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');
$viewRenderer->initView();
$viewRenderer->view->addHelperPath('Emory/View/Helper', 'Emory_View_Helper');



// set up minimal logging but suppress most of the output
$writer = new Zend_Log_Writer_Stream("php://output");
$logger = new Zend_Log($writer);
$filter = new Zend_Log_Filter_Priority(Zend_Log::CRIT);
$logger->addFilter($filter);
Zend_Registry::set('logger', $logger);


// required when running through the web - zend complains without this
if (!isset($argv)) Zend_Session::start();

// convenience function used to run tests individually when not running as a suite
function runtest($test) {
  global $argv;
  if (! defined('RUNNER')) {
    define('RUNNER', true);
    $reporter = isset($argv) ? new TextReporter() : new HtmlReporter();
    $test->run($reporter);
  }
}

// utility function used by xacml tests
function setFedoraAccount($user) {
  $fedora_cfg = Zend_Registry::get('fedora-config');
  
  // create a new fedora connection with configured port & server, specified password
  $fedora = new FedoraConnection($user, $user,	// for test accounts, username = password
				 $fedora_cfg->server, $fedora_cfg->port);
  
  // note: needs to be in registry because this is what the etd object will use
  Zend_Registry::set('fedora', $fedora);
}




?>
