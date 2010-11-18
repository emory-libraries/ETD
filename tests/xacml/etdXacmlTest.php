<?php

require_once("../bootstrap.php");
require_once('models/etd.php');
require_once('models/FedoraCollection.php');


/* NOTE: this test depends on having these user accounts defined in the test fedora instance:
  author, committee, etdadmin, guest, gradcoord (with attribute deptCoordinator=department)
 - and ETD repository-wide policies must be installed, with unwanted default policies removed
 (and of course xacml must be enabled)

 Warning: this is a very slow test
*/

class TestEtdXacml extends UnitTestCase {
  private $pid;
  private $collectionpid;

  /**
   * FedoraConnection with default test user credentials
   */
  private $fedoraAdmin;

  /**
   * FedoraConnection with current test user credentials
   */
  private $fedora;

  function __construct() {
    $fedora_cfg = Zend_Registry::get('fedora-config');
    $this->fedoraAdmin = new FedoraConnection($fedora_cfg);
    
    // get test pids for fedora fixture
    $this->pid = $this->fedoraAdmin->getNextPid($fedora_cfg->pidspace);
    $this->collectionpid = $this->fedoraAdmin->getNextPid($fedora_cfg->pidspace);
  }

    
  function setUp() {
    $this->fedora = Zend_Registry::get('fedora');
      
    $fname = '../fixtures/etd1.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $etd = new etd($dom);
    $etd->pid = $this->pid;

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
    
    $this->fedoraAdmin->ingest($etd->saveXML(), "loading test object");

    //ingest collection object
     $collection = new FedoraCollection();
     $collection->pid = $this->collectionpid;
     $collection->owner = "etdadmin";	// set owner to etdadmin  to allow for editing by superusers
    $collection->ingest("creating test object");



  }

  function tearDown() {
    setFedoraAccount("fedoraAdmin");
    $this->fedoraAdmin->purge($this->pid, "removing test object");
    $this->fedoraAdmin->purge($this->collectionpid, "removing test object");
  }


  /* FIXME: need to test rules for departmental staff..
     can't seem to accurately simulate ldap attributes with test account (?)
   */

