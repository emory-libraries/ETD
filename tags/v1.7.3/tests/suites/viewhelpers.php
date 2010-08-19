<?php

if (defined('RUNNER')) {
  $is_runner = false;	// running as part of a larger suite
} else {
  define('RUNNER', true);
  $is_runner = true;
}

require_once("../bootstrap.php");

class ViewHelpersGroupTest extends GroupTest {
  function ViewHelpersGroupTest() {
    $this->GroupTest('ETD View Helpers tests');
    $this->addTestFile('viewhelpers/EtdCoinsTest.php');
  }
}

if ($is_runner) {
  $test = new ViewHelpersGroupTest;
  $reporter = TextReporter::inCli() ? new TextReporter() : new HtmlReporter();
  $test->run($reporter);
}

?>
