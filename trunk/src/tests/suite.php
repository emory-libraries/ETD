<?php

error_reporting(E_ALL);
ini_set("include_path", ini_get("include_path") . ":../lib:../lib/ZendFramework:../lib/fedora:../lib/xml-utilities:../app/:../app/modules/");

require_once('simpletest/unit_tester.php');
require_once('simpletest/reporter.php');

require('Zend/Loader.php');
Zend_Loader::registerAutoload();

Zend_Registry::set('fedora-config', new Zend_Config_Xml("../config/fedora.xml", "test"));


$reporter = isset($argc) ? new TextReporter() : new HtmlReporter();

$suite = new TestSuite('All Tests');

// models
$suite->addTestFile('models/etdTest.php');
$suite->addTestFile('models/etd_htmlTest.php');

// controllers
$suite->addTestFile('controllers/TestAdminController.php');
$suite->addTestFile('controllers/TestEtdController.php');

// required - zend complains without this
Zend_Session::start();
$suite->run($reporter);


?>
