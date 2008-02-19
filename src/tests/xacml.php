<?php

require_once("bootstrap.php");


class XacmlGroupTest extends GroupTest {
  function XacmlGroupTest() {
    $this->GroupTest('ETD XACML tests');
    //    $this->addTestFile('xacml/etdXacmlTest.php');
    $this->addTestFile('xacml/userXacmlTest.php');
  }
}

if (! defined('RUNNER')) {
  define('RUNNER', true);
  $test = &new XacmlGroupTest();
  $test->run(new HtmlReporter());
}



// utility function used by xacml tests
function setFedoraAccount($user) {
  $fedora_cfg = Zend_Registry::get('fedora-config');
  
  // create a new fedora connection with configured port & server, specified password
  $fedora = new FedoraConnection($user, $user,	// for test accounts, username = password
				 $fedora_cfg->server, $fedora_cfg->port);
  
  // note: needs to be in registry because this is what the etd object will use
  Zend_Registry::set('fedora', $fedora);
}


?>