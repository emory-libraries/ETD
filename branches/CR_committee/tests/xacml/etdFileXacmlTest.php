<?php
require_once("../bootstrap.php");
require_once('models/datastreams/etdfile.php');


/* NOTE: this test depends on specific configurations in fedora tests instance
  (see etdXacmlTest.php for more details)

 Warning: this is a very slow test
*/

class TestEtdFileXacml extends UnitTestCase {
  private $pid;

  /**
   * FedoraConnection with default test user credentials
   */
  private $fedoraAdmin;

  function __construct() {
    $fedora_cfg = Zend_Registry::get('fedora-config');
    $this->fedoraAdmin = new FedoraConnection($fedora_cfg);
    
    // get test pid for fedora fixture
    $this->pid = $this->fedoraAdmin->getNextPid($fedora_cfg->pidspace);
  }

  
  function setUp() {
    $fname = '../fixtures/etdfile.xml';
    $dom = new DOMDocument();
    $dom->load($fname);

    $etdfile = new etd_file($dom);
    $etdfile->pid = $this->pid;
    $etdfile->owner =  "author";	// set 'author' test account as owner
      
    // initialize the xacml policy the way it should be set up normally
    //  - these mimic the settings for a PDF or supplement
    $etdfile->policy->addRule("view");
    $etdfile->policy->view->condition->addUser("committee");
    //    $etdfile->policy->view->condition->department = "department";
    $etdfile->policy->addRule("draft");
    $etdfile->policy->draft->condition->user = "author";

    setFedoraAccount("fedoraAdmin");
    $etdfile->ingest("loading test object");
  }

  function tearDown() {
    setFedoraAccount("fedoraAdmin");
    $this->fedoraAdmin->purge($this->pid, "removing test object");
  }


  /* FIXME: need to test rules for departmental staff..
     can't seem to accurately simulate ldap attributes with test account (?)
   */

  function testGuestPermissionsOnUnpublishedEtdFile() {
    // use guest account to access fedora
    setFedoraAccount("guest");

    // test draft etd - guest shouldn't be able to see anything
    $etd = new etd_file($this->pid);
    try {
        // exception shouldn't actually trigger until we try to access something
        $etd->dc;
    } catch (Exception $e) {
    }
    $this->assertIsA($e, 'FedoraAccessDenied');
  }

  function testGuestPermissionsOnPublishedEtdFile() {
    // set etd as published using admin account
    setFedoraAccount("fedoraAdmin");
    $etdfile = new etd_file($this->pid);
    $etdfile->policy->addRule("published");
    $yesterday = time() - (24 * 60 * 60);	// now - 1 day (24 hours; 60 mins; 60 secs)
    $etdfile->policy->published->condition->embargo_end = date("Y-m-d", $yesterday);
    $result = $etdfile->save("added published rule to test guest permissions");
    $this->assertNotNull($result);

    setFedoraAccount("guest");
    $etdfile = new etd_file($this->pid);
    // these datastreams should be accessible
    $this->assertIsA($etdfile->dc, "dublin_core");
    $this->assertIsA($etdfile->rels_ext, "rels_ext");
    // FIXME: how to test access to file datastream ?

    $this->expectException(new FedoraAccessDenied("getDatastream for {$this->pid}/POLICY"));
    $this->assertNull($etdfile->policy);
  }


  function testAuthorPermissionsOnDraft() {
    // set user account to author
    setFedoraAccount("author");

    // record starts out as a draft-- author should be able to read and modify
    $etdfile = new etd_file($this->pid);

    // datastreams should be accessible
    $this->assertIsA($etdfile->dc, "dublin_core");
    $this->assertIsA($etdfile->rels_ext, "rels_ext");
    $this->assertIsA($etdfile->policy, "XacmlPolicy");

    // should be able to modify these datastreams
    $etdfile->dc->title = "new file title";    	//   DC
    $this->assertNotNull($etdfile->save("test author permissions - modify DC on draft etdfile"));

    $etdfile->policy->removeRule("view");    // POLICY
    $this->assertNotNull($etdfile->save("test author permissions - modify POLICY on draft etdfile"));

    try {
        $purged = $etdfile->purge("testing author permissions - purge draft etdfile");
    } catch (Exception $e) {
    }
    $this->assertIsA($e, 'FedoraAccessDenied', 'author should not have access to purge draft etdfile');
    $this->assertFalse(isset($purged), 'author should not be able to purge draft etdfile');
  }

