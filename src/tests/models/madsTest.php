<?php

require_once('models/mads.php');

class TestMads extends UnitTestCase {
  private $mads;

  function setUp() {
    $xml = new DOMDocument();
    $xml->load("fixtures/mads.xml");
    $this->mads = new mads($xml);
  }

  function tearDown() {}

  function testBasicProperties() {
    //types
    $this->assertIsA($this->mads, "mads");
    $this->assertIsA($this->mads->name, "mads_name");
    $this->assertIsa($this->mads->permanent, "mads_affiliation");
    $this->assertIsa($this->mads->permanent->address, "mads_address");
    //values
    $this->assertEqual("Mickey", $this->mads->name->first);
    $this->assertEqual("Mouse", $this->mads->name->last);
    $this->assertEqual("1920", $this->mads->name->date);
    $this->assertEqual("123 Disney Lane", $this->mads->permanent->address->street);
    $this->assertEqual("Disney World", $this->mads->permanent->address->city);
    $this->assertEqual("FL", $this->mads->permanent->address->state);
    $this->assertEqual("mickey@disney.com", $this->mads->permanent->email);
    $this->assertEqual("mmouse", $this->mads->netid);
  }
    
}