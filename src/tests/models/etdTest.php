<?php

require_once('models/etd.php');
require_once('models/esdPerson.php');

class TestEtd extends UnitTestCase {
    private $etd;

  function setUp() {
    $fname = 'fixtures/etd1.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $this->etd = new etd($dom);

    $this->etd->policy->addRule("view");
    $this->etd->policy->addRule("draft");

  }
  
  function tearDown() {
  }
  
  function testBasicProperties() {
    // test that foxml properties are accessible
    $this->assertIsA($this->etd, "etd");
    $this->assertIsA($this->etd->dc, "dublin_core");
    $this->assertIsA($this->etd->rels_ext, "rels_ext");
    $this->assertIsA($this->etd->mods, "etd_mods");
    $this->assertIsA($this->etd->html, "etd_html");
    $this->assertIsA($this->etd->premis, "premis");
    $this->assertIsA($this->etd->policy, "XacmlPolicy");
    
    $this->assertEqual("test:etd1", $this->etd->pid);
    $this->assertEqual("Why I Like Cheese", $this->etd->label);
    $this->assertEqual("etd", $this->etd->cmodel);
    $this->assertEqual("mmouse", $this->etd->owner);
  }

  function testSpecialProperties() {
    /* special properties that set multiple values
     formatting is preserved in html & removed for dc/mods */

    $this->etd->title = "<i>Cheesy</i>ness";
    $this->assertEqual("<i>Cheesy</i>ness", $this->etd->html->title);
    $this->assertEqual("Cheesyness", $this->etd->mods->title);
    $this->assertEqual("Cheesyness", $this->etd->dc->title);

    $this->etd->abstract = "<b>cheese</b> explained";
    $this->assertEqual("<b>cheese</b> explained", $this->etd->html->abstract);
    $this->assertEqual("cheese explained", $this->etd->mods->abstract);
    $this->assertEqual("cheese explained", $this->etd->dc->description);

    $this->etd->contents = "<p>chapter 1 <br/> chapter 2</p>";
    $this->assertEqual("<p>chapter 1 <br/> chapter 2</p>", $this->etd->html->contents);
    $this->assertEqual("chapter 1 -- chapter 2", $this->etd->mods->tableOfContents);

    // xacml
    $this->etd->pid = "newpid:1";
    $this->assertEqual("newpid:1", $this->etd->policy->pid);
    $this->assertEqual("newpid-1", $this->etd->policy->policyid);

    
    $this->etd->owner = "dduck";
    $this->assertEqual("dduck", $this->etd->policy->view->condition->users[0]);
    $this->assertEqual("dduck", $this->etd->policy->draft->condition->user);
  }

  function testGetUserRole() {
    $person = new esdPerson();

    // netid matches the author rel in rels-ext 
    $person->netid = "mmouse";
    $this->assertEqual("author", $this->etd->getUserRole($person));
    // netid matches one of the committee rels 
    $person->netid = "dduck";
    $this->assertEqual("committee", $this->etd->getUserRole($person));

    // department matches author's department & user is staff
    $person->netid = "someuser";
    $person->department = "Disney";
    $person->role = "staff";
    $this->assertEqual("departmental staff", $this->etd->getUserRole($person));

    // user is not staff, but department matches
    $person->role = "student";
    $this->assertNotEqual("departmental staff", $this->etd->getUserRole($person));
    
    // nothing matches - user's base role should be returned
    $person->department = "Warner Brothers";
    $person->role = "default role";
    $this->assertEqual("default role", $this->etd->getUserRole($person));

  }

  function testSetStatus() {

    // attach an etd file for testing
    $fname = 'fixtures/etdfile.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $etdfile = new etd_file($dom);
    $this->etd->pdfs[] = $etdfile;
    // separate copy - mock original file
    $dom2 = new DOMDocument();
    $dom2->load($fname);
    $etdfile2 = new etd_file($dom2);
    $etdfile2->pid = "test:etdfile2";
    $this->etd->originals[] = $etdfile2;

    // run through the various statuses in order, check everything is set correctly

    // draft - draft rule is added, published removed (because object had published status before)
    $this->etd->setStatus("draft");
    $this->assertEqual("draft", $this->etd->status());
    $this->assertIsA($this->etd->policy->draft, "PolicyRule");
    $this->assertEqual($this->etd->policy->draft->condition->user, "mmouse");	// owner from etd
    $this->assertFalse(isset($this->etd->policy->published));
    // draft rule should also be added to related etdfile objects
    $this->assertIsA($this->etd->pdfs[0]->policy->draft, "PolicyRule");
    $this->assertFalse(isset($this->etd->pdfs[0]->policy->published));
    $this->assertIsA($this->etd->originals[0]->policy->draft, "PolicyRule");
    $this->assertFalse(isset($this->etd->originals[0]->policy->published));

    // submitted - draft rule removed, no new rules
    $this->etd->setStatus("submitted");
    $this->assertEqual("submitted", $this->etd->status());
    $this->assertFalse(isset($this->etd->policy->draft));
    $this->assertFalse(isset($this->etd->pdfs[0]->policy->draft));
    $this->assertFalse(isset($this->etd->originals[0]->policy->draft));

    // reviewed - no rules change
    $etd_rulecount = count($this->etd->policy->rules);
    $this->etd->setStatus("reviewed");
    $this->assertEqual("reviewed", $this->etd->status());
    $this->assertEqual($etd_rulecount, count($this->etd->policy->rules));

    // approved - no rules change
    $etd_rulecount = count($this->etd->policy->rules);
    $this->etd->setStatus("approved");
    $this->assertEqual("approved", $this->etd->status());
    $this->assertEqual($etd_rulecount, count($this->etd->policy->rules));

    // published - publish rule is added
    $this->etd->setStatus("published");
    $this->assertEqual("published", $this->etd->status());
    $this->assertIsA($this->etd->policy->published, "PolicyRule");
    $this->assertTrue(isset($this->etd->policy->published));
    // published rule should also be added to related etdfile objects 
    $this->assertIsA($this->etd->pdfs[0]->policy->published, "PolicyRule");
    $this->assertTrue(isset($this->etd->pdfs[0]->policy->published));
    // but published rule should NOT be added to original
    $this->assertFalse(isset($this->etd->originals[0]->policy->published));
  }
  


}

?>