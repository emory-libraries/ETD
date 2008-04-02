<?php
require_once("../bootstrap.php");
require_once('models/skosCollection.php');

class TestSkosCollection extends UnitTestCase {
  private $skos;

  function setUp() {
    $xml = new DOMDocument();
    $xml->load("../fixtures/skos.xml");
    $this->skos = new collectionHierarchy($xml, "#toplevel");
  }

  function tearDown() {}

  function testBasicProperties() {
    $this->assertIsA($this->skos, "collectionHierarchy");
    $this->assertEqual("Top Level", $this->skos->label);
    $this->assertEqual("#toplevel", $this->skos->id);
    $this->assertIsA($this->skos->collection, "skosCollection");
    $this->assertEqual(2, count($this->skos->members));
    $this->assertIsA($this->skos->members[0], "skosMember");
    $this->assertEqual("a member", $this->skos->members[0]->label);
    $this->assertEqual("another member", $this->skos->members[1]->label);
    $this->assertEqual("third-level member", $this->skos->members[1]->members[0]->label);
  }

  function testMiddleHierarchy() {
    // initialize in the middle of the hierarchy - should have access below and to parent
    $xml = new DOMDocument();
    $xml->load("../fixtures/skos.xml");
    $middle = new collectionHierarchy($xml, "#two");


    $this->assertIsA($middle, "collectionHierarchy");
    $this->assertEqual("another member", $middle->label);
    $this->assertEqual("#two", $middle->id);
    $this->assertIsA($middle->collection, "skosCollection");
    $this->assertEqual(1, count($middle->members));
    $this->assertIsA($middle->members[0], "skosMember");
    $this->assertEqual("third-level member", $middle->members[0]->label);

    // test parent association
    $this->assertIsA($middle->parent, "collectionHierarchy");
    $this->assertEqual($middle->parent->label, "Top Level");
    $this->assertEqual($middle->parent->id, "#toplevel");
    
  }

  public function testGetAllFields() {
    $fields = $this->skos->getAllFields();
    // should be an array that includes the labels for all collections/members
    $this->assertIsA($fields, "array");
    $this->assertTrue(in_array("Top Level", $fields));
    $this->assertTrue(in_array("a member", $fields));
    $this->assertTrue(in_array("another member", $fields));
    $this->assertTrue(in_array("third-level member", $fields));
  }

  public function testFindLabel() {
    $this->assertEqual("Top Level", $this->skos->findLabel("Level"));
    $this->assertEqual("third-level member", $this->skos->findLabel("third-level"));
  }

  public function testfindIdByLabel() {
    $this->assertEqual("#toplevel", $this->skos->findIdbylabel("Top Level"));
    $this->assertEqual("#one", $this->skos->findIdbylabel("a member"));
    $this->assertEqual("#three", $this->skos->findIdbylabel("third-level member"));
  }

  public function testCalculateTotals() {
    $totals = array("a member" => 2, "third-level member" => 1);
    $this->skos->collection->calculateTotal($totals);

    $this->assertEqual(2, $this->skos->members[0]->count);
    $this->assertEqual(1, $this->skos->members[1]->members[0]->count);
    $this->assertEqual(1, $this->skos->members[1]->count);
    $this->assertEqual(3, $this->skos->count);

    $totals = array("a member" => 1, "another member" => 1, "third-level member" => 2);
    $this->skos->collection->calculateTotal($totals);

    $this->assertEqual(1, $this->skos->members[0]->count);
    $this->assertEqual(2, $this->skos->members[1]->members[0]->count);
    $this->assertEqual(3, $this->skos->members[1]->count);	// 2 (third-level) + 1 (self)
    $this->assertEqual(4, $this->skos->count);
    
  }

  public function testBadInitialization() {
    $xml = new DOMDocument();
    $xml->load("../fixtures/skos.xml");
    $this->expectException(new XmlObjectException("Error in constructor: collection id #nonexistent not found"));
    $skos = new collectionHierarchy($xml, "#nonexistent");
  }

}

if (! defined('RUNNER')) {
  define('RUNNER', true);
  $test = &new TestSkosCollection();
  $test->run(new HtmlReporter());
}
