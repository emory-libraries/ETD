<?php
require_once("../bootstrap.php");
require_once('models/datastreams/premis.php');

class TestPremis extends UnitTestCase {
  private $premis;

  function setUp() {
    $xml = new DOMDocument();
    $xml->load("../fixtures/premis.xml");
    $this->premis = new premis($xml);
  }

  function tearDown() {}

  function testBasicProperties() {
    // types
    $this->assertIsA($this->premis, "premis");
    $this->assertIsA($this->premis->object, "premis_object");
    $this->assertIsA($this->premis->object->identifier, "premis_identifier");
    $this->assertIsA($this->premis->event, "array");
    $this->assertIsA($this->premis->event[0], "premis_event");
    $this->assertIsA($this->premis->event[0]->identifier, "premis_identifier");

    // values from xml
    $this->assertEqual("bitstream", $this->premis->object->type);
    $this->assertEqual("ark", $this->premis->object->identifier->type);
    $this->assertEqual("emory:0011", $this->premis->object->identifier->value);

    $this->assertTrue($this->premis->isValid());
  }

  function testEventProperties() {
    $event = $this->premis->event[0];

    $this->assertEqual("etdrepo", $event->identifier->type);
    $this->assertEqual("emory:0011.1", $event->identifier->value);
    $this->assertEqual("creation", $event->type);
    $this->assertEqual("2008-01-01", $event->date);
    $this->assertEqual("event details", $event->detail);
    $this->assertEqual("success", $event->outcome);
    $this->assertEqual("netid", $event->agent->type);
    $this->assertEqual("username", $event->agent->value);
  }

  function testAddingEvent() {
    $date = "2008-01-02T13:00:00-05:00";  // set a date for testing
    $this->premis->addEvent("modification", "modified thesis record", "success",
          array("ldap","testuser"), $date);

    $this->assertEqual(2, count($this->premis->event));
    $this->assertIsA($this->premis->event[1], "premis_event");
    $this->assertEqual("modification", $this->premis->event[1]->type);
    $this->assertEqual("modified thesis record", $this->premis->event[1]->detail);
    $this->assertEqual("success", $this->premis->event[1]->outcome);
    $this->assertEqual("ldap", $this->premis->event[1]->agent->type);
    $this->assertEqual("testuser", $this->premis->event[1]->agent->value);
    $this->assertEqual($date, $this->premis->event[1]->date);
    $this->assertEqual("emory:0011.2", $this->premis->event[1]->identifier->value);
  }

  function testRemovingEvent() {
    $this->premis->removeEvent("emory:0011.1");
    $this->assertEqual(0, count($this->premis->event));
    $this->assertNoPattern("|<premis:eventIdentifierValue>emory:0011.1</premis:eventIdentifierValue>|",
         $this->premis->saveXML());

    // bogus id should generate an error
    $this->expectError("No premis events found matching identifier 'nonexistent'");
    $this->premis->removeEvent("nonexistent");
    
  }
  
  function testCreatePremisFromScratch() {
    $this->scratch_premis = new premis(); 
    $this->assertIsA($this->scratch_premis, "premis");    
  }   

}

runtest(new TestPremis());
?>