  function testAuthorDeleteDraft() {
    // set user account to author
    setFedoraAccount("author");
    // record starts out as a draft
    $etdfile = new etd_file($this->pid);
    
    // delete (changes status, does not purge)
    $this->assertNotNull($etdfile->delete("testing delete xacml"));
  }


  function testAuthorPermissionsOnNonDraft() {
    // set user account to author
    setFedoraAccount("author");

    // record starts out as a draft-- author should be able to read and modify
    $etdfile = new etd_file($this->pid);

    // remove draft rule for remainder of test
    $etdfile->policy->removeRule("draft");    // POLICY
    $this->assertNotNull($etdfile->save("test author permissions - modify POLICY on draft etdfile"));

    // ETD no longer has draft policy - now test that author *cannot* modify
    $etdfile->dc->title = "new title";	  //   DC
    $this->expectError("Access Denied to modify datastream DC");
    $this->assertNull($etdfile->save("test author permissions - modify DC on non-draft etdfile"));
    $etdfile->dc->calculateChecksum();	// mark as unmodified
    
    $etdfile->rels_ext->supplementOf = "test:etd1";    // RELS-EXT
    $this->expectError("Access Denied to modify datastream RELS-EXT");
    $this->assertNull($etdfile->save("test author permissions - modify RELS-EXT on non-draft etdfile"));
    $etdfile->rels_ext->calculateChecksum();       

    $etdfile->policy->removeRule("view");    // POLICY
    $this->expectError("Access Denied to modify datastream POLICY"); 
    $this->assertNull($etdfile->save("test author permissions - modify POLICY on non-draft etdfile"));

    try {
        $purged = $etdfile->purge("testing author permissions - purge non-draft etdfile");
    } catch (Exception $e) {}
    $this->assertIsA($e, 'FedoraAccessDenied',
        'author should get access denied when attempting to purge non-draft etdfile');
    $this->assertFalse(isset($purged), 'etdfile purge should not return success for author on non-draft');
  }

  function testAuthorDeleteNonDraft() {
    // set user account to author
    setFedoraAccount("author");
    // record starts out as a draft - remove
    $etdfile = new etd_file($this->pid);
    $etdfile->policy->removeRule("draft");  
    $etdfile->save("remove draft policy for testing");

    $this->expectError('Access Denied to modify object properties');
    $this->assertNull($etdfile->delete("testing delete xacml"));
  }

  
  function testCommitteePermissions() {
    // set user account to committee
    setFedoraAccount("committee");

    // for committee member, it shouldn't matter if etd is draft, published, etc.
    $etdfile = new etd_file($this->pid);

    // these datastreams should be accessible
    $this->assertIsA($etdfile->dc, "dublin_core");
    $this->assertIsA($etdfile->rels_ext, "rels_ext");
    $this->assertIsA($etdfile->policy, "XacmlPolicy");

    /* committee should not have access to change any datastreams */
    $etdfile->dc->title = "new title";	  //   DC
    $this->expectError("Access Denied to modify datastream DC");
    $this->assertNull($etdfile->save("test committee permissions - modify DC"));
    $etdfile->dc->calculateChecksum();  // set as not modified so it won't attempt to save again
    
    $etdfile->rels_ext->supplementOf = "test:etd1";    // RELS-EXT
    $this->expectError("Access Denied to modify datastream RELS-EXT");
    $this->assertNull($etdfile->save("test committee permissions - modify RELS-EXT"));
    $etdfile->rels_ext->calculateChecksum();
    
    $etdfile->policy->removeRule("view");    // POLICY
    $this->expectError("Access Denied to modify datastream POLICY");
    $this->assertNull($etdfile->save("test committee permissions - modify POLICY"));
  }

    
  function testEtdAdminViewPermissions() {
    // set user account to etd admin
    setFedoraAccount("etdadmin");

    // for etd admin, it shouldn't matter if etd is draft, published, etc.
    $etdfile = new etd_file($this->pid);

    // these datastreams should be accessible
    $this->assertIsA($etdfile->dc, "dublin_core", "etdadmin can read DC");
    $this->assertIsA($etdfile->rels_ext, "rels_ext", "etdadmin can read RELS-EXT");
    $this->assertIsA($etdfile->policy, "XacmlPolicy", "etdadmin can read POLICY");
  }

