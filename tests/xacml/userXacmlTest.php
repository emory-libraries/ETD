<?php

require_once("../bootstrap.php");
require_once('models/authorInfo.php');

/* NOTE: this test depends on having these user accounts defined in the test fedora instance:
  author, committee, etdadmin, guest
 - and ETD repository-wide policies must be installed, with unwanted default policies removed
 (and of course xacml must be enabled)

 Warning: this is a very slow test
*/

class TestUserXacml extends UnitTestCase {
  private $pid;
  private $fedora_cfg;

  /**
   * FedoraConnection with default test user credentials
   */
  private $fedoraAdmin;

  function __construct() {
    $this->fedora_cfg = Zend_Registry::get('fedora-config');
    $this->fedoraAdmin = new FedoraConnection($this->fedora_cfg);
  }


  function setUp() {

    // get test pid for fedora fixture
    $this->pid = $this->fedoraAdmin->getNextPid($this->fedora_cfg->pidspace);

    $fname = '../fixtures/authorInfo.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $authorInfo = new authorInfo($dom);
    $authorInfo->owner = "author";	// set author to owner for purposes of the test
    $authorInfo->pid = $this->pid;

    /* user does not have object-specific policy rules -
       all relevant rules are set in repo-wide policy   */
    $authorInfo->ingest("loading test object");
  }

  function tearDown() {
    try { $this->fedoraAdmin->purge($this->pid, "removing test object");  } catch (Exception $e) {}
  }


  function testGuestPermissions() {
    // use guest account to access fedora
    setFedoraAccount("guest");

    // guest shouldn't be able to see anything
    $this->expectException(new FoxmlException("Access Denied to " . $this->pid));
    $authorInfo = new authorInfo($this->pid);
    // these datastreams should be accessible
    $this->expectException(new FedoraAccessDenied("getDatastream for {$this->pid}/DC"));
    $this->assertNull($authorInfo->dc);
    $this->expectException(new FedoraAccessDenied("getDatastream for {$this->pid}/MADS"));
    $this->assertNull($authorInfo->mads);

  }

  function testOwnerPermissions() {
    // using author test account as owner
    setFedoraAccount("author");
    $fedora = Zend_Registry::get("fedora");

    // author should be able to view and modify
    $authorInfo = new authorInfo($this->pid);

    // these datastreams should be accessible
    $this->assertIsA($authorInfo->dc, "dublin_core");
    $this->assertIsA($authorInfo->mads, "mads");

    // should be able to modify these datastreams
    $authorInfo->dc->title = "new title";    	//   DC
    $this->assertNotNull($authorInfo->save("test author permissions - modify DC"),
        'authorInfo object owner should be able to modify DC datastream');
    $authorInfo->mads->netid = "username";   // MADS
    $this->assertNotNull($authorInfo->save("test author permissions - modify MADS"),
        'authorInfo object owner should be able to modify MADS datastream');
  }

  function testEtdAdminPermissions() {
    // use etdadmin account
    setFedoraAccount("etdadmin");

    // admin should be able to view but NOT modify
    $authorInfo = new authorInfo($this->pid);

    // these datastreams should be accessible
    $this->assertIsA($authorInfo->dc, "dublin_core");
    $this->assertIsA($authorInfo->mads, "mads");

    // should NOT be able to modify MADS datastream
    $authorInfo->mads->netid = "username";
    $this->expectError("Access Denied to modify datastream MADS");
    $this->assertNull($authorInfo->save("test owner permissions - modify MADS"));
  }

}

runtest(new TestUserXacml());
