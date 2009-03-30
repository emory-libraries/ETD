<?php

if (defined('RUNNER')) {
  $is_runner = false;	// running as part of a larger suite
} else {
  define('RUNNER', true);
  $is_runner = true;
}

require_once("../bootstrap.php");

class LibGroupTest extends GroupTest {
  function LibGroupTest() {
    $this->GroupTest('ETD Library tests');
    $this->addTestFile('lib/AclTest.php');
    $this->addTestFile('lib/ProcessPdfTest.php');
    $this->addTestFile('lib/TestPdfPageTotal.php');
    $this->addTestFile('lib/TestGetFromFedora.php');
  }
}

if ($is_runner) {
  $test = new LibGroupTest;
  $reporter = isset($argv) ? new TextReporter() : new HtmlReporter();
  $test->run($reporter);
}

?>