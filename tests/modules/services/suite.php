<?php

if (defined('RUNNER')) {
  $is_runner = false;	// running as part of a larger suite
} else {
  define('RUNNER', true);
  $is_runner = true;
  chdir("..");
}

require_once("../bootstrap.php");
ini_set("include_path", "../modules/services:" . ini_get("include_path"));

class ServicesModuleGroupTest extends GroupTest {
  function ServicesModuleGroupTest() {
    $this->addTestFile('controllers/TestXmlbyidController.php');
  }
}

if ($is_runner) {
  $test = new ServicesModuleGroupTest;
  $reporter = TextReporter::inCli() ? new TextReporter() : new HtmlReporter();
  $test->run($reporter);
}

?>
