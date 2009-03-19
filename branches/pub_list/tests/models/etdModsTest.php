<?php
require_once("../bootstrap.php");
require_once('models/etd_mods.php');

class TestEtdMods extends UnitTestCase {
  private $mods;

  function setUp() {
    // error_reporting(E_ALL ^ E_NOTICE);
    $xml = new DOMDocument();
    $xml->load("../fixtures/mods.xml");
    $this->mods = new etd_mods($xml);
  }

  function tearDown() {}

  function testBasicProperties() {
    $this->assertIsA($this->mods, "etd_mods");
    $this->assertIsA($this->mods->chair, "Array");
    $this->assertIsA($this->mods->degree, "etd_degree");
    $this->assertEqual("PhD", $this->mods->degree->name);
    $this->assertEqual("Doctoral", $this->mods->degree->level);
    $this->assertEqual("subfield", $this->mods->degree->discipline);
    $this->assertEqual("subfield", $this->mods->subfield);
  }
  
  function testKeywords() {
    // sanity checks - reading values in the xml
    $this->assertIsa($this->mods->keywords, "Array");
    $this->assertEqual(1, count($this->mods->keywords));
    $this->assertIsa($this->mods->keywords[0], "mods_subject");
    $this->assertEqual("1", count($this->mods->keywords));
  }
  
  function testAddKeywords() {
    // adding new values
    $this->mods->addKeyword("animated mice");
    $this->assertEqual(2, count($this->mods->keywords));
    $this->assertEqual("animated mice", $this->mods->keywords[1]->topic);
    $this->assertPattern('|<mods:subject authority="keyword"><mods:topic>animated mice</mods:topic></mods:subject>|', $this->mods->saveXML());
  }

  function testResearchFields() {
    $this->assertIsa($this->mods->researchfields, "Array");
    $this->assertEqual(1, count($this->mods->researchfields));
    $this->assertIsa($this->mods->researchfields[0], "mods_subject");
    $this->assertEqual("1", count($this->mods->researchfields));

    // test if a field is currently set
    $this->assertTrue($this->mods->hasResearchField("7024"));
    $this->assertFalse($this->mods->hasResearchField("5934"));
  }

  function testAddResearchFields() {

    // add a single field
    $this->mods->addResearchField("Mouse Studies", "7025");
    $this->assertEqual(2, count($this->mods->researchfields));
    $this->assertIsa($this->mods->researchfields[1], "mods_subject");
    $this->assertEqual("Mouse Studies", $this->mods->researchfields[1]->topic);
    $this->assertEqual("7025", $this->mods->researchfields[1]->id);
    // note: pattern is dependent on attribute order; this is how they are created currently
    $this->assertPattern('|<mods:subject authority="proquestresearchfield" ID="id7025"><mods:topic>Mouse Studies</mods:topic></mods:subject>|', $this->mods->saveXML());
    
  }

  function testSetResearchFields() {
    // NOTE: php is now outputting a notice when using __set on arrays
    // (actual logic seems to work properly)
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);
    
    // set all fields from an array 
    $newfields = array("7334" => "Animated Arts", "8493" => "Cheese and Mice",
		       "8593" => "Disney Studies");
    $this->mods->setResearchFields($newfields);

    $this->assertEqual(3, count($this->mods->researchfields));
    $this->assertIsa($this->mods->researchfields[2], "mods_subject");

    $this->assertEqual("7334", $this->mods->researchfields[0]->id);
    $this->assertEqual("Animated Arts", $this->mods->researchfields[0]->topic);
    $this->assertEqual("8493", $this->mods->researchfields[1]->id);
    $this->assertEqual("Cheese and Mice", $this->mods->researchfields[1]->topic);
    $this->assertEqual("8593", $this->mods->researchfields[2]->id);
    $this->assertEqual("Disney Studies", $this->mods->researchfields[2]->topic);
    
    $this->assertPattern('|<mods:subject authority="proquestresearchfield" ID="id8593"><mods:topic>Disney Studies</mods:topic></mods:subject>|', $this->mods->saveXML());

