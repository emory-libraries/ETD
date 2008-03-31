<?php

require_once("bootstrap.php");

/* NOTE: because these tests rely on data in fedora, they are very slow to run */

class ControllerGroupTest extends GroupTest {
  function ControllerGroupTest() {
    $this->GroupTest('ETD Controller tests');
    $this->addTestFile('controllers/TestManageController.php');
    $this->addTestFile('controllers/TestSubmissionController.php');
    $this->addTestFile('controllers/TestEditController.php');
    $this->addTestFile('controllers/TestAuthController.php');
  }
}

if (! defined('RUNNER')) {
  define('RUNNER', true);
  $test = &new ControllerGroupTest();
  $test->run(new HtmlReporter());
}
?>