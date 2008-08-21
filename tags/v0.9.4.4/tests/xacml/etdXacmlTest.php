<?php
require_once("../bootstrap.php");
require_once('models/etd.php');


/* NOTE: this test depends on having these user accounts defined in the test fedora instance:
  author, committee, etdadmin, guest
 - and ETD repository-wide policies must be installed, with unwanted default policies removed
 (and of course xacml must be enabled)

 Warning: this is a very slow test
*/

class TestEtdXacml extends UnitTestCase {
  private $pid;

  /**
   * FedoraConnection with default test user credentials
   */
  private $fedoraAdmin;
    
  function setUp() {
    if (!isset($this->fedoraAdmin)) {
      $fedora_cfg = Zend_Registry::get('fedora-config');
      $this->fedoraAdmin = new FedoraConnection($fedora_cfg->user, $fedora_cfg->password,
			       $fedora_cfg->server, $fedora_cfg->port);
    }
      
    $fname = '../fixtures/etd1.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $etd = new etd($dom);

    // repository-wide policy rules
    $etd->owner = "author";	// set owner to author username (rely on repository-wide policy for author access)
    // note: etdadmin access is covered by repository-wide policy
    
    // initialize the xacml object policy the way it should be set up normally
    $etd->policy->addRule("view");
    $etd->policy->view->condition->addUser("committee");
    $etd->policy->view->condition->department = "department";
    $etd->policy->addRule("draft");
    $etd->policy->draft->condition->user = "author";
    $this->pid =  $etd->pid;
    
    try {
      $this->fedoraAdmin->ingest($etd->saveXML(), "loading test object");
    } catch (FedoraObjectExists $e) {
      // if a previous test run failed, object may still be in Fedora
      $this->purgeTestObject();
      $this->fedoraAdmin->ingest($etd->saveXML(), "loading test object");
    }

  }

  function tearDown() {
    $this->purgeTestObject();
  }

  function purgeTestObject() {
    $this->fedoraAdmin->purge($this->pid, "removing test object");
  }


  /* FIXME: need to test rules for departmental staff..
     can't seem to accurately simulate ldap attributes with test account (?)
   */

  function NOtestGuestPermissionsOnUnpublishedETD() {
    // use guest account to access fedora
    setFedoraAccount("guest");

    // test draft etd - guest shouldn't be able to see anything
    //    $this->expectException(new FoxmlException("Access Denied to {$this->pid}"));
    $this->expectException(new FedoraAccessDenied("getDatastream for {$this->pid}/RELS-EXT"));
    new etd($this->pid);

  }

  function NOtestGuestPermissionsOnPublishedETD() {
    // set etd as published using admin account
    setFedoraAccount("fedoraAdmin");	// NOTE: wasn't working as etdadmin for some reason (but no error...)
    $etd = new etd($this->pid);
    $etd->policy->addRule("published");
    $result = $etd->save("added published rule to test guest permissions");

    setFedoraAccount("guest");
    $etd = new etd($this->pid);
    // these datastreams should be accessible
    $this->assertIsA($etd->dc, "dublin_core");
    $this->assertIsA($etd->rels_ext, "rels_ext");
    $this->assertIsA($etd->mods, "etd_mods");
    $this->assertIsA($etd->html, "etd_html");
    // these datastreams should still not be accessible
    $this->expectException(new FedoraAccessDenied("getDatastream for {$this->pid}/PREMIS"));
    $this->assertNull($etd->premis);
    $this->expectException(new FedoraAccessDenied("getDatastream for {$this->pid}/POLICY"));
    $this->assertNull($etd->policy);
  }

 
  function NOtestAuthorPermissions() {
    // set user account to author
    setFedoraAccount("author");

    // record starts out as a draft-- author should be able to read and modify
    $etd = new etd($this->pid);

    // these datastreams should be accessible
    $this->assertIsA($etd->dc, "dublin_core");
    $this->assertIsA($etd->rels_ext, "rels_ext");
    $this->assertIsA($etd->mods, "etd_mods");
    $this->assertIsA($etd->html, "etd_html");
    // NOTE: there is a default Fedora repo-wide policy that restricts this... should we keep to that?
    $this->assertIsA($etd->policy, "XacmlPolicy");

    // should be able to modify these datastreams
    $etd->dc->title = "new title";    	//   DC
    $this->assertNotNull($etd->save("test author permissions - modify DC on draft etd"));
    $etd->mods->title = "new title";    //   MODS
    $this->assertNotNull($etd->save("test author permissions - modify MODS on draft etd"));
    $etd->html->title = "new title";    //   XHTML
    $this->assertNotNull($etd->save("test author permissions - modify XHTML on draft etd"));
    $etd->rels_ext->status = "reviewed";    // RELS-EXT
    $this->assertNotNull($etd->save("test author permissions - modify RELS-EXT on draft etd"));
    $etd->premis->addEvent("test", "testing permissions", "success",
			   array("testid", "author"));    	// PREMIS
    $this->assertNotNull($etd->save("test author permissions - modify PREMIS on draft etd"));
    $etd->policy->removeRule("draft");    // POLICY
    $this->assertNotNull($etd->save("test author permissions - modify POLICY on draft etd"));

    // ETD no longer has draft policy - now test that author *cannot* modify
    $etd->dc->title = "new title";	  //   DC
    //    $this->expectError("Access Denied to modify datastream DC");
    $this->assertNull($etd->save("test author permissions - modify DC on non-draft etd"));
    $etd->dc->calculateChecksum();	// mark as unmodified
    
    $etd->mods->title = "new title";    //   MODS
    //    $this->expectError("Access Denied to modify datastream MODS");
    $this->assertNull($etd->save("test author permissions - modify MODS on non-draft etd"));
    $etd->mods->calculateChecksum();
    
    $etd->html->title = "new title";    //   XHTML
    //    $this->expectError("Access Denied to modify datastream XHTML");
    $this->assertNull($etd->save("test author permissions - modify XHTML on non-draft etd"));
    $etd->html->calculateChecksum();
	
    $etd->rels_ext->status = "reviewed";    // RELS-EXT
    //    $this->expectError("Access Denied to modify datastream RELS-EXT");
    $this->assertNull($etd->save("test author permissions - modify RELS-EXT on non-draft etd"));
    $etd->rels_ext->calculateChecksum();

    /*

    FIXME: there is something weird about these two: get an error
    about attempting to modify DC and expecting it doesn't catch it properly
    $etd->premis->addEvent("test", "testing permissions", "success",
    array("testid", "author"));    	// PREMIS
    $this->expectError("Access Denied to modify datastream PREMIS");
    $this->assertNull($etd->save("test author permissions - modify PREMIS on non-draft etd"));
    print "\n<br/><b>DEBUG:</b> end modifying premis<br/>\n";
    

    //    $etd->policy->removeRule("etdadmin");    // POLICY
    $etd->policy->removeRule("view");    // POLICY
    $this->expectError("Access Denied to modify datastream POLICY"); 
    $this->assertNull($etd->save("test author permissions - modify POLICY on non-draft etd"));
    */    

  }

