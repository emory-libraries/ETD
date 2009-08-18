<?php
require_once("../bootstrap.php");
if (defined('RUNNER')) {
  $is_runner = false;	// running as part of a larger suite
} else {
  define('RUNNER', true);
  $is_runner = true;
}

class XacmlGroupTest extends GroupTest {
  function XacmlGroupTest() {
    $this->GroupTest('ETD XACML tests');
    $this->addTestFile('xacml/etdXacmlTest.php');
    $this->addTestFile('xacml/userXacmlTest.php');
    $this->addTestFile('xacml/etdFileXacmlTest.php');
    $this->addTestFile('xacml/programXacmlTest.php');
  }
}

if ($is_runner) {
  $test = new XacmlGroupTest;
  $reporter = isset($argv) ? new TextReporter() : new HtmlReporter();
  $test->run($reporter);
}

?>