    // check hasResearchField when there are multiple fields
    $this->assertTrue($this->mods->hasResearchField("8593"));
    $this->assertTrue($this->mods->hasResearchField("8493"));
    $this->assertFalse($this->mods->hasResearchField("6006"));

    // set by array with a shorter list - research fields should only contain new values
    $newfields = array("7024" => "Cheese Studies");
    $this->mods->setResearchFields($newfields);
    $this->assertEqual(1, count($this->mods->researchfields));

    error_reporting($errlevel);	    // restore prior error reporting
  }

  function testCheckRequirements() {
    // ignore php errors - "indirect modification of overloaded property
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);

    $missing = $this->mods->checkRequired();
    $this->assertTrue(in_array("table of contents", array_keys($missing)));
    $this->assertFalse($this->mods->readyToSubmit());
    $this->mods->tableOfContents = "1. a chapter -- 2. another chapter";

    $this->assertTrue(in_array("chair", array_keys($missing)));
    $this->mods->chair[0]->id = "wdisney";

    $this->assertTrue(in_array("committee members", array_keys($missing)));
    $this->mods->committee[0]->id = "dduck";

    // does not have rights or copyright yet - not ready  
    $this->assertFalse($this->mods->readyToSubmit());

    // add embargo, pq, copyright & rights
    $this->mods->addNote("no", "admin", "copyright");
    //    $this->mods->addNote("embargo requested? yes", "admin", "embargo");
    $this->mods->embargo_request = "yes";
    $this->mods->addNote("no", "admin", "pq_submit");
    $this->mods->rights = "rights statement";
    $this->assertTrue($this->mods->readyToSubmit());
    
    error_reporting($errlevel);	    // restore prior error reporting
  }

  function testPageNumbers() {
    // number of pages stored in mods:extent - should be able to set and write as a number
    $this->mods->pages = 133;
    $this->assertEqual(133, $this->mods->pages);
    // but should be stored in the xml with page abbreviation
    $this->assertPattern('|<mods:extent>133 p.</mods:extent>|', $this->mods->saveXML());
    
  }

  function testAddCommittee() {
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);

    $count = count($this->mods->committee);
    $this->mods->addCommittee("Duck", "Donald");
    $this->assertEqual($count + 1, count($this->mods->committee));
    $this->assertEqual("Duck", $this->mods->committee[$count]->last);
    $this->assertEqual("Donald", $this->mods->committee[$count]->first);
    $this->assertEqual("Duck, Donald", $this->mods->committee[$count]->full);
    // should probably check xml with regexp, but mods:name is complicated and it seems to be working...

    
    // test adding committee after all committee members have been removed
    // FIXME: this section is TIMING OUT for some reason...
    // ids need to be set so names can be removed
    $this->mods->committee[0]->id = "test";	
    $this->mods->committee[1]->id = "dduck";
    $this->mods->setCommittee(array());
    $this->assertEqual(0, count($this->mods->committee));
    $this->mods->addCommittee("Duck", "Donald");
    $this->assertEqual(1, count($this->mods->committee));
    $this->assertEqual("Duck", $this->mods->committee[0]->last);

    error_reporting($errlevel);	    // restore prior error reporting
  }

  function testRemoveCommittee() {
    $this->expectException(new XmlObjectException("Can't remove committee member/chair with non-existent id"));
    $this->mods->removeCommittee("");
    
    $this->mods->committee[0]->id = "testid";
    $this->mods->removeCommitteeMember("testid");
    $this->assertEqual(0, count($this->mods->committee));
  }

  function testAddNonemoryCommittee() {
    $count = count($this->mods->nonemory_committee);
    $this->mods->addCommittee("Duck", "Daisy", "nonemory_committee", "Disney World");
    $this->assertEqual($count + 1, count($this->mods->nonemory_committee));
    $this->assertEqual("Duck", $this->mods->nonemory_committee[$count]->last);
    $this->assertEqual("Daisy", $this->mods->nonemory_committee[$count]->first);
    $this->assertEqual("Duck, Daisy", $this->mods->nonemory_committee[$count]->full);

    // add when there are none already in the xml
    $xml = new DOMDocument();
    $xml->load("../fixtures/mods2.xml");
    $mods = new etd_mods($xml);

    $mods->addCommittee("Duck", "Daisy", "nonemory_committee", "Disney World");
    $this->assertEqual(1, count($mods->nonemory_committee));
    $this->assertEqual("Duck", $mods->nonemory_committee[0]->last);
    $this->assertEqual("Daisy", $mods->nonemory_committee[0]->first);
    $this->assertEqual("Duck, Daisy", $mods->nonemory_committee[0]->full);

    // FIXME: test passing invalid type

  }

  function testSetCommitteeById() {
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);
    
    $this->mods->setCommittee(array("mhalber"), "chair"); // NOTE: testing against real ESD, so data could change...
    $this->assertEqual("mhalber", $this->mods->chair[0]->id);
    $this->assertEqual("Halbert", $this->mods->chair[0]->last);

    $this->mods->setCommittee(array("ahickco", "jfenton"));
    $this->assertEqual("ahickco", $this->mods->committee[0]->id);
    $this->assertEqual("Hickcox", $this->mods->committee[0]->last);
    $this->assertEqual("jfenton", $this->mods->committee[1]->id);
    $this->assertEqual("Fenton", $this->mods->committee[1]->last);

    error_reporting($errlevel);	    // restore prior error reporting
  }

  // testing adding a second chair
  function testCoChairs() {
    $this->mods->addCommittee("Harrison", "George", "chair");
    $this->assertEqual(2, count($this->mods->chair));
    $this->assertEqual("Harrison", $this->mods->chair[1]->last);
  }


  function testAddNote() {
    $this->mods->addNote("test adding note", "admin", "embargo_expiration_notice");
    $this->assertTrue(isset($this->mods->embargo_notice));
    $this->assertEqual("test adding note", $this->mods->embargo_notice);
    $this->assertPattern('|<mods:note type="admin" ID="embargo_expiration_notice">test adding note</mods:note>|', $this->mods->saveXML());
  }

  function testRemove() {
    $this->mods->addNote("testing note removal", "admin", "embargo_expiration_notice");
    $this->mods->remove("embargo_notice");
    $this->assertFalse(isset($this->mods->embargo_notice));
    $this->assertNoPattern("|<mods:note type='admin' ID='embargo_expiration_notice'>|", $this->mods->saveXML());
  }

  function testPQSubmitNote() {
    // won't be present initially (on records created before it was added)
    $this->assertFalse(isset($this->mods->pq_submit));
    $this->mods->addNote("yes", "admin", "pq_submit");	// add
    $this->assertTrue(isset($this->mods->pq_submit));
    $this->assertPattern('|<mods:note type="admin" ID="pq_submit">|',  $this->mods->saveXML());

    //convenience functions
    // - required?
    $this->mods->degree->name = "PhD";
    $this->assertTrue($this->mods->ProquestRequired());
    $this->mods->degree->name = "MA";
    $this->assertFalse($this->mods->ProquestRequired());
    // - PQ submit field is filled in
    $this->mods->remove("pq_submit");
    $this->assertFalse($this->mods->hasSubmitToProquest());
    $this->mods->addNote("yes", "admin", "pq_submit");
    $this->assertNotNull($this->mods->pq_submit);
    $this->assertEqual("yes", $this->mods->pq_submit);
    $this->assertTrue($this->mods->hasSubmitToProquest(), "submit to PQ is set");
    //    $this->xmlconfig["pq_submit"] = array("xpath" => "mods:note[@type='admin'][@ID='pq_submit']");
    // - send to PQ?
    $this->mods->degree->name = "PhD";
    $this->assertTrue($this->mods->submitToProquest());
    $this->mods->degree->name = "MA";
    $this->mods->pq_submit = "yes";
    $this->assertTrue($this->mods->submitToProquest());
    $this->mods->pq_submit = "no";
    $this->assertFalse($this->mods->submitToProquest());
  }

}

runtest(new TestEtdMods());

?>