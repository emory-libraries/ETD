<?php

require_once("bootstrap.php");

class XacmlGroupTest extends GroupTest {
  function XacmlGroupTest() {
    $this->GroupTest('ETD XACML tests');
    $this->addTestFile('models/etdXacmlTest.php');
  }
}

if (! defined('RUNNER')) {
  define('RUNNER', true);
  $test = &new XacmlGroupTest();
  $test->run(new HtmlReporter());
}
?>