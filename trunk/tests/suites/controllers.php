<?php
require_once("../bootstrap.php");

if (defined('RUNNER')) {
  $is_runner = false;	// running as part of a larger suite
} else {
  define('RUNNER', true);
  $is_runner = true;
}


/* NOTE: because these tests rely on data in fedora, they are very slow to run */

class ControllerGroupTest extends GroupTest {
  function ControllerGroupTest() {
    $this->GroupTest('ETD Controller tests');
    $this->addTestFile('controllers/TestManageController.php');
    $this->addTestFile('controllers/TestSubmissionController.php');
    $this->addTestFile('controllers/TestAuthController.php');
    $this->addTestFile('controllers/TestDocsController.php');
    $this->addTestFile('controllers/TestBrowseController.php');
    $this->addTestFile('controllers/TestConfigController.php');
    //    $this->addTestFile('controllers/TestEtdController.php');
  }
}

if ($is_runner) {
  $test = new ControllerGroupTest;
  $reporter = isset($argv) ? new TextReporter() : new HtmlReporter();
  $test->run($reporter);
}

?>