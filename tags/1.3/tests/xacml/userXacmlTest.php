<?php

require_once("../bootstrap.php");
require_once('models/user.php');

/* NOTE: this test depends on having these user accounts defined in the test fedora instance:
  author, committee, etdadmin, guest
 - and ETD repository-wide policies must be installed, with unwanted default policies removed
 (and of course xacml must be enabled)

 Warning: this is a very slow test
*/

class TestUserXacml extends UnitTestCase {
  private $pid;

  /**
   * FedoraConnection with default test user credentials
   */
  private $fedoraAdmin;
    
  function setUp() {
    if (!isset($this->fedoraAdmin)) {
      $fedora_cfg = Zend_Registry::get('fedora-config');
      $this->fedoraAdmin = new FedoraConnection($fedora_cfg);
    }
      
    $fname = '../fixtures/user.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $user = new user($dom);
    $user->owner = "author";	// set author to owner for purposes of the test
      
    $this->pid =  $user->pid;
    /* user does not have object-specific policy rules -
       all relevant rules are set in repo-wide policy   */

    try {
      $this->fedoraAdmin->ingest($user->saveXML(), "loading test object");
    } catch (FedoraObjectExists $e) {
      // if a previous test run failed, object may still be in Fedora
      $this->purgeTestObject();
      $this->fedoraAdmin->ingest($user->saveXML(), "loading test object");
    }

  }

  function tearDown() {
    $this->purgeTestObject();
  }

  function purgeTestObject() {
    $this->fedoraAdmin->purge($this->pid, "removing test object");
  }


  function testGuestPermissions() {
    // use guest account to access fedora
    setFedoraAccount("guest");

    // guest shouldn't be able to see anything
    $this->expectException(new FoxmlException("Access Denied to test:user1"));
    $user = new user($this->pid);
    // these datastreams should be accessible
    $this->expectException(new FedoraAccessDenied("getDatastream for {$this->pid}/DC"));
    $this->assertNull($user->dc);
    $this->expectException(new FedoraAccessDenied("getDatastream for {$this->pid}/MADS"));
    $this->assertNull($user->mads);

  }

  function testOwnerPermissions() {
    // using author test account as owner
    setFedoraAccount("author");
    $fedora = Zend_Registry::get("fedora");

    // author should be able to view and modify
    $user = new user($this->pid);

    // these datastreams should be accessible
    $this->assertIsA($user->dc, "dublin_core");
    $this->assertIsA($user->mads, "mads");

    // should be able to modify these datastreams
    $user->dc->title = "new title";    	//   DC
    $this->assertNotNull($fedora->modifyXMLDatastream($user->pid, "DC",
                                $user->dc->datastream_label(),
                                $user->dc->saveXML(), "test etdadmin permissions - modify DC"),
                   "test owner permissions - modify DC");
    
    $user->mads->netid = "username";   // MADS
    $this->assertNotNull($fedora->modifyXMLDatastream($user->pid, "MADS",
                                $user->mads->datastream_label(),
                                $user->mads->saveXML(), "test etdadmin permissions - modify MADS"),
                    "owner can modify MADS");
  }

  function testEtdAdminPermissions() {
    // use etdadmin account
    setFedoraAccount("etdadmin");

    // admin should be able to view but NOT modify
    $user = new user($this->pid);

    // these datastreams should be accessible
    $this->assertIsA($user->dc, "dublin_core");
    $this->assertIsA($user->mads, "mads");

    // should NOT be able to modify MADS datastream
    $user->mads->netid = "username";
    $this->expectError("Access Denied to modify datastream MADS");
    $this->assertNull($user->save("test owner permissions - modify MADS"));
  }

}

runtest(new TestUserXacml());
?>