  function NOtestCommitteePermissions() {
    // set user account to committee
    setFedoraAccount("committee");

    // for committee member, it shouldn't matter if etd is draft, published, etc.
    $etd = new etd($this->pid);

    // these datastreams should be accessible
    $this->assertIsA($etd->dc, "dublin_core");
    $this->assertIsA($etd->rels_ext, "rels_ext");
    $this->assertIsA($etd->mods, "etd_mods");
    $this->assertIsA($etd->html, "etd_html");
    // FIXME: there is a default Fedora repo-wide policy that restricts this... should we keep to that?
    $this->assertIsA($etd->policy, "XacmlPolicy");


    /* committee should not have access to change any datastreams */
    $etd->dc->title = "new title";	  //   DC
    $this->expectError("Access Denied to modify datastream DC");
    $this->assertNull($etd->save("test committee permissions - modify DC"));
    $etd->dc->calculateChecksum();  // set as not modified so it won't attempt to save again
    
    $etd->mods->title = "new title";    //   MODS
    $this->expectError("Access Denied to modify datastream MODS");
    $this->assertNull($etd->save("test committee permissions - modify MODS"));
    $etd->mods->calculateChecksum();  // set as not modified so it won't attempt to save again

    
    $etd->html->title = "new title";    //   XHTML
    $this->expectError("Access Denied to modify datastream XHTML");
    $this->assertNull($etd->save("test committee permissions - modify XHTML"));
    $etd->html->calculateChecksum();
    
    $etd->rels_ext->status = "reviewed";    // RELS-EXT
    $this->expectError("Access Denied to modify datastream RELS-EXT");
    $this->assertNull($etd->save("test committee permissions - modify RELS-EXT"));
    $etd->rels_ext->calculateChecksum();


    /** these two fail - same weird DC error as for author  
     $etd->premis->addEvent("test", "testing permissions", "success",
     array("testid", "author"));    	// PREMIS
     $this->expectError("Access Denied to modify datastream PREMIS");
     $this->assertNull($etd->save("test committee permissions - modify PREMIS"));
     $etd->premis->calculateChecksum();
    
     $etd->policy->removeRule("etdadmin");    // POLICY
     $this->expectError("Access Denied to modify datastream POLICY");
     $this->assertNull($etd->save("test committee permissions - modify POLICY"));
    */
  }

  

  
  function  testEtdAdminViewPermissions() {
    // set user account to etd admin
    setFedoraAccount("etdadmin");

    // for etd admin, it shouldn't matter if etd is draft, published, etc.
    $etd = new etd($this->pid);

    // these datastreams should be accessible
    $this->assertIsA($etd->dc, "dublin_core", "etdadmin can read DC");
    $this->assertIsA($etd->rels_ext, "rels_ext", "etdadmin can read RELS-EXT");
    $this->assertIsA($etd->mods, "etd_mods", "etdadmin can read MODS");
    $this->assertIsA($etd->html, "etd_html", "etdadmin can read XHTML");
    // FIXME: there is a default Fedora repo-wide policy that restricts this... should we keep to that?
    $this->assertIsA($etd->policy, "XacmlPolicy", "etdadmin can read POLICY");
  }

