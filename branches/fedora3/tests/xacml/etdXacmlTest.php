<?php
require_once("../bootstrap.php");
require_once('models/etd.php');


/* NOTE: this test depends on having these user accounts defined in the test fedora instance:
  author, committee, etdadmin, guest, gradcoord (with attribute deptCoordinator=department)
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

  /**
   * FedoraConnection with current test user credentials
   */
  private $fedora;
    
  function setUp() {
    if (!isset($this->fedoraAdmin)) {
      $fedora_cfg = Zend_Registry::get('fedora-config');
      $this->fedoraAdmin = new FedoraConnection($fedora_cfg);
    }
    $this->fedora = Zend_Registry::get('fedora');
      
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
    if (isset($etd->policy->published)) $etd->policy->removeRule("published");
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

  function testGuestPermissionsOnUnpublishedETD() {
    // use guest account to access fedora
    setFedoraAccount("guest");

    // test draft etd - guest shouldn't be able to see anything
    //$this->expectException(new FoxmlException("Access Denied to {$this->pid}"));
    $this->expectException(new FedoraAccessDenied("getDatastream for {$this->pid}/RELS-EXT"));
    new etd($this->pid);

  }

  function testGuestPermissionsOnPublishedETD() {
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

  function testGuest_getMetadata_unpublished() {
    // use guest account to access fedora
    setFedoraAccount("guest");
    $fedora = Zend_Registry::get("fedora");

    // permission denied on accessing MODS to create dissemination
    $this->expectError();
    $result = $fedora->getDisseminationSOAP($this->pid, "emory-control:metadataTransform-sDef",
					"getMarcxml");
    $this->assertFalse($result);
  }

  function testGuest_getMetadata_pub() {
    // set etd as published using admin account
    setFedoraAccount("fedoraAdmin");
    $etd = new etd($this->pid);
    $etd->policy->addRule("published");
    $result = $etd->save("added published rule to test guest permissions");

    // use guest account to access fedora
    setFedoraAccount("guest");
    $fedora = Zend_Registry::get("fedora");

    // FIXME: sdef object pid should probably be stored in a config file or something...
    $result = $fedora->getDisseminationSOAP($this->pid, "emory-control:metadataTransform-sDef",
					"getMarcxml");
    $this->assertIsA($result, "MIMETypedStream",
		     "result from getDissemination should be MIMETypedStream");
    $this->assertPattern("/<marc:record/", $result->stream,
			 "result from getMarcxml contains marc xml");
  }
 
  function testAuthorPermissions() {
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

    $etd->premis->addEvent("test", "testing permissions", "success",
                            array("testid", "author"));    	// PREMIS
    $result = null;
    try {
        $result = $this->fedora->modifyXMLDatastream($etd->pid, "PREMIS",
						    $etd->premis->datastream_label(),
						    $etd->premis->saveXML(),
                            "test author permissions - modify PREMIS on non-draft etd");
    } catch (Exception $e) {
        $exception = $e;
    }
    $this->assertNull($result, "PREMIS not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    $this->assertPattern("/modify datastream.*PREMIS/", $exception->getMessage());
    unset($exception);
    $result = null;

    $etd->policy->removeRule("view");    // POLICY
    try {
        $result = $this->fedora->modifyXMLDatastream($etd->pid, "POLICY",
						    $etd->policy->datastream_label(),
						    $etd->policy->saveXML(), "test author permissions - modify POLICY on non-draft etd");
    } catch (Exception $e) {
        $exception = $e;
    }
    $this->assertNull($result, "POLICY not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    $this->assertPattern("/modify datastream.*POLICY/", $exception->getMessage());
  }

  function testCommitteePermissions() {
    // set user account to committee
    setFedoraAccount("committee");
    // update to latest fedora connection with committee credentials
    $this->fedora = Zend_Registry::get('fedora');

    // for committee member, it shouldn't matter if etd is draft, published, etc.
    $etd = new etd($this->pid);

    // these datastreams should be accessible
    $this->assertIsA($etd->dc, "dublin_core");
    $this->assertIsA($etd->rels_ext, "rels_ext");
    $this->assertIsA($etd->mods, "etd_mods");
    $this->assertIsA($etd->html, "etd_html");
    // FIXME: there is a default Fedora repo-wide policy that restricts this... should we keep to that?
    $this->assertIsA($etd->policy, "XacmlPolicy");

    $result = null;

    /* committee should not have access to change any datastreams */
    $etd->dc->title = "new title";	  //   DC
    try {
        $result = $this->fedora->modifyXMLDatastream($etd->pid, "DC",
                                $etd->dc->datastream_label(),
                                $etd->dc->saveXML(), "test committee permissions - modify DC");
    } catch (Exception $e) {
        $exception = $e;
    }
    $this->assertNull($result, "DC not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    $this->assertPattern("/modify datastream.*DC/", $exception->getMessage());
    unset($exception);
    $result = null;
        
    $etd->mods->title = "new title";    //   MODS
    // save just what the datastream we want to test
    try {        
        $result = $this->fedora->modifyXMLDatastream($etd->pid, "MODS",
						    $etd->mods->datastream_label(),
						    $etd->mods->saveXML(), "test committee permissions - modify MODS");
    } catch (Exception $e) {
        $exception = $e;
    }
    $this->assertNull($result, "MODS not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    $this->assertPattern("/modify datastream.*MODS/", $exception->getMessage());
    unset($exception);
    $result = null;

    $etd->mods->title = "newer title";    //   MODS
    // save just the datastream we want to test
    try {        
        $result = $this->fedora->modifyXMLDatastream($etd->pid, "MODS",
						    $etd->mods->datastream_label(),
						    $etd->mods->saveXML(), "test committee permissions - modify MODS");
    } catch (Exception $e) {
        $exception = $e;
    }
    $this->assertNull($result, "MODS not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    $this->assertPattern("/modify datastream.*MODS/", $exception->getMessage());
    unset($exception);
    $result = null;
    
    $etd->html->title = "newest title";    //   XHTML
    try {        
        $result = $this->fedora->modifyXMLDatastream($etd->pid, "XHTML",
						    $etd->html->datastream_label(),
						    $etd->html->saveXML(), "test committee permissions - modify XHTML");
    } catch (Exception $e) {
        $exception = $e;        
    }
    $this->assertNull($result, "XHTML not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    $this->assertPattern("/modify datastream.*XHTML/", $exception->getMessage());
    unset($exception);
    $result = null;

    $etd->rels_ext->status = "reviewed";    // RELS-EXT    
    try {
        $result = $this->fedora->modifyXMLDatastream($etd->pid, "RELS-EXT",
						    $etd->rels_ext->datastream_label(),
						    $etd->rels_ext->saveXML(), "test committee permissions - modify RELS-EXT");
    } catch (Exception $e) {
        $exception = $e;
    }
    $this->assertNull($result, "RELS-EXT not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    $this->assertPattern("/modify datastream.*RELS-EXT/", $exception->getMessage());
    //$this->assertNull($etd->save("test committee permissions - modify RELS-EXT"));
    //$etd->rels_ext->calculateChecksum();
    unset($exception);
    $result = null;


    $etd->premis->addEvent("test", "testing permissions", "success",
                            array("testid", "author"));    	// PREMIS
    try {
        $result = $this->fedora->modifyXMLDatastream($etd->pid, "PREMIS",
						    $etd->premis->datastream_label(),
						    $etd->premis->saveXML(), "test committee permissions - modify PREMIS");
    } catch (Exception $e) {
        $exception = $e;
    }
    $this->assertNull($result, "PREMIS not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    $this->assertPattern("/modify datastream.*PREMIS/", $exception->getMessage());
    unset($exception);
    $result = null;

    $etd->policy->removeRule("view");    // POLICY
    try {
        $result = $this->fedora->modifyXMLDatastream($etd->pid, "POLICY",
						    $etd->policy->datastream_label(),
						    $etd->policy->saveXML(), "test committee permissions - modify POLICY");
    } catch (Exception $e) {
        $exception = $e;
    }
    $this->assertNull($result, "POLICY not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    $this->assertPattern("/modify datastream.*POLICY/", $exception->getMessage());    
  }

  

  
  function  testEtdAdminViewPermissions() {
    // set user account to etd admin
    setFedoraAccount("etdadmin");
    // update to latest fedora connection with etdadmin credentials
    $this->fedora = Zend_Registry::get('fedora');

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

  function testEtdAdminCanModify() {
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

    $fedora = Zend_Registry::get("fedora");
    // admin needs access to modify MODS for setting embargo duration, admin notes, etc.
    $etd->mods->title = "new title";    //   MODS
    $this->assertNotNull($fedora->modifyXMLDatastream($etd->pid, "MODS",
                                $etd->mods->datastream_label(),
                                $etd->mods->saveXML(), "test etdadmin permissions - modify MODS"),
                   "etdadmin can modify MODS");

    // if MODS is modified, DC will be updated also   - so etdadmin needs permissions
    $etd->dc->title = "newer title";	  //   DC
    $this->assertNotNull($fedora->modifyXMLDatastream($etd->pid, "DC",
                                $etd->dc->datastream_label(),
                                $etd->dc->saveXML(), "test etdadmin permissions - modify DC"),
                   "etdadmin can modify DC");

    // tech support needs permission to fix xhtml fields
    $etd->html->calculateChecksum();
    $etd->html->title = "newest title";    //   XHTML
    $this->assertNotNull($fedora->modifyXMLDatastream($etd->pid, "XHTML",
                                $etd->html->datastream_label(),
                                $etd->html->saveXML(), "test etdadmin permissions - modify XHTML"),
                   "etdadmin can modify XHTML");
  }

  // test transition from draft to submission (triggered by author)
  function testChangingStatus_submitted() {
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
 
  function testChangingStatus_return_to_draft() {
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
    $etd = new etd($this->pid);

    $this->assertIsA($etd, "etd");
    $this->assertEqual($this->pid, $etd->pid);    
    $this->assertEqual("ETD", $etd->contentModelName());
    $this->assertEqual("1.0", $etd->contentModelVersion());
    $this->assertEqual("Why I Like Cheese", $etd->label);
    // view mods
    $this->assertEqual("Why I Like Cheese", $etd->mods->title);
    // view rels
    $this->assertEqual("author", $etd->rels_ext->author);
    // view dc
    $this->assertEqual("Gouda or Cheddar?", $etd->dc->description);
    // view policy
    $this->assertEqual("department", $etd->policy->view->condition->department);

  }
  
}

runtest(new TestEtdXacml());
?>