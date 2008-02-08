<?php

require_once('models/etd.php');

/* NOTE: this test depends on having these user accounts defined in the test fedora instance:
	 author, committee, etdadmin, guest
    (and xacml must be enabled)

  Warning: this is a very slow test
 */

class TestEtdXacml extends UnitTestCase {
    private $pid;

  function setUp() {
    $fname = 'fixtures/etd1.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $etd = new etd($dom);

    // initialize the xacml policy the way it should be set up normally
    $etd->policy->addRule("view");
    $etd->policy->view->condition->users[0] = "author";
    $etd->policy->view->condition->addUser("committee");
    $etd->policy->view->condition->addUser("etdadmin");
    $etd->policy->addRule("draft");
    $etd->policy->draft->condition->user = "author";
    $etd->policy->addRule("etdadmin");
    $etd->policy->etdadmin->condition->users[0] = "etdadmin";

    fedora::ingest($etd->saveXML(), "loading test object");
    $this->pid =  $etd->pid;

  }

  function tearDown() {
    // restore fedora config to default test user
    Zend_Registry::set('fedora-config', new Zend_Config_Xml("../config/fedora.xml", "test"));
    fedora::purge($this->pid, "removing test object");
  }


  function testGuestPermissions() {
    // use guest account to access fedora
    $this->setFedoraAccount("guest");

    // starts out as draft - guest shouldn't be able to see anything
    $etd = new etd($this->pid);

    // should not be able to access any datastreams
    $this->expectError("Authorization Denied to get datastream DC");
    $this->assertNull($etd->dc);
    $this->expectError("Authorization Denied to get datastream MODS");
    $this->assertNull($etd->mods);
    $this->expectError("Authorization Denied to get datastream XHTML");
    $this->assertNull($etd->html);
    $this->expectError("Authorization Denied to get datastream RELS-EXT");
    $this->assertNull($etd->rels_ext);
    $this->expectError("Authorization Denied to get datastream PREMIS");
    $this->assertNull($etd->premis);
    $this->expectError("Authorization Denied to get datastream POLICY");
    $this->assertNull($etd->policy);


    
    $this->setFedoraAccount("etdadmin");
    $etd = new etd($this->pid);
    $etd->policy->addRule("published");
    $etd->save("added published rule to test guest permissions");

    $this->setFedoraAccount("guest");
    $etd = new etd($this->pid);
    // these datastreams should be accessible
    $this->assertIsA($etd->dc, "dublin_core");
    $this->assertIsA($etd->rels_ext, "rels_ext");
    $this->assertIsA($etd->mods, "etd_mods");
    $this->assertIsA($etd->html, "etd_html");
    // these datastreams should still not be accessible
    $this->expectError("Authorization Denied to get datastream PREMIS");
    $this->assertNull($etd->premis);
    $this->expectError("Authorization Denied to get datastream POLICY");
    $this->assertNull($etd->policy);
  }  

 
  function testAuthorPermissions() {
    // set user account to author
    $this->setFedoraAccount("author");

    // record starts out as a draft-- author should be able to read and modify
    $etd = new etd($this->pid);

    // these datastreams should be accessible
    $this->assertIsA($etd->dc, "dublin_core");
    $this->assertIsA($etd->rels_ext, "rels_ext");
    $this->assertIsA($etd->mods, "etd_mods");
    $this->assertIsA($etd->html, "etd_html");
    // FIXME: there is a default Fedora repo-wide policy that restricts this... should we keep to that?
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
    $this->expectError("Access Denied to modify datastream DC");
    $this->assertNull($etd->save("test author permissions - modify DC on non-draft etd"));
    $etd->dc->calculateChecksum();	// mark as unmodified
    
    $etd->mods->title = "new title";    //   MODS
    $this->expectError("Access Denied to modify datastream MODS");
    $this->assertNull($etd->save("test author permissions - modify MODS on non-draft etd"));
    $etd->mods->calculateChecksum();
    
    $etd->html->title = "new title";    //   XHTML
    $this->expectError("Access Denied to modify datastream XHTML");
    $this->assertNull($etd->save("test author permissions - modify XHTML on non-draft etd"));
    $etd->html->calculateChecksum();
    
    $etd->rels_ext->status = "reviewed";    // RELS-EXT
    $this->expectError("Access Denied to modify datastream RELS-EXT");
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
    

    $etd->policy->removeRule("etdadmin");    // POLICY
    $this->expectError("Access Denied to modify datastream POLICY"); 
    $this->assertNull($etd->save("test author permissions - modify POLICY on non-draft etd"));
    */
  }


  function testCommitteePermissions() {
    // set user account to committee
    $this->setFedoraAccount("committee");

    // for committee member, it shouldn't matter if etd is draft, published, etc.
    $etd = new etd($this->pid);

    // these datastreams should be accessible
    $this->assertIsA($etd->dc, "dublin_core");
    $this->assertIsA($etd->rels_ext, "rels_ext");
    $this->assertIsA($etd->mods, "etd_mods");
    $this->assertIsA($etd->html, "etd_html");
    // FIXME: there is a default Fedora repo-wide policy that restricts this... should we keep to that?
    $this->assertIsA($etd->policy, "XacmlPolicy");


    /* testing all datastreams at once because it is much simpler */
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


  function testEtdAdminPermissions() {
    // set user account to etd admin
    $this->setFedoraAccount("etdadmin");

    // for etd admin, it shouldn't matter if etd is draft, published, etc.
    $etd = new etd($this->pid);

    // these datastreams should be accessible
    $this->assertIsA($etd->dc, "dublin_core");
    $this->assertIsA($etd->rels_ext, "rels_ext");
    $this->assertIsA($etd->mods, "etd_mods");
    $this->assertIsA($etd->html, "etd_html");
    // FIXME: there is a default Fedora repo-wide policy that restricts this... should we keep to that?
    $this->assertIsA($etd->policy, "XacmlPolicy");

    // should be able to modify these datastreams
    $etd->rels_ext->status = "reviewed";    // RELS-EXT  (set status)
    $this->assertNotNull($etd->save("test etdadmin permissions - modify RELS-EXT on draft etd"));
    $etd->premis->addEvent("test", "testing permissions", "success",
			   array("testid", "etdadmin"));    	// PREMIS  (add to event log)
    $this->assertNotNull($etd->save("test etdadmin permissions - modify PREMIS on draft etd"));
    $etd->policy->removeRule("draft");    // POLICY		// (with changed status)
    $this->assertNotNull($etd->save("test etdadmin permissions - modify POLICY on draft etd"));


    // should not be able to modify main record metadata
    $etd->dc->title = "new title";	  //   DC
    $this->expectError("Access Denied to modify datastream DC");
    $this->assertNull($etd->save("test etdadmin permissions - modify DC"));
    $etd->dc->calculateChecksum();
    
    $etd->mods->title = "new title";    //   MODS
    $this->expectError("Access Denied to modify datastream MODS");
    $this->assertNull($etd->save("test etdadmin permissions - modify MODS"));
    $etd->mods->calculateChecksum();
	
    $etd->html->title = "new title";    //   XHTML
    // NOTE: the order of these errors is significant
    $this->expectError("Access Denied to modify datastream XHTML");
    $this->assertNull($etd->save("test etdadmin permissions - modify XHTML"));
  }
 
  
  function setFedoraAccount($user) {
    $fedora_cfg = Zend_Registry::get('fedora-config')->toArray();
    $fedora_cfg['user'] = $user;
    $fedora_cfg['password'] = $user;
    Zend_Registry::set('fedora-config', new Zend_Config($fedora_cfg));
  }
  
}