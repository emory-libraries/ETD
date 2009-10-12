<?php
define('RUNNER', true);

require_once("../bootstrap.php");

// test groups
require_once('models.php');
require_once('controllers.php');
require_once('lib.php');
require_once('viewhelpers.php');
require_once('xslt.php');

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

$reporter = TextReporter::inCli() ? new TextReporter() : new HtmlReporter();
$suite->run($reporter);


?>
