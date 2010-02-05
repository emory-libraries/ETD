<?php

if (defined('RUNNER')) {
  $is_runner = false;	// running as part of a larger suite
} else {
  define('RUNNER', true);
  $is_runner = true;
}

require_once("../bootstrap.php");

class XsltGroupTest extends GroupTest {
  function XsltGroupTest() {
    $this->GroupTest('ETD XSLT tests');
    $this->addTestFile('xslt/cleanModsTest.php');
    $this->addTestFile('xslt/etdmsTest.php');
    $this->addTestFile('xslt/mods2marc21Test.php');
    $this->addTestFile('xslt/solrindexTest.php');
  }
}

if ($is_runner) {
  $test = new XsltGroupTest;
  $reporter = TextReporter::inCli() ? new TextReporter() : new HtmlReporter();
  $test->run($reporter);
}

?>
