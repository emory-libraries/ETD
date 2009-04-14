<?php
require_once("../bootstrap.php");
require_once('models/etd.php');
require_once('models/esdPerson.php');

class TestEtd extends UnitTestCase {
    private $etd;

  function setUp() {
    $fname = '../fixtures/etd1.xml';
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
    $this->assertPattern("|<p>chapter 1\s*<br/>\s*chapter 2</p>|", $this->etd->html->contents);
    $this->assertEqual("chapter 1 -- chapter 2", $this->etd->mods->tableOfContents);

    // xacml
    $this->etd->pid = "newpid:1";
    $this->assertEqual("newpid:1", $this->etd->policy->pid);
    $this->assertEqual("newpid-1", $this->etd->policy->policyid);

    
    $this->etd->owner = "dduck";
    $this->assertEqual("dduck", $this->etd->owner);
    $this->assertEqual("dduck", $this->etd->policy->draft->condition->user);

    // department - mods & view policy

    // attach an etd file to test that department is set on etdFile view policy
    $fname = '../fixtures/etdfile.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $etdfile = new etd_file($dom);
    $etdfile->policy->addRule("view");
    $this->etd->addSupplement($etdfile);

    $this->etd->department = "Chemistry";
    $this->assertEqual("Chemistry", $this->etd->mods->department);
    $this->assertEqual("Chemistry", $this->etd->policy->view->condition->department);
    // check that department was also set on etdfile
    $this->assertEqual("Chemistry", $this->etd->supplements[0]->policy->view->condition->department);
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
    $person->grad_coord = "Disney";
    $this->assertEqual("program coordinator", $this->etd->getUserRole($person));

    // grad coordinator field not set
    $person->role = "student";
    $person->grad_coord = null;
    $this->assertNotEqual("program coordinator", $this->etd->getUserRole($person));
    
    // nothing matches - user's base role should be returned
    $person->department = "Warner Brothers";
    $person->role = "default role";
    $this->assertEqual("default role", $this->etd->getUserRole($person));

  }

  function testSetStatus() {

    // attach an etd file for testing
    $fname = '../fixtures/etdfile.xml';
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
    $this->assertTrue(isset($this->etd->pdfs[0]->policy->draft));
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
    // etd object publish rule should have no condition
    $this->assertFalse(isset($this->etd->policy->published->condition));
    
    // etdfile published rule should also be added to related etdfile objects 
    $this->assertIsA($this->etd->pdfs[0]->policy->published, "PolicyRule");
    $this->assertTrue(isset($this->etd->pdfs[0]->policy->published));
    $this->assertTrue(isset($this->etd->pdfs[0]->policy->published->condition));
    // embargo end should be set to today by default
    $this->assertTrue(isset($this->etd->pdfs[0]->policy->published->condition->embargo_end));
    $this->assertEqual($this->etd->pdfs[0]->policy->published->condition->embargo_end, date("Y-m-d"));
    // published rule should NOT be added to original
    $this->assertFalse(isset($this->etd->originals[0]->policy->published));

    // publish with embargo
    $this->etd->mods->embargo_end = "2010-01-01";
    $this->etd->setStatus("published");
    $this->assertEqual($this->etd->pdfs[0]->policy->published->condition->embargo_end, "2010-01-01");
    
  }

  

  function testInitbyTemplate() {
    $etd = new etd();
    $this->assertIsA($etd, "etd");
    $this->assertIsA($etd->dc, "dublin_core");
    $this->assertIsA($etd->rels_ext, "rels_ext");
    $this->assertIsA($etd->mods, "etd_mods");
    $this->assertIsA($etd->html, "etd_html");
    $this->assertIsA($etd->premis, "premis");
    $this->assertIsA($etd->policy, "XacmlPolicy");

    $this->assertEqual("etd", $etd->cmodel);
    // check for error found in ticket:150
    $this->assertEqual("draft", $etd->status());
  
  }

  function testAddCommittee() {	// committee chairs and members
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);
    
    // attach an etd file to test that chair/committee are set on etdFile view policy
    $fname = '../fixtures/etdfile.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $etdfile = new etd_file($dom);
    $etdfile->policy->addRule("view");
    $this->etd->addSupplement($etdfile);

    
    // NOTE: if this netid goes out of ESD, this test will fail
    $this->etd->setCommittee(array("mhalber"), "chair");
    // should be set in mods, rels-ext, and in view policy rule
    $this->assertEqual("mhalber", $this->etd->mods->chair[0]->id);
    $this->assertEqual("Halbert", $this->etd->mods->chair[0]->last);
    $this->assertEqual("mhalber", $this->etd->rels_ext->committee[0]);
    $this->assertTrue($this->etd->policy->view->condition->users->includes("mhalber"));
    $this->assertTrue($this->etd->supplements[0]->policy->view->condition->users->includes("mhalber"));


