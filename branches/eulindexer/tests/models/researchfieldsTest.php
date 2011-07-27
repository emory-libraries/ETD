<?php
require_once("../bootstrap.php");
require_once('models/researchfields.php');

class TestResearchfields extends UnitTestCase {
  private $rf;

  function setUp() {
    $xml = new DOMDocument();
    $this->rf = new researchfields();
  }

  function tearDown() {}

  function testBasicProperties() {
    $this->assertIsA($this->rf, "researchfields");
    $this->assertIsA($this->rf, "collectionHierarchy");
    
    $this->assertEqual("UMI Research Fields", $this->rf->label);
    $this->assertEqual("#researchfields", $this->rf->id);
    $this->assertIsA($this->rf->collection, "researchfieldCollection");
    $this->assertIsA($this->rf->collection, "skosCollection");
    $this->assertEqual(2, count($this->rf->members));
    $this->assertIsA($this->rf->members[0], "researchfieldMember");
    $this->assertIsA($this->rf->members[0], "skosMember");
    $this->assertEqual("The Humanities and Social Sciences", $this->rf->members[0]->label);
    $this->assertEqual("The Sciences and Engineering", $this->rf->members[1]->label);
    $this->assertEqual("Communications and the Arts", $this->rf->members[0]->members[0]->label);

    // still program-extended members & collections at deeper level of hierarchy?
    $this->assertIsA($this->rf->members[0]->collection, "researchfieldCollection");
  }

  public function testGetIndexedFields() {
    $fields = $this->rf->getIndexedFields();
    $this->assertIsA($fields, "array");

    // should not contain higher-level hierarchy stuff
    $this->assertFalse(in_array("Communications and the Arts", $fields));
    $this->assertFalse(in_array("The Humanities and Social Sciences", $fields));
    // should contain research fields at any level in the hierarchy
    $this->assertTrue(in_array("Art History", $fields));
    $this->assertTrue(in_array("Psychology, General", $fields));
  }

}

runtest(new TestResearchfields());
?>
