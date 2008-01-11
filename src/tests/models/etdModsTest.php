<?php

require_once('models/etd_mods.php');

class TestEtdMods extends UnitTestCase {
  private $mods;

  function setUp() {
    $xml = new DOMDocument();
    $xml->load("fixtures/mods.xml");
    $this->mods = new etd_mods($xml);
  }

  function tearDown() {}

  function testKeywords() {
    // sanity checks - reading values in the xml
    $this->assertIsa($this->mods->keywords, "Array");
    $this->assertEqual(1, count($this->mods->keywords));
    $this->assertIsa($this->mods->keywords[0], "mods_subject");
    $this->assertEqual("1", count($this->mods->keywords));
  }
  
  function testAddKeywords()
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
    $this->assertPattern('|<mods:subject authority="proquestresearchfield" ID="7025"><mods:topic>Mouse Studies</mods:topic></mods:subject>|', $this->mods->saveXML());
    
  }

  function testSetResearchFields() {

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
    
    $this->assertPattern('|<mods:subject authority="proquestresearchfield" ID="8593"><mods:topic>Disney Studies</mods:topic></mods:subject>|', $this->mods->saveXML());

    // check hasResearchField when there are multiple fields
    $this->assertTrue($this->mods->hasResearchField("8593"));
    $this->assertTrue($this->mods->hasResearchField("8493"));
    $this->assertFalse($this->mods->hasResearchField("6006"));

    // set by array with a shorter list - research fields should only contain new values
    $newfields = array("7024" => "Cheese Studies");
    $this->mods->setResearchFields($newfields);
    $this->assertEqual(1, count($this->mods->researchfields));
    
  }
  

}
