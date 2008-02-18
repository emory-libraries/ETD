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
  


}

?>