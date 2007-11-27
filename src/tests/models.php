<?php

require_once("bootstrap.php");

class ModelGroupTest extends GroupTest {
  function ModelGroupTest() {
    $this->GroupTest('Model tests');
    $this->addTestFile('models/etdTest.php');
    $this->addTestFile('models/etd_htmlTest.php');
    $this->addTestFile('models/FezEtdTest.php');
  }
}

if (! defined('RUNNER')) {
  define('RUNNER', true);
  $test = &new ModelGroupTest();
  $test->run(new HtmlReporter());
}
?>