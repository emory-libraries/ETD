<?

error_reporting(E_ALL);
ini_set("include_path", "../../src/app/:../../src/app/modules/:../../src/lib:../../src/lib/ZendFramework:../../src/lib/fedora:../../src/lib/xml-utilities:..:../../src/config:../../src/app/models:" . ini_get("include_path"));


require('Zend/Loader.php');
Zend_Loader::registerAutoload();
require_once("api/FedoraConnection.php");

require_once('simpletest/unit_tester.php');
require_once('simpletest/reporter.php');
require_once('simpletest/xmltime.php');
include_once("MockObjects.php");

// required when running through the web - zend complains without this
if (! TextReporter::inCli()) Zend_Session::start();

$mode = "test";
$env = new Zend_Config(array('mode' => $mode));
Zend_Registry::set('env-config', $env);
Zend_Registry::set('environment', $mode);
Zend_Registry::set('debug', false);

$src_dir = "../../src";
$config_dir = $src_dir . "/config/";
Zend_Registry::set("config-dir", $config_dir);

// needed for notifier
$config = new Zend_Config_Xml($config_dir . "config.xml", $mode);
Zend_Registry::set('config', $config);
touch($config->logfile);	// make sure logfile exists	  

$fedora_cfg = new Zend_Config_Xml($config_dir . "fedora.xml", $mode);
Zend_Registry::set('fedora-config', $fedora_cfg);

$fedora = new FedoraConnection($fedora_cfg);
Zend_Registry::set('fedora', $fedora);

// create DB object for access to Emory Shared Data - needed to test setting advisor/committee fields
$esdconfig = new Zend_Config_Xml($config_dir . 'esd.xml', $mode);
$esd = Zend_Db::factory($esdconfig->adapter, $esdconfig->params->toArray());
Zend_Registry::set('esd-db', $esd);
Zend_Registry::set('esd-config', $esdconfig);
Zend_Db_Table_Abstract::setDefaultAdapter($esd);



// set up access controls
$acl = new Emory_Acl_Xml($config_dir . "access.xml");
Zend_Registry::set('acl', $acl);
// store acl for use within view also
//$viewRenderer->view->acl = $acl;

// sqlite db for statistics data
$db_config = new Zend_Config_Xml($config_dir . 'statistics.xml', $mode);
// copy over an empty version of the stats db with all tables set up
copy($src_dir . "/data/stats.db", $db_config->params->dbname);
$db = Zend_Db::factory($db_config);
Zend_Registry::set('stat-db', $db);	

Zend_Registry::set('persis-config',
		   new Zend_Config_Xml($config_dir . "persis.xml", $mode));


$front = Zend_Controller_Front::getInstance();
//$front = $this->view->getFrontController();
$front->setControllerDirectory(array("default" => $src_dir . "/app/controllers",
				     "emory" => $src_dir . "/lib/ZendFramework/Emory"));
$front->addModuleDirectory($src_dir . "/app/modules");
$front->setControllerDirectory($src_dir . "/app/controllers", "default");
// set a dummy request for any functionality that requires it
$front->setRequest(new Zend_Controller_Request_Http());

// default routes must be set up for certain helpers, like url
$router = $front->getRouter();
$router->addDefaultRoutes();	

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

// convenience function used to run tests individually when not running as a suite
function runtest($test) {
  global $argv;
  if (! defined('RUNNER')) {
    define('RUNNER', true);
    $reporter = TextReporter::inCli() ? new TextReporter() : new HtmlReporter();
    $test->run($reporter);
  }
}


// utility function used by xacml tests
function setFedoraAccount($user) {
  // create a new fedora connection with configured port & server, specified password
  $fedora_cfg = Zend_Registry::get('fedora-config');
  $fedora_opts = $fedora_cfg->toArray();
  // for test accounts, username = password
  $fedora_opts["username"] = $fedora_opts["password"] = $user;
  $fedora = new FedoraConnection($fedora_opts);
  
  // note: needs to be in registry because this is what the etd object will use
  Zend_Registry::set('fedora', $fedora);
}

?>
