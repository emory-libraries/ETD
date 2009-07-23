<?php
require_once("../bootstrap.php");
require_once('models/mads.php');

class TestMads extends UnitTestCase {
  private $mads;

  function setUp() {
    $xml = new DOMDocument();
    $xml->load("../fixtures/mads.xml");
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
    $this->assertEqual("123 Disney Lane", $this->mads->permanent->address->street[0]);
    $this->assertEqual("Disney World", $this->mads->permanent->address->city);
    $this->assertEqual("FL", $this->mads->permanent->address->state);
    $this->assertEqual("mickey@disney.com", $this->mads->permanent->email);
    $this->assertEqual("mmouse", $this->mads->netid);
  }


  function testInitFromEsd() {
    $esd = new esdPersonObject();
    $testuser = $esd->findByUsername("roza");
    $this->mads->initializeFromEsd($testuser);

    // FIXME: use test data!!!
    $this->assertEqual("roza", $this->mads->netid);
    $this->assertEqual("Reena", $this->mads->name->first);
    $this->assertEqual("Oza-Frank", $this->mads->name->last);
    // no academic plan ?
    $this->assertEqual("", $this->mads->current->organization);
    $this->assertEqual("roza@emory.edu", $this->mads->current->email);
    // current address information (no data?)
    
    
  }

  function testSetAddressFromEsd() {
    $addr = new esdAddress();
    $addr->street = array("101 Main St.");
    $addr->city = "Atlanta";
    $addr->state = "GA";
    $addr->zip = "30323";
    $addr->country = "USA";
    $addr->telephone = "404-123-3433";  
    $this->mads->setAddressFromEsd($this->mads->current, $addr);
    $this->assertEqual("101 Main St.", $this->mads->current->address->street[0]);
    $this->assertEqual("Atlanta", $this->mads->current->address->city);
    $this->assertEqual("GA", $this->mads->current->address->state);
    $this->assertEqual("USA", $this->mads->current->address->country);
    $this->assertEqual("30323", $this->mads->current->address->postcode);
    $this->assertEqual("404-123-3433", $this->mads->current->phone);
  }
    
    
}

runtest(new TestMads());
?>