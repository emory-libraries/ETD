<?php
require_once("../bootstrap.php");
require_once('models/etd.php');


/* NOTE: this test depends on having these user accounts defined in the test fedora instance:
  author, committee, etdadmin, guest
 - and ETD repository-wide policies must be installed, with unwanted default policies removed
 (and of course xacml must be enabled)

 Warning: this is a very slow test
*/

class TestProgramXacml extends UnitTestCase {
  private $pid;

  /**
   * FedoraConnection with default test user credentials
   */
  private $fedoraAdmin;
    
  function setUp() {
    $config = Zend_Registry::get("config");
    $this->pid = $config->programs_pid;
    if (!isset($this->fedoraAdmin)) {
      $fedora_cfg = Zend_Registry::get('fedora-config');
      $this->fedoraAdmin = new FedoraConnection($fedora_cfg->user, $fedora_cfg->password,
			       $fedora_cfg->server, $fedora_cfg->port);
    }
  }


  function testGuestCanView() {
    // use guest account to access fedora
    setFedoraAccount("guest");
    $fedora = Zend_Registry::get("fedora");

    $xml = $fedora->getDatastream($this->pid, "SKOS");
    $this->assertNotNull($xml);

  }

}


runtest(new TestProgramXacml());
?>
