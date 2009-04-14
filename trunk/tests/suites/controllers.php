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
    $this->addTestFile('controllers/TestUserController.php');
    $this->addTestFile('controllers/TestManageController.php');
    $this->addTestFile('controllers/TestSubmissionController.php');
    $this->addTestFile('controllers/TestAuthController.php');
    $this->addTestFile('controllers/TestDocsController.php');
    $this->addTestFile('controllers/TestBrowseController.php');
    $this->addTestFile('controllers/TestConfigController.php');
    $this->addTestFile('controllers/TestFeedsController.php');
    $this->addTestFile('controllers/TestStatisticsController.php');
    $this->addTestFile('controllers/TestEditController.php');
    $this->addTestFile('controllers/TestFileController.php');
    $this->addTestFile('controllers/TestSearchController.php');
    $this->addTestFile('controllers/TestViewController.php');
    $this->addTestFile('controllers/TestBaseController.php');
    $this->addTestFile('controllers/TestHelpController.php');
    $this->addTestFile('controllers/TestProgramController.php');
    //    $this->addTestFile('controllers/TestEtdController.php');
  }
}

if ($is_runner) {
  $test = new ControllerGroupTest;
  $reporter = isset($argv) ? new TextReporter() : new HtmlReporter();
  $test->run($reporter);
}

?>