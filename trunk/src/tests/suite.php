<?php
define('RUNNER', true);

require_once("bootstrap.php");

// test groups
require_once('models.php');
require_once('controllers.php');

$suite = new TestSuite('All Tests');

// models
$suite->addTestCase(new ModelGroupTest());

// controllers
$suite->addTestCase(new ControllerGroupTest());

$suite->run(new HtmlReporter());


?>
