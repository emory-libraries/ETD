<?php
require_once("../bootstrap.php");
require_once('models/mads.php');
require_once("fixtures/esd_data.php");

class TestMads extends UnitTestCase {
  private $mads;
  private $data;

  function setUp() {
    $xml = new DOMDocument();
    $xml->load("../fixtures/mads.xml");
    $this->mads = new mads($xml);

    $this->data = new esd_test_data();
    $this->data->loadAll();
  }

  function tearDown() {
    $this->data->cleanUp();
  }

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
    $testuser = $esd->findByUsername("mstuden");
    $this->mads->initializeFromEsd($testuser);

    $this->assertEqual("mstuden", $this->mads->netid);
    $this->assertEqual("Mary", $this->mads->name->first);
    $this->assertEqual("Student", $this->mads->name->last);
    $this->assertEqual("Bioscience", $this->mads->current->organization);
    $this->assertEqual("m.student@emory.edu", $this->mads->current->email);
    // current address
    $this->assertEqual("5544 Rambling Road", $this->mads->current->address->street[0]);
    $this->assertEqual("Apt. 3C", $this->mads->current->address->street[1]);
    $this->assertEqual("c/o Spot", $this->mads->current->address->street[2]);
    $this->assertEqual("Atlanta", $this->mads->current->address->city);
    $this->assertEqual("GA", $this->mads->current->address->state);
    $this->assertEqual("30432", $this->mads->current->address->postcode);
    $this->assertEqual("USA", $this->mads->current->address->country);
    $this->assertEqual("4041234566", $this->mads->current->phone);
    $this->assertEqual(date("Y-m-d"), $this->mads->current->date);
    // permanent address
    $this->assertEqual("346 Lamplight Trail", $this->mads->permanent->address->street[0]);
    $this->assertEqual("Suite #2", $this->mads->permanent->address->street[1]);
    $this->assertEqual("Rm. 323", $this->mads->permanent->address->street[2]);
    $this->assertEqual("Cleveland", $this->mads->permanent->address->city);
    $this->assertEqual("OH", $this->mads->permanent->address->state);
    $this->assertEqual("99304", $this->mads->permanent->address->postcode);
    $this->assertEqual("545234566", $this->mads->permanent->phone);
    $this->assertEqual("CA", $this->mads->permanent->address->country);
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