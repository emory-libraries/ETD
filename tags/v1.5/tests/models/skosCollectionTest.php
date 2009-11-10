<?php
require_once("../bootstrap.php");
require_once('models/skosCollection.php');

class TestSkosCollection extends UnitTestCase {
  private $skos;

  private $errlevel;
  function setUp() {

    $xml = new DOMDocument();
    $xml->load("../fixtures/skos.xml");
    $this->skos = new collectionHierarchy($xml, "#toplevel");
  }

  function tearDown() {
  }

  function testBasicProperties() {
    $this->assertIsA($this->skos, "collectionHierarchy");
    $this->assertEqual("Top Level", $this->skos->label);
    $this->assertEqual("#toplevel", $this->skos->id);
    $this->assertIsA($this->skos->collection, "skosCollection");
    $this->assertIsA($this->skos->members, "Array");
    $this->assertEqual(2, count($this->skos->members));
    $this->assertIsA($this->skos->members[0], "skosMember");
    $this->assertEqual("a member", $this->skos->members[0]->label);
    $this->assertEqual("#one", $this->skos->members[0]->id);
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

  // for basic skosCollection, indexed fields should be the same as all fields
  public function testGetIndexedFields() {
    $fields = $this->skos->getIndexedFields();
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

    // find when label has special characters
    $this->skos->label = "Women's Studies";
    $this->assertEqual("#toplevel", $this->skos->findIdbylabel("Women's Studies"));
    $this->skos->label = "Cell & Developmental Biology";
    $this->assertEqual("#toplevel", $this->skos->findIdbylabel("Cell & Developmental Biology"));
  }

  public function testfindLabelById() {
    $this->assertEqual("Top Level", $this->skos->findLabelbyId("#toplevel"));
    $this->assertEqual("a member", $this->skos->findLabelbyId("#one"));
    $this->assertEqual("third-level member", $this->skos->findLabelbyId("#three"));

    // should also work without leading #
    $this->assertEqual("third-level member", $this->skos->findLabelbyId("three"));
    $this->assertEqual("another member", $this->skos->findLabelbyId("#two"));
  }

  public function testfindDescendantIdByLabel() {
    $this->assertEqual("#one", $this->skos->collection->findDescendantIdbyLabel("a member"));
    $this->assertEqual("#three",
		       $this->skos->collection->findDescendantIdbyLabel("third-level member"));
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
    $this->expectException(new XmlObjectException("Error in constructor: collection id '#nonexistent' not found"));
    $skos = new collectionHierarchy($xml, "#nonexistent");
  }

  public function testModify(){
    // NOTE: php is now outputting a notice when using __set on arrays/objects
    // (actual logic seems to work properly)
    $this->errlevel = error_reporting(E_ALL ^ E_NOTICE);

    $this->skos->label = "new label";
    $this->assertEqual("new label", $this->skos->label);
    $this->assertPattern("|<rdfs:label>new label</rdfs:label>|", $this->skos->saveXML());

    $this->skos->members[0]->label = "subcollection";
    $this->assertEqual("subcollection", $this->skos->members[0]->label);
    $this->assertPattern("|<rdfs:label>subcollection</rdfs:label>|", $this->skos->saveXML());

    $this->skos->members[1]->members[0]->label = "level 3";
    $this->assertEqual("level 3", $this->skos->members[1]->members[0]->label);
    $this->assertPattern("|<rdfs:label>level 3</rdfs:label>|", $this->skos->saveXML());

    error_reporting($this->errlevel);	    // restore prior error reporting
  }

  public function testSetMembers() {
    $this->skos->collection->setMembers(array("#two", "#three"));
    $this->assertEqual("#two", $this->skos->members[0]->id);
    $this->assertEqual("another member", $this->skos->members[0]->label);
    $this->assertEqual("#three", $this->skos->members[1]->id);
    $this->assertEqual("third-level member", $this->skos->members[1]->label);
    $this->assertPattern('|skos:member rdf:resource="#two"|', $this->skos->collection->saveXML());
    $this->assertPattern('|skos:member rdf:resource="#three"|', $this->skos->collection->saveXML());
    $this->assertNoPattern('|skos:member rdf:resource="#one"|', $this->skos->collection->saveXML());

    // set to less members than before - last one should be removed
    $this->skos->collection->setMembers(array("#one"));
    $this->assertEqual(1, count($this->skos->members));
    $this->assertEqual("#one", $this->skos->members[0]->id);
    $this->assertPattern('|skos:member rdf:resource="#one"|', $this->skos->collection->saveXML());
    $this->assertNoPattern('|skos:member rdf:resource="#three"|', $this->skos->collection->saveXML());

    // set to more members than current - new one should be added
    $this->skos->collection->setMembers(array("#three", "#two"));
    $this->assertEqual(2, count($this->skos->members));
    // members-by-id updated to new member list
    $this->assertEqual("another member", $this->skos->collection->two->label);
    $this->assertEqual("third-level member", $this->skos->three->label);


    // bug...
    $this->skos->collection->setMembers(array("#two"));
    $this->assertEqual(1, count($this->skos->members));
    // members-by-id updated to new member list
    $this->assertEqual("another member", $this->skos->two->label);
    
  }


  public function testMembersById() {
    // reference by id short-cut
    $this->assertIsA($this->skos->one, "skosMember");
    $this->assertEqual("a member", $this->skos->one->label);
    $this->assertIsA($this->skos->two, "skosMember");
    $this->assertEqual("another member", $this->skos->two->label);
    $this->assertIsA($this->skos->two->three, "skosMember");
    $this->assertEqual("third-level member", $this->skos->two->three->label);

    // modify when referencing this way
    $this->skos->two->label = "new label";
    $this->assertEqual("new label", $this->skos->two->label);
  }

  public function testFindOrphans() {
    $orphans = $this->skos->findOrphans();
    $this->assertIsA($orphans, "Array");
    $this->assertEqual(0, count($orphans));

    $this->skos->collection->setMembers(array());
    $orphans = $this->skos->findOrphans();
    $this->assertIsA($orphans, "Array");
    $this->assertEqual(2, count($orphans));
    $this->assertIsA($orphans[0], "skosCollection");
    $this->assertIsA($orphans[1], "skosCollection");
    $this->assertEqual("#toplevel", $orphans[0]->id);
    $this->assertEqual("#one", $orphans[1]->id);
    $this->assertEqual("toplevel", $orphans[0]->getId());
    $this->assertEqual("one", $orphans[1]->getId());
  }


}

runtest(new TestSkosCollection());
?>