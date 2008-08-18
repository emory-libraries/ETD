<?php
require_once("../bootstrap.php");
require_once('models/programs.php');

class TestPrograms extends UnitTestCase {
  private $programs;

  function setUp() {
    $xml = new DOMDocument();
    $this->programs = new programs();
  }

  function tearDown() {}

  function testBasicProperties() {
    $this->assertIsA($this->programs, "programs");
    $this->assertIsA($this->programs, "collectionHierarchy");
    
    $this->assertEqual("Programs", $this->programs->label);
    $this->assertEqual("#programs", $this->programs->id);
    $this->assertIsA($this->programs->collection, "programCollection");
    $this->assertIsA($this->programs->collection, "skosCollection");
    $this->assertEqual(2, count($this->programs->members));
    $this->assertIsA($this->programs->members[0], "programMember");
    $this->assertIsA($this->programs->members[0], "skosMember");
    $this->assertEqual("Graduate", $this->programs->members[0]->label);
    $this->assertEqual("Undergraduate", $this->programs->members[1]->label);
    $this->assertEqual("Humanities", $this->programs->members[0]->members[0]->label);

    // still program-extended members & collections at deeper level of hierarchy?
    $this->assertIsA($this->programs->members[0]->collection, "programCollection");
  }

  public function testGetIndexedFields() {
    $fields = $this->programs->getIndexedFields();
    $this->assertIsA($fields, "array");

    // should not contain higher-level hierarchy stuff
    $this->assertFalse(in_array("Graduate", $fields));
    $this->assertFalse(in_array("Humanities", $fields));
    // should contain programs and subfields
    $this->assertTrue(in_array("Art History", $fields), "regular program");
    $this->assertTrue(in_array("Psychology", $fields), "program with subfields");
    $this->assertTrue(in_array("Immunology", $fields), "subfield of a program");
  }

}

runtest(new TestPrograms());
?>
