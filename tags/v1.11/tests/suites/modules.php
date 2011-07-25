<?php

if (defined('RUNNER')) {
  $is_runner = false;	// running as part of a larger suite
} else {
  define('RUNNER', true);
  $is_runner = true;
}

require_once("../bootstrap.php");

class ModuleGroupTest extends GroupTest {
  function ModuleGroupTest() {
    $this->addTestFile('modules/services/suite.php');
  }
}

if ($is_runner) {
  $test = new ModuleGroupTest;
  $reporter = TextReporter::inCli() ? new TextReporter() : new HtmlReporter();
  $test->run($reporter);
}

?>
