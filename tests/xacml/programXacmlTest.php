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

  /**
   * FedoraConnection with default test user credentials
   */
  private $fedoraAdmin;
  private $fedora_cfg;


  function __construct() {
    $this->fedora_cfg = Zend_Registry::get('fedora-config');
    $this->fedoraAdmin = new FedoraConnection($this->fedora_cfg);

    // get test pid for fedora fixture
    $this->pid = $this->fedoraAdmin->getNextPid($this->fedora_cfg->pidspace);
  }


    
  function setUp() {
    $this->dsid = "SKOS";
    
    if (!isset($this->fedoraAdmin)) {
      $this->fedora_cfg = Zend_Registry::get('fedora-config');
      $this->fedoraAdmin = new FedoraConnection($this->fedora_cfg);
    }

    //ingest progrmas object that has the correct owner that will allow it to be modified
    $fname = '../fixtures/programs2.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $etd = new etd($dom);
    $etd->pid = $this->pid;
    $etd->owner = "etdadmin";	// set ownere to etdadmin
    $this->fedoraAdmin->ingest($etd->saveXML(), "loading test object");
  }


  function tearDown() {
    setFedoraAccount("fedoraAdmin");
    $this->fedoraAdmin->purge($this->pid, "removing test object");
  }


  function testGuest() {
    // use guest account to access fedora
    setFedoraAccount("guest");
    $fedora = Zend_Registry::get("fedora");

    // guest can view data
    $xml = $fedora->getDatastream($this->pid, $this->dsid);
    $this->assertNotNull($xml);

    // cannot modify
    $this->expectException(new FedoraAccessDenied("modify datastream - " . $this->pid
					      . "/" . $this->dsid));
    $result = $fedora->modifyXMLDatastream($this->pid, $this->dsid, "program hierarchy",
					   $xml, "testing modify");
    // no result from attempted modify
    $this->assertNull($result, "xacml does not allow guest to modify programs");
  }

  function testModifyByAdmin() {
    setFedoraAccount("etdadmin");
    $fedora = Zend_Registry::get("fedora");
    
    $xml = $fedora->getDatastream($this->pid, $this->dsid);
    $this->assertNotNull($xml);
    
    // *can modify*
    $result = $fedora->modifyXMLDatastream($this->pid, $this->dsid, "program hierarchy",
					   $xml, "modify as etdadmin");
    $this->assertNotNull($result, "xacml does allows etdadmin to modify programs");

  }

    function testModifyByMaint() {
    setFedoraAccount("etdmaint");
    $fedora = Zend_Registry::get("fedora");

    $xml = $fedora->getDatastream($this->pid, $this->dsid);
    $this->assertNotNull($xml);

    // *can modify*
    $result = $fedora->modifyXMLDatastream($this->pid, $this->dsid, "program hierarchy",
					   $xml, "modify as etdmaint");
    $this->assertNotNull($result, "xacml allows etdmaint to modify programs");

  }

}


runtest(new TestProgramXacml());
?>
