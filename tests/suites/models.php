<?php

if (defined('RUNNER')) {
  $is_runner = false; // running as part of a larger suite
} else {
  define('RUNNER', true);
  $is_runner = true;
}

require_once("../bootstrap.php");

class ModelGroupTest extends GroupTest {
  function ModelGroupTest() {
    $this->GroupTest('ETD Model tests');
    $this->addTestFile('models/etdTest.php');
    $this->addTestFile('models/etd_htmlTest.php');
    $this->addTestFile('models/etdRelsTest.php');
    $this->addTestFile('models/etdModsTest.php');
    $this->addTestFile('models/premisTest.php');
    $this->addTestFile('models/policyTest.php');
    $this->addTestFile('models/filePolicyTest.php');

    $this->addTestFile('models/etdFileTest.php');
 
    $this->addTestFile('models/authorInfoTest.php');
    $this->addTestFile('models/madsTest.php');
    $this->addTestFile('models/skosCollectionTest.php');
    $this->addTestFile('models/programsTest.php');
    $this->addTestFile('models/vocabulariesTest.php');

    $this->addTestFile('models/ProQuestSubmissionTest.php');
    $this->addTestFile('models/esdPersonTest.php');

    $this->addTestFile('models/etdSetTest.php');
    $this->addTestFile('models/unAPIresponseTest.php');

    $this->addTestFile('models/SchoolsConfigTest.php');

    $this->addTestFile('models/solrEtdTest.php');
    
    $this->addTestFile('models/chartsTest.php');
    $this->addTestFile('models/TestFedoraCollection.php');
    $this->addTestFile('models/TestNotifier.php');
    $this->addTestFile('models/researchfieldsTest.php');
  }
}

if ($is_runner) {
  $test = new ModelGroupTest;
  $reporter = TextReporter::inCli() ? new TextReporter() : new HtmlReporter();
  $test->run($reporter);
}

?>
