<?php

require_once("bootstrap.php");

class ControllerGroupTest extends GroupTest {
  function ControllerGroupTest() {
    $this->GroupTest('ETD Controller tests');
    $this->addTestFile('controllers/TestManageController.php');
    //    $this->addTestFile('controllers/TestAuthController.php');
    //    $this->addTestFile('controllers/TestEtdController.php');
  }
}

if (! defined('RUNNER')) {
  define('RUNNER', true);
  $test = &new ControllerGroupTest();
  $test->run(new HtmlReporter());
}
?>