  function testGuestPermissionsOnUnpublishedETD() {
    // use guest account to access fedora
    setFedoraAccount("guest");
    // get fedoraConnection instance with guest credentials
    $fedora = Zend_Registry::get("fedora");

    // test draft etd - guest shouldn't be able to see anything
    $result = null;
    $exception = null;
    try {
      $result = $fedora->getDatastream($this->pid, "DC");
    } catch (Exception $e) {
        $exception = $e;
    }
    $this->assertNull($result, "unpublished DC should not be accessible to guest - got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    if ($exception) $this->assertEqual("getDatastream for {$this->pid}/DC", $exception->getMessage());

    $result = null;
    $exception = null;
    try {
      $result = $fedora->getDisseminationSOAP($this->pid, "emory-control:ETDmetadataParts",
					      "title");
    } catch (Exception $e) {
        $exception = $e;
    }
    $this->assertNull($result, "unpublished metadata dissemination should not be accessible to guest - got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    if ($exception) $this->assertEqual("getDissemination title (emory-control:ETDmetadataParts) on {$this->pid}", $exception->getMessage());

  }

  function testGuestPermissionsOnPublishedETD() {
    // set etd as published using admin account
    setFedoraAccount("fedoraAdmin");	// NOTE: wasn't working as etdadmin for some reason (but no error...)
    $etd = new etd($this->pid);
    $etd->policy->addRule("published");
    $result = $etd->save("added published rule to test guest permissions");

    setFedoraAccount("guest");
    // get fedoraConnection instance with guest credentials
    $fedora = Zend_Registry::get("fedora");
     // re-initialize with guest fedora account
     $etd = new etd($this->pid);
    // these datastreams should be accessible
    $this->assertIsA($etd->dc, "dublin_core");
    $this->assertIsA($etd->rels_ext, "rels_ext");
    $this->assertIsA($etd->mods, "etd_mods");

    $result = null;
    $exception = null;
    // can no longer access html datastream directly
    try {
      $result = $fedora->getDatastream($this->pid, "XHTML");
    } catch (Exception $e) {
        $exception = $e;
    }
    $this->assertNull($result, "published XHTML should not be accessible to guest - got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    if ($exception) $this->assertEqual("getDatastream for {$this->pid}/XHTML", $exception->getMessage());

    // access portions of html via service methods
    $this->assertPattern('|<div id="title"|', $etd->title(),
			 "html title accessible via dissemination");
    $this->assertPattern('|<div id="abstract"|', $etd->abstract(),
			 "html abstract accessible via dissemination");
    $this->assertPattern('|<div id="contents"|', $etd->tableOfContents(),
			 "html ToC accessible via dissemination");

    // premis/policy datastreams should still not be accessible
    $result = null;
    $exception = null;
    try {
      $result = $fedora->getDatastream($this->pid, "PREMIS");
    } catch (Exception $e) {
      $exception = $e;
    }
    $this->assertNull($result, "PREMIS should never be accessible to guest - got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    if ($exception) $this->assertEqual("getDatastream for {$this->pid}/PREMIS", $exception->getMessage());

    $result = null;
    $exception = null;
    try {
      $result = $fedora->getDatastream($this->pid, "POLICY");
    } catch (Exception $e) {
      $exception = $e;
    }
    $this->assertNull($result, "POLICY should never be accessible to guest - got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    if ($exception) $this->assertEqual("getDatastream for {$this->pid}/POLICY", $exception->getMessage());
  }
  
  function testGuestPermissionsOnPublishedETD_TOCrestricted() {
    // set etd as published using admin account
    setFedoraAccount("fedoraAdmin");	
    $etd = new etd($this->pid);
    $etd->policy->addRule("published");
    $etd->policy->published->condition->restrictMethods(array("tableofcontents"));
    $result = $etd->save("added published rule to test guest permissions");

    setFedoraAccount("guest");
    // get fedoraConnection instance with guest credentials
    $fedora = Zend_Registry::get("fedora");
     // re-initialize with guest fedora account
     $etd = new etd($this->pid);

     // access portions of html via service methods
    $this->assertPattern('|<div id="title"|', $etd->title(),
			 "html title accessible via dissemination");
    $this->assertPattern('|<div id="abstract"|', $etd->abstract(),
			 "html abstract accessible via dissemination");

    unset($result);
    try {
      $result = $fedora->getDisseminationSOAP($this->pid, "emory-control:ETDmetadataParts",
					      "tableofcontents");
    } catch (Exception $e) {
      $exception = $e;
    }
    $this->assertIsA($exception, "FedoraAccessDenied",
		     "should have gotten a FedoraAccessDenied exception when accessing restricted ToC; got " .
		     get_class($exception));
    $this->assertFalse(isset($result), "getDissemination should not return any content for restricted ToC");
    if (isset($exception))
      $this->assertPattern("/getDissemination tableofcontents/", $exception->getMessage());

  }

  function testGuestPermissionsOnPublishedETD_abstract_restricted() {
    // set etd as published using admin account
    setFedoraAccount("fedoraAdmin");	
    $etd = new etd($this->pid);
    $etd->policy->addRule("published");
    $etd->policy->published->condition->restrictMethods(array("abstract"));
    $result = $etd->save("added published rule to test guest permissions");

    setFedoraAccount("guest");
    // get fedoraConnection instance with guest credentials
    $fedora = Zend_Registry::get("fedora");
     // re-initialize with guest fedora account
     $etd = new etd($this->pid);

     // access portions of html via service methods
    $this->assertPattern('|<div id="title"|', $etd->title(),
			 "html title accessible via dissemination");
    $this->assertPattern('|<div id="contents"|', $etd->tableOfContents(),
			 "html ToC accessible via dissemination");

    unset($result);
    try {
      $result = $fedora->getDisseminationSOAP($this->pid, "emory-control:ETDmetadataParts",
					      "abstract");
    } catch (Exception $e) {
      $exception = $e;
    }
    $this->assertIsA($exception, "FedoraAccessDenied",
	     "should have gotten a FedoraAccessDenied exception when accessing restricted abstract; got " .
		     get_class($exception));
    $this->assertFalse(isset($result), "getDissemination should not return any content for restricted abstract");
    if (isset($exception))
      $this->assertPattern("/getDissemination abstract/", $exception->getMessage());
  }


  function testGuest_getMetadata_unpublished() {
    // use guest account to access fedora
    setFedoraAccount("guest");
    $fedora = Zend_Registry::get("fedora");

    // permission denied on accessing MODS to create dissemination
    $this->expectError();
    $result = $fedora->getDisseminationSOAP($this->pid, "emory-control:metadataTransform",
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


    $etd = new etd($this->pid);
    $xml = $etd->getMarcxml();
    $this->assertPattern("/<marc:record/", $xml,
			 "result from getMarcxml contains marc xml");
  }
 
  function testAuthorPermissions() {
    // set user account to author
    setFedoraAccount("author");
    // get fedoraConnection from registry with current user credentials
    $fedora = Zend_Registry::get("fedora");


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
        $result = $fedora->modifyXMLDatastream($etd->pid, "PREMIS",
						    $etd->premis->datastream_label(),
						    $etd->premis->saveXML(),
                            "test author permissions - modify PREMIS on non-draft etd");
    } catch (Exception $e) {
        $exception = $e;
    }
    $this->assertNull($result, "PREMIS not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    if ($exception) $this->assertPattern("/modify datastream.*PREMIS/", $exception->getMessage());
    unset($exception);
    $result = null;

    $etd->policy->removeRule("view");    // POLICY
    try {
        $result = $fedora->modifyXMLDatastream($etd->pid, "POLICY",
						    $etd->policy->datastream_label(),
						    $etd->policy->saveXML(), "test author permissions - modify POLICY on non-draft etd");
    } catch (Exception $e) {
        $exception = $e;
    }
    $this->assertNull($result, "POLICY not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    if ($exception) $this->assertPattern("/modify datastream.*POLICY/", $exception->getMessage());
  }

  function testCommitteePermissions() {
    // set user account to committee
    setFedoraAccount("committee");
    // get latest fedora connection with committee credentials
    $fedora = Zend_Registry::get('fedora');

    // for committee member, it shouldn't matter if etd is draft, published, etc.
    $etd = new etd($this->pid);

    // these datastreams should be accessible
    $this->assertIsA($etd->dc, "dublin_core");
    $this->assertIsA($etd->rels_ext, "rels_ext");
    $this->assertIsA($etd->mods, "etd_mods");
    // FIXME: there is a default Fedora repo-wide policy that restricts this... should we keep to that?
    $this->assertIsA($etd->policy, "XacmlPolicy");

    // can no longer access html datastream directly
    $this->expectException(new FedoraAccessDenied("getDatastream for {$this->pid}/XHTML"));
    $this->assertIsA($etd->html, "etd_html");
    $this->assertNotNull($etd->title(), "html title accessible via dissemination");
    $this->assertNotNull($etd->abstract(), "html abstract accessible via dissemination");
    $this->assertNotNull($etd->tableOfContents(), "html ToC accessible via dissemination");


    $result = null;

    /* committee should not have access to change any datastreams */
    $etd->dc->title = "new title";	  //   DC
    $result = null;
    try {
      $result = $fedora->modifyXMLDatastream($etd->pid, "DC",
                                $etd->dc->datastream_label(),
                                $etd->dc->saveXML(), "test committee permissions - modify DC");
    } catch (Exception $e) {
        $exception = $e;
    }
    $this->assertNull($result, "DC not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    if ($exception) $this->assertPattern("/modify datastream.*DC/", $exception->getMessage());
    unset($exception);
    $result = null;

    $etd->mods->title = "newer title";    //   MODS
    // save just the datastream we want to test
    try {        
        $result = $fedora->modifyXMLDatastream($etd->pid, "MODS",
						    $etd->mods->datastream_label(),
						    $etd->mods->saveXML(), "test committee permissions - modify MODS");
    } catch (Exception $e) {
        $exception = $e;
    }
    $this->assertNull($result, "MODS not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    if ($exception) $this->assertPattern("/modify datastream.*MODS/", $exception->getMessage());
    unset($exception);
    $result = null;
    
    $etd->html->title = "newest title";    //   XHTML
    try {        
        $result = $fedora->modifyXMLDatastream($etd->pid, "XHTML",
						    $etd->html->datastream_label(),
						    $etd->html->saveXML(), "test committee permissions - modify XHTML");
    } catch (Exception $e) {
        $exception = $e;        
    }
    $this->assertNull($result, "XHTML not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    if ($exception) $this->assertPattern("/modify datastream.*XHTML/", $exception->getMessage());
    unset($exception);
    $result = null;
    
    $etd->rels_ext->status = "reviewed";    // RELS-EXT
    try {
      $result = $fedora->modifyXMLDatastream($etd->pid, "RELS-EXT",
						   $etd->rels_ext->datastream_label(),
						   $etd->rels_ext->saveXML(), "test committee permissions - modify RELS-EXT");
    } catch (Exception $e) {
      $exception = $e;
    }
    $this->assertNull($result, "RELS-EXT not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    if ($exception) $this->assertPattern("/modify datastream.*RELS-EXT/", $exception->getMessage());
    unset($exception);
    $result = null;
    $etd->premis->addEvent("test", "testing permissions", "success",
                            array("testid", "author"));    	// PREMIS
    try {
        $result = $fedora->modifyXMLDatastream($etd->pid, "PREMIS",
						    $etd->premis->datastream_label(),
						    $etd->premis->saveXML(), "test committee permissions - modify PREMIS");
    } catch (Exception $e) {
        $exception = $e;
    }
    $this->assertNull($result, "PREMIS not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    if ($exception) $this->assertPattern("/modify datastream.*PREMIS/", $exception->getMessage());
    unset($exception);
    $result = null;
    
    $etd->policy->removeRule("view");    // POLICY
    try {
        $result = $fedora->modifyXMLDatastream($etd->pid, "POLICY",
						     $etd->policy->datastream_label(),
						     $etd->policy->saveXML(), "test committee permissions - modify POLICY");
    } catch (Exception $e) {
      $exception = $e;
    }
    $this->assertNull($result, "POLICY not updated - timestamp should be null, got '$result'");
    $this->assertIsA($exception, "FedoraAccessDenied");
    if ($exception) $this->assertPattern("/modify datastream.*POLICY/", $exception->getMessage());    
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

    //html disseminations
    $this->assertNotNull($etd->title(), "html title accessible via dissemination");
    $this->assertNotNull($etd->abstract(), "html abstract accessible via dissemination");
    $this->assertNotNull($etd->tableOfContents(), "html ToC accessible via dissemination");

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

  function testCollectionEtdadminCanModify() {
    // set user account to etd admin
    setFedoraAccount("etdadmin");

    // for etdadmin, it shouldn't matter if etd is draft, published, etc.
    $collection = new FedoraCollection($this->collectionpid);

     // should be able to modify fields
    $collection->label = "testing modify";
    $saveresult = $collection->save("test etdadmin permissions - modify collection");
    $this->assertNotNull($saveresult,
			 "etdadmin can modify label");
  }

  function testCollectionEtdmaintCanModify() {
    // set user account to etdmaint
    setFedoraAccount("etdmaint");

    // for etdmaint, it shouldn't matter if etd is draft, published, etc.
    $collection = new FedoraCollection($this->collectionpid);

    // should be able to modify these fields
    $collection->label = "testing modify";
    $saveresult = $collection->save("test etdmaint permissions - modify collection");
    $this->assertNotNull($saveresult,
			 "etdadmin can modify label");

  }


  
}

runtest(new TestEtdXacml());

?>