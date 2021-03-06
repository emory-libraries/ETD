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
    $this->addTestFile('lib/TestIngestOrError.php');
    $this->addTestFile('lib/TestUtil.php');
  }
}

if ($is_runner) {
  $test = new LibGroupTest;
  $reporter = TextReporter::inCli() ? new TextReporter() : new HtmlReporter();
  $test->run($reporter);
}

?>