    $this->etd->setCommittee(array("ahickco", "jfenton"));
    $this->assertEqual("ahickco", $this->etd->mods->committee[0]->id);
    $this->assertEqual("jfenton", $this->etd->mods->committee[1]->id);
    $this->assertEqual("ahickco", $this->etd->rels_ext->committee[0]);
    $this->assertEqual("jfenton", $this->etd->rels_ext->committee[1]);
    $this->assertTrue($this->etd->policy->view->condition->users->includes("ahickco"));
    $this->assertTrue($this->etd->policy->view->condition->users->includes("jfenton"));
    $this->assertTrue($this->etd->supplements[0]->policy->view->condition->users->includes("ahickco"));
    $this->assertTrue($this->etd->supplements[0]->policy->view->condition->users->includes("jfenton"));

    error_reporting($errlevel);	    // restore prior error reporting
  }


  function testConfirmGraduation() {
    $this->etd->confirm_graduation();
    $this->assertEqual("Graduation Confirmed by ETD system",
		       $this->etd->premis->event[count($this->etd->premis->event) - 1]->detail);
  }

  function testPublish() {
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);
    
    $pubdate = "2008-01-01";
    $this->etd->mods->embargo = "6 months";	// specify an embargo duration
    $this->etd->mods->chair[0]->id = "nobody";	// set ids for error messages
    $this->etd->mods->committee[0]->id = "nobodytoo";

    // official pub date, 'actual' pub date
    $this->etd->publish($pubdate, $pubdate);		// if not specified, date defaults to today

    $fname = '../fixtures/user.xml';
    $dom = new DOMDocument();
    $dom->loadXML(file_get_contents($fname));
    $authorinfo = new user($dom);
    $this->etd->related_objects['authorInfo'] = $authorinfo;

    $this->assertEqual($pubdate, $this->etd->mods->originInfo->issued);
    $this->assertEqual("2008-07-01", $this->etd->mods->embargo_end);
    $this->assertEqual("published", $this->etd->status());
    $this->assertEqual("Published by ETD system",
		       $this->etd->premis->event[count($this->etd->premis->event) - 1]->detail);
    // note: publication notification no longer part of publish() function


    // simulate bad data / incomplete record to test exception
    $this->etd->mods->embargo = "";	// empty duration - results in zero-time unix
    $this->expectException(new XmlObjectException("Calculated embargo date does not look correct (timestamp:, 1969-12-31)"));
    $this->etd->publish($pubdate, $pubdate);

    error_reporting($errlevel);	    // restore prior error reporting
  }


  // test adding files to an etd
  function testAddFile() {
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);
    
    $fname = '../fixtures/etdfile.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $etdfile = new etd_file($dom);
    $etdfile->policy->addRule("view");

    $this->etd->mods->chair[0]->id = "jsmith";
    $this->etd->mods->committee[0]->id = "kjones";
    $this->etd->addSupplement($etdfile);

    $this->assertTrue(isset($this->etd->supplements));
    $this->assertEqual(1, count($this->etd->supplements));
    $this->assertTrue(isset($this->etd->rels_ext->supplement));
    $this->assertEqual(1, count($this->etd->rels_ext->supplement));
    // relation to file was added to etd
    $this->assertPattern("|<rel:hasSupplement rdf:resource=\"info:fedora/" . $etdfile->pid . "\"/>|",
			 $this->etd->rels_ext->saveXML());
    // NOTE: relation to etd is not currently added to file object by this function (should it be?)

    // any values already added to etd that are relevant to xacml policy should be set on file
    $this->assertTrue($this->etd->supplements[0]->policy->view->condition->users->includes("jsmith"));
    $this->assertTrue($this->etd->supplements[0]->policy->view->condition->users->includes("kjones"));
    $this->assertEqual("Disney", $this->etd->supplements[0]->policy->view->condition->department);

    error_reporting($errlevel);	    // restore prior error reporting
  }


  
  function testUpdateDC() {
    $fname = '../fixtures/etd2.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $etd = new etd($dom);

    $etd->updateDC();

    $this->assertEqual($etd->mods->title, $etd->dc->title);
    $this->assertEqual($etd->mods->abstract, $etd->dc->description);
    $this->assertEqual($etd->mods->ark, $etd->dc->ark);	// fix dc class
    $this->assertEqual($etd->mods->author->full, $etd->dc->creator);
    $this->assertEqual($etd->mods->chair[0]->full, $etd->dc->contributor);
    $this->assertEqual($etd->mods->language->text, $etd->dc->language);
    $this->assertEqual($etd->mods->researchfields[0]->topic, $etd->dc->subjects[0]);
    $this->assertEqual($etd->mods->keywords[0]->topic, $etd->dc->subjects[1]);
    $this->assertEqual($etd->mods->date, $etd->dc->date);
    $this->assertEqual($etd->mods->genre, $etd->dc->type);

    // need to test setting arks for related objects (pdf/supplement)


    // losing escaped & from mods to dc
    $etd->mods->abstract = "this &amp; that";
    $etd->updateDC();
    $this->assertEqual("this & that", $etd->dc->description);
    $this->assertPattern("|<dc:description>this &amp; that</dc:description>|",
			 $etd->dc->saveXML());
  }


  function testEmbargoExpirationNotice() {
    $event_count = count($this->etd->premis->event);
    $this->etd->embargo_expiration_notice();
    // admin note in mods should be set
    $this->assertTrue(isset($this->etd->mods->embargo_notice));
    $this->assertEqual(date("Y-m-d"), $this->etd->mods->embargo_notice);
    // premis event for record history
    $this->assertEqual($event_count + 1, count($this->etd->premis->event), "additional event added");
    $this->assertEqual("Embargo Expiration 60-Day Notification sent by ETD system",
		       $this->etd->premis->event[$event_count]->detail);	// text of last event
  }

  function testUndoEmbargoExpirationNotice() {
    // simulate embargo expiration notice so it can be undone
    $this->etd->embargo_expiration_notice();
	
    $this->etd->undoEmbargoExpirationNotice();
    $this->assertFalse(isset($this->etd->mods->embargo_notice));
    $this->assertNotEqual("Embargo Expiration 60-Day Notification sent by ETD system",
			  $this->etd->premis->event[count($this->etd->premis->event) - 1]->detail);
  }


  function testTitleAbstractToc() {
    // title was not getting set in MODS
    $this->etd->title = '<p>Enantiomeric <span style="font-family: Symbol">p</span>-Complexes</p>';
    $this->assertPattern('|<p>Enantiomeric <span.*>p</span>-Complexes</p>|', $this->etd->html->title);
    $this->assertPattern("/^Enantiomeric p-Complexes\s*/", $this->etd->mods->title);
    $this->assertPattern("/^Enantiomeric p-Complexes\s*/", $this->etd->dc->title);

    $this->etd->abstract = '<p>Pure TpMo(CO)<sub>2</sub>(<span style="font-family: Symbol;">h</span><sup>3</sup>-pyranyl)...</p>';
    $this->assertPattern('|<p>Pure\s+TpMo\(CO\)<sub>2</sub>\(<span.*>h</span><sup>3</sup>-pyranyl\)...</p>|', $this->etd->html->abstract);
    $this->assertPattern("/Pure\s+TpMo\(CO\)2\(h3-pyranyl\)...\s*/", $this->etd->mods->abstract);
    $this->assertPattern("/Pure\s+TpMo\(CO\)2\(h3-pyranyl\)...\s*/", $this->etd->dc->description);


    $this->etd->contents = '<p style="margin: 0cm 0cm 0pt 21pt;">Introduction</p>
    <p style="margin: 0cm 0cm 0pt 21pt;">Zirconium Complexes</p>';
    $this->assertPattern('|<p>Introduction</p>\s*<p>Zirconium Complexes</p>|', $this->etd->html->contents);
    $this->assertEqual("Introduction -- Zirconium Complexes", $this->etd->mods->tableOfContents);

    
  }

  function testIsRequired() {
    $this->assertTrue($this->etd->isRequired("title"), "title is required");
    $this->assertTrue($this->etd->isRequired("author"), "author is required");
    $this->assertTrue($this->etd->isRequired("program"), "program is required");
    $this->assertTrue($this->etd->isRequired("chair"), "chair is required");
    $this->assertTrue($this->etd->isRequired("committee members"),
		      "committee members are required");
    $this->assertTrue($this->etd->isRequired("researchfields"), "researchfields are required");
    $this->assertTrue($this->etd->isRequired("keywords"), "keywords are required");
    $this->assertTrue($this->etd->isRequired("degree"), "degree is required");
    $this->assertTrue($this->etd->isRequired("language"), "language is required");
    $this->assertTrue($this->etd->isRequired("abstract"), "abstract is required");
    $this->assertTrue($this->etd->isRequired("table of contents"), "contents is required");
    $this->assertTrue($this->etd->isRequired("embargo request"), "embargo request is required");
    $this->assertTrue($this->etd->isRequired("submission agreement"),
		      "submission agreement is required");
    $this->assertTrue($this->etd->isRequired("send to ProQuest"), "send to PQ is required");
    $this->assertTrue($this->etd->isRequired("copyright"), "copyright is required");

    // associated user object to test required fields in contact info
    $authorinfo = new user();
    $this->etd->related_objects['authorInfo'] = $authorinfo;

    // user object not available - user fields required 
    $this->assertTrue($this->etd->isRequired("email"), "email is required");
    $this->assertTrue($this->etd->isRequired("permanent email"),
		      "permanent email is required");
    $this->assertTrue($this->etd->isRequired("permanent address"),
		       "permanent address is required");
    
  }

  function testGetResourceId() {
    // resource id used for all ACL checks
    $this->assertEqual("published etd", $this->etd->getResourceId());
    $this->etd->rels_ext->status = "draft";
    $this->assertEqual("draft etd", $this->etd->getResourceId());
  }


  function testIsHonors() {
    $this->assertFalse($this->etd->isHonors(), "etd is not honors etd");
  }

  

}

runtest(new TestEtd());

?>