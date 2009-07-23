<?php

define('RUNNER', true);

require_once("../bootstrap.php");

// test groups
require_once('models.php');
require_once('controllers.php');
require_once('lib.php');
require_once('xacml.php');

$suite = new TestSuite('All ETD Tests');

// models
$suite->addTestCase(new ModelGroupTest());

// controllers
$suite->addTestCase(new ControllerGroupTest());

//lib
$suite->addTestCase(new LibGroupTest());

//xacml
$suite->addTestCase(new XacmlGroupTest());

$reporter = new XmlTimeReporter();
$suite->run($reporter);

?>