  function NOtestEtdAdminCanModify() {
    // set user account to etd admin
    setFedoraAccount("etdadmin");

    // for etd admin, it shouldn't matter if etd is draft, published, etc.
    $etd = new etd($this->pid);

    // should be able to modify these datastreams
    $etd->rels_ext->status = "reviewed";    // RELS-EXT  (set status)
    $saveresult = $etd->save("test etdadmin permissions - modify RELS-EXT on draft etd");
    $this->assertNotNull($saveresult,
			 "etdadmin can set status");
    $etd->premis->addEvent("test", "testing permissions", "success",
			   array("testid", "etdadmin"));    	// PREMIS  (add to event log)
    $this->assertNotNull($etd->save("test etdadmin permissions - modify PREMIS on draft etd"),
			 "etdadmin can log events");
    $etd->policy->removeRule("draft");    // POLICY		// (with changed status)
    $this->assertNotNull($etd->save("test etdadmin permissions - modify POLICY on draft etd"),
			 "etdadmin can modify policy");

    // admin needs access to modify MODS for setting embargo duration, admin notes, etc.
    $etd->mods->title = "new title";    //   MODS
    $this->assertNotNull($etd->save("test etdadmin permissions - modify MODS"), "etdadmin cannot modify MODS");


  }
  
  function NOtestEtdAdminCannotModify() {
    // set user account to etd admin
    setFedoraAccount("etdadmin");

    // for etd admin, it shouldn't matter if etd is draft, published, etc.
    $etd = new etd($this->pid);

    // should not be able to modify main record metadata
    $etd->dc->title = "new title";	  //   DC
    $this->expectError("Access Denied to modify datastream DC");
    $this->assertNull($etd->save("test etdadmin permissions - modify DC"), "etdadmin cannot modify DC");


    $etd = new etd($this->pid);	
    $etd->html->title = "new title";    //   XHTML
    // NOTE: the order of these errors is significant
    $this->expectError("Access Denied to modify datastream XHTML");
    $this->assertNull($etd->save("test etdadmin permissions - modify XHTML"), "etdadmin cannot modify XHTML");
  }



  // test transition from draft to submission (triggered by author)
  function NOtestChangingStatus_submitted() {
    setFedoraAccount("author");

    $etd = new etd($this->pid);
    $etd->setStatus("draft");	// starts as draft first, then moves to submitted
    $etd->setStatus("submitted");
    $this->assertFalse(isset($etd->policy->draft), "draft removed before saving");
    $etd->save("simulating author submission");

    // reload from fedora to confirm policy was saved
    $etd = new etd($this->pid);
    $this->assertEqual("submitted", $etd->status());
    $this->assertFalse(isset($etd->policy->draft), "draft rule no longer present in fedora");
  }
 
  function NOtestChangingStatus_return_to_draft() {
    setFedoraAccount("etdadmin");

    $etd = new etd($this->pid);
    // record has been submitted and is kicked back to draft
    
    $etd->setStatus("draft");	// setting to draft so draft rule will be removed
    $etd->setStatus("submitted");
    $this->assertFalse(isset($etd->policy->draft), "draft removed from submitted record");
    $etd->save("setting status to submitted to test revert to draft");
    // pull fresh from fedora
    $etd = new etd($this->pid);
    $this->assertFalse(isset($etd->policy->draft), "draft removed from submitted record");
    $etd->setStatus("draft");
    $this->assertTrue(isset($etd->policy->draft), "draft added before saving");
    $this->assertEqual($etd->policy->draft->condition->user, "author"); 	// author/owner
    $etd->save("simulating request changes");

    // reload from fedora to confirm policy was saved
    $etd = new etd($this->pid);
    $this->assertEqual("draft", $etd->status());
    $this->assertTrue(isset($etd->policy->draft), "draft present in fedora");
    // set based on author/owner
    $this->assertEqual($etd->policy->draft->condition->user, "author");
    
  }

  function testGradCoordinator() {
    setFedoraAccount("gradcoord");
    //    $this->expectException(new FedoraNotAuthorized("getObjectProfile for test:etd1"));
    // FIXME: why are we getting this warning? everything seems to work okay...
    $etd = new etd($this->pid);

    $this->assertIsA($etd, "etd");
    $this->assertEqual($this->pid, $etd->pid);
    // get info failing?
    $this->assertEqual("etd", $etd->cmodel);
    $this->assertEqual("Why I Like Cheese", $etd->label);
    // view mods
    $this->assertEqual("Why I Like Cheese", $etd->mods->title);
    // view rels
    $this->assertEqual("mmouse", $etd->rels_ext->author);
    // view dc
    $this->assertEqual("Gouda or Cheddar?", $etd->dc->description);
    // view policy
    $this->assertEqual("department", $etd->policy->view->condition->department);

  }
  
}

runtest(new TestEtdXacml());
?>