  function testEtdAdminCanModify() {
    // set user account to etd admin
    setFedoraAccount("etdadmin");

    // for etd admin, it shouldn't matter if etd is draft, published, etc.
    $etdfile = new etd_file($this->pid);

    // should be able to modify these datastreams
    //    $etdfile->rels_ext->supplementOf = "test:etd1";    // RELS-EXT  (set status)
    $etdfile->rels_ext->addRelation("rel:status", "draft");    // RELS-EXT  (set status)
    $saveresult = $etdfile->save("test etdadmin permissions - modify RELS-EXT on draft etdfile");
    $this->assertNotNull($saveresult,
			 "etdadmin can set status");
    $etdfile->policy->removeRule("draft");    // POLICY
    $this->assertNotNull($etdfile->save("test etdadmin permissions - modify POLICY on draft etdfile"),
			 "etdadmin can modify policy");

    $etdfile->dc->title = "new title";	  //   DC    
    $this->assertNotNull($etdfile->save("test etdadmin permissions - modify DC"), "etdadmin can modify DC");

  }
  
  function IGNORE_testEtdAdminCannotModify() {
    // set user account to etd admin
    setFedoraAccount("etdadmin");

    // for etd admin, it shouldn't matter if etd is draft, published, etc.
    $etdfile = new etd_file($this->pid);

    // can modify DC generically on ETD objects
    // test modifying binary file?
  }

  function testEmbargoNotExpired() {
    setFedoraAccount("fedoraAdmin");
    $etdfile = new etd_file($this->pid);
    $etdfile->policy->addRule("published");
    $tomorrow = time() + (24 * 60 * 60);	// now + 1 day (24 hours; 60 mins; 60 secs)
    $etdfile->policy->published->condition->embargo_end = date("Y-m-d", $tomorrow);
    $result = $etdfile->save("added embargo rule to test permissions");

    // guest shouldn't be able to see anything
    setFedoraAccount("guest");
    try {
        $etdfile = new etd_file($this->pid);
        $etdfile->dc;
    } catch (Exception $e) {}
    $this->assertIsA($e, 'FedoraAccessDenied',
        'guest should be denied access to etdfile with unexpired embargo');

    // author should still be able to see
    setFedoraAccount("author");
    $etdfile = new etd_file($this->pid);
    $this->assertIsA($etdfile->dc, "dublin_core");

    // committee should still be able to see
    setFedoraAccount("committee");
    $etdfile = new etd_file($this->pid);
    $this->assertIsA($etdfile->dc, "dublin_core");

    // etdadmin should still be able to see
    setFedoraAccount("etdadmin");
    $etdfile = new etd_file($this->pid);
    $this->assertIsA($etdfile->dc, "dublin_core");


  }

  function testEmbargoExpired() {
    setFedoraAccount("fedoraAdmin");
    $etdfile = new etd_file($this->pid);
    $etdfile->policy->addRule("published");
    // by default, embargo is set to today when published rule is added
    // FIXME: greater than or equal - should allow access on the day it is published, right?
    $yesterday = time() - (24 * 60 * 60);	// now - 1 day (24 hours; 60 mins; 60 secs)
    $etdfile->policy->published->condition->embargo_end = date("Y-m-d", $yesterday);
    $result = $etdfile->save("added embargo rule to test permissions");

    // guest should now be able to see
    setFedoraAccount("guest");
    $etdfile = new etd_file($this->pid);
    $this->assertIsA($etdfile->dc, "dublin_core");

    // no other users should be affected by this change, so no need to test again
  }

  function testArchivalCopy() {
    // archival copy will have no extra rules (view,draft), will not get 'published'
    setFedoraAccount("fedoraAdmin");
    $etdfile = new etd_file($this->pid);
    $etdfile->policy->removeRule("view");
    $etdfile->policy->removeRule("draft");
    $result = $etdfile->save("remove rules to test archival copy permissions");

    // guest should not be able to see
    setFedoraAccount("guest");
    try {
        $etdfile = new etd_file($this->pid);
        $etdfile->dc;
    } catch (Exception $e_guest) {}
    $this->assertIsA($e_guest, 'FedoraAccessDenied',
        'guest should be denied access to archival copy of etd file');
      
    // author should still be able to see
    setFedoraAccount("author");
    $etdfile = new etd_file($this->pid);
    $this->assertIsA($etdfile->dc, "dublin_core");

    // committee should not be able to see
    setFedoraAccount("committee");
    try {
        $etdfile = new etd_file($this->pid);
        $etdfile->dc;
    } catch (Exception $e_comm) {}
    $this->assertIsA($e_comm, 'FedoraAccessDenied',
        'committee member should not be able to access archival etd file');

    // etdadmin should still be able to see
    setFedoraAccount("etdadmin");
    $etdfile = new etd_file($this->pid);
    $this->assertIsA($etdfile->dc, "dublin_core");


  }
}

runtest(new TestEtdFileXacml());
?>
