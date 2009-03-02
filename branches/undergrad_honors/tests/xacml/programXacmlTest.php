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
  private $dsid;

  private $fedora;

  /**
   * FedoraConnection with default test user credentials
   */
  private $fedoraAdmin;
    
  function setUp() {
    $config = Zend_Registry::get("config");
    $this->pid = $config->programs_pid;
    $this->dsid = "SKOS";
    
    if (!isset($this->fedoraAdmin)) {
      $fedora_cfg = Zend_Registry::get('fedora-config');
      $this->fedoraAdmin = new FedoraConnection($fedora_cfg->user, $fedora_cfg->password,
			       $fedora_cfg->server, $fedora_cfg->port);
    }

    $this->fedora = Zend_Registry::get("fedora");
  }


  function testGuest() {
    // use guest account to access fedora
    setFedoraAccount("guest");

    // guest can view data
    $xml = $this->fedora->getDatastream($this->pid, $this->dsid);
    $this->assertNotNull($xml);

    // cannot modify
    $this->expectException(new FedoraAccessDenied("modify datastream - " . $this->pid
					      . "/" . $this->dsid));
    $result = $this->fedora->modifyXMLDatastream($this->pid, $this->dsid, "program hierarchy",
					   "<rdf:RDF/>", "testing modify");
    // no result from attempted modify
    $this->assertNull($result, "xacml does not allow guest to modify programs");
  }

  function testModify() {
    setFedoraAccount("etdadmin");
    
    $xml = $this->fedora->getDatastream($this->pid, $this->dsid);
    $this->assertNotNull($xml);
    
    // *can* modify
    $result = $this->fedora->modifyXMLDatastream($this->pid, $this->dsid, "program hierarchy",
					   $xml, "modify as etdadmin");
    $this->assertNotNull($result, "xacml allows etdadmin to modify programs");

    // FIXME: how to we keep from messing up the test programs instance by changing it?!? 
    
  }
   

}


runtest(new TestProgramXacml());
?>
