<?php

require_once("bootstrap.php");

class ModelGroupTest extends GroupTest {
  function ModelGroupTest() {
    $this->GroupTest('ETD Model tests');
    $this->addTestFile('models/etdTest.php');
    $this->addTestFile('models/etd_htmlTest.php');
    $this->addTestFile('models/etdModsTest.php');
    $this->addTestFile('models/FezEtdTest.php');
    $this->addTestFile('models/premisTest.php');
    $this->addTestFile('models/policyTest.php');

    $this->addTestFile('models/etdFileTest.php');
 
    $this->addTestFile('models/userTest.php');
    $this->addTestFile('models/madsTest.php');
    $this->addTestFile('models/skosCollectionTest.php');

    $this->addTestFile('models/ProQuestSubmissionTest.php');
  }
}

if (! defined('RUNNER')) {
  define('RUNNER', true);
  $test = &new ModelGroupTest();
  $test->run(new HtmlReporter());
}
?>