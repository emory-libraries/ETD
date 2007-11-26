<?php

require_once("bootstrap.php");

class ControllerGroupTest extends GroupTest {
  function ControllerGroupTest() {
    $this->GroupTest('Controller tests');
    $this->addTestFile('controllers/TestAdminController.php');
    $this->addTestFile('controllers/TestEtdController.php');
  }
}

if (! defined('RUNNER')) {
  define('RUNNER', true);
  $test = &new ControllerGroupTest();
  $test->run(new HtmlReporter());
}
?>