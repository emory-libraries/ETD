<?php
//This suite is for manually running unit tests. xacml tests are not included
//here because they are very slow and should be run manually or by the
//continuous integration server
define('RUNNER', true);

require_once("../bootstrap.php");

// test groups
require_once('models.php');
require_once('controllers.php');
require_once('lib.php');
require_once('viewhelpers.php');
require_once('xslt.php');
require_once('modules.php');

$suite = new TestSuite('All ETD Tests');

// models
$suite->addTestCase(new ModelGroupTest());

// controllers
$suite->addTestCase(new ControllerGroupTest());

//lib
$suite->addTestCase(new LibGroupTest());

//view helpers
$suite->addTestCase(new ViewHelpersGroupTest());

//xslt
$suite->addTestCase(new XsltGroupTest());

//modules
$suite->addTestCase(new ModuleGroupTest());


$reporter = TextReporter::inCli() ? new TextReporter() : new HtmlReporter();
$suite->run($reporter);


?>
