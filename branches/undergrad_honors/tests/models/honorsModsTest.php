<?php
require_once("../bootstrap.php");
require_once('models/honors_mods.php');

class TestHonorsMods extends UnitTestCase {
  private $mods;

  function setUp() {
    $xml = new DOMDocument();
    $xml->load("../fixtures/mods.xml");
    $this->mods = new honors_mods($xml);
  }

  function tearDown() {}

  function testBasicProperties() {
    $this->assertIsA($this->mods, "honors_mods");
  }

  function testCheckRequirements() {
    // ignore php errors - "indirect modification of overloaded property
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);
    

    // only testing what is different from etd_mods base class
    //  - research fields
    $this->mods->researchfields[0]->id = $this->mods->researchfields[0]->topic = "";
    $missing = $this->mods->checkRequired();
    $this->assertFalse(in_array("researchfields", array_keys($missing)),
		       "researchfields is not missing (not required)");
    

    // also test-- "send to ProQuest", "copyright"
    
    error_reporting($errlevel);	    // restore prior error reporting
  }


  function testIsRequired() {
    $this->assertFalse($this->mods->isRequired("researchfields"),
		       "research fields are not required");
    $this->assertFalse($this->mods->isRequired("researchfields"),
		       "research fields are not required");
    $this->assertFalse($this->mods->isRequired("send to ProQuest"),
		       "send to PQ info not required");
    $this->assertFalse($this->mods->isRequired("copyright"),
		       "copyright info not required");
    
    // sampling of required fields -- all others should be carried over from etd_mods
    $this->assertTrue($this->mods->isRequired("title"), "title is required");
    $this->assertTrue($this->mods->isRequired("author"), "author is required");
    $this->assertTrue($this->mods->isRequired("language"), "language is required");
    $this->assertTrue($this->mods->isRequired("degree"), "degree is required");
    $this->assertTrue($this->mods->isRequired("keywords"), "keywords are required");
    $this->assertTrue($this->mods->isRequired("abstract"), "abstract is required");
  }

}


runtest(new TestHonorsMods());

?>