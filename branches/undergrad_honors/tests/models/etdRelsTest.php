<?php
require_once("../bootstrap.php");
require_once('models/etd.php');
require_once('models/etd_rels.php');

class TestEtdRels extends UnitTestCase {
  private $rels;

  function setUp() {
    $fname = '../fixtures/etd1.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $etd = new etd($dom);
    $this->rels = $etd->rels_ext;
  }

  function tearDown() {
  }
  
  function testBasicProperties() {
    $this->assertIsA($this->rels, "etd_rels");
    $this->assertIsA($this->rels, "rels_ext");

    // values from fixture
    $this->assertEqual("published", $this->rels->status);
    $this->assertEqual("mmouse", $this->rels->author);
    $this->assertEqual("dduck", $this->rels->committee[0]);
    $this->assertEqual("pluto", $this->rels->committee[1]);
  }

  function testSetStatus() {
    $this->expectException(new XmlObjectException("'bogus' is not a recognized etd status"));
    $this->rels->status = "bogus";
  }

  function testProgramSubfield() {
    // setting the fields when they are not present will automatically add them
    $this->assertFalse(isset($this->rels->program), "program is not set from fixture");
    $this->assertFalse(isset($this->rels->subfield), "subfield is not set from fixture");
    $this->rels->program = "progid";
    $this->assertEqual("progid", $this->rels->program);
    $this->assertPattern("|<rel:program>progid</rel:program>|", $this->rels->saveXML());
    $this->rels->subfield = "subfldid";
    $this->assertEqual("subfldid", $this->rels->subfield);
    $this->assertPattern("|<rel:subfield>subfldid</rel:subfield>|", $this->rels->saveXML());
  }

  function testStatusList() {
    $list = etd_rels::getStatusList();
    $this->assertIsA($list, "Array");
    $this->assertTrue(in_array("published", $list), "published is in list of states");
    $this->assertTrue(in_array("draft", $list), "draft is in list of states");
  }

  function testClearCommittee() {
    $this->rels->clearCommittee();
    $this->assertEqual(0, count($this->rels->committee));
    $this->assertNoPattern("|<rel:committee>|", $this->rels->saveXML());
  }

  function testSetCommittee() {
    $this->rels->setCommittee(array("pluto", "dduck"));
    $this->assertEqual(2, count($this->rels->committee));
    $this->assertEqual("pluto", $this->rels->committee[0]);
    $this->assertPattern("|<rel:committee>pluto</rel:committee>|", $this->rels->saveXML());
    $this->assertEqual("dduck", $this->rels->committee[1]);
    $this->assertPattern("|<rel:committee>dduck</rel:committee>|", $this->rels->saveXML());

  }
}


runtest(new TestEtdRels());
?>