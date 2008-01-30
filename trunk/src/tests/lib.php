<?php

require_once("bootstrap.php");

class LibGroupTest extends GroupTest {
  function LibGroupTest() {
    $this->GroupTest('ETD library tests');
    $this->addTestFile('lib/AclTest.php');
  }
}

if (! defined('RUNNER')) {
  define('RUNNER', true);
  $test = &new LibGroupTest();
  $test->run(new HtmlReporter());
}
?>