<?php
require_once("../bootstrap.php");
require_once('models/user.php');

class TestUser extends UnitTestCase {
    private $user;

  function setUp() {
    $fname = '../fixtures/user.xml';
    $dom = new DOMDocument();
    //    $dom->load($fname);
    $dom->loadXML(file_get_contents($fname));
    $this->user = new user($dom);
  }
  
  function tearDown() {
  }

  function testBasicProperties() {
    // test that foxml properties are accessible
    $this->assertIsA($this->user, "user");
    $this->assertIsA($this->user->dc, "dublin_core");
    $this->assertIsA($this->user->mads, "mads");
    
    $this->assertEqual("test:user1", $this->user->pid);
    $this->assertEqual("user", $this->user->cmodel);
  }

  function testNormalizeDates() {
    $this->user->mads->permanent->date = "Jan 03 2009";	   // some kind of human-readable date
    $this->user->normalizeDates();
    $this->assertEqual("2009-01-03", $this->user->mads->permanent->date);

    $this->user->mads->permanent->date = "3 January 2009";	   // some kind of human-readable date
    $this->user->normalizeDates();
    $this->assertEqual("2009-01-03", $this->user->mads->permanent->date);

    
    // when blank, shouldn't set to zero-time unix 
    $this->user->mads->permanent->date = "";
    $this->user->normalizeDates();
    $this->assertEqual("", $this->user->mads->permanent->date);
    
  }

  function testCheckRequired() {
    // start with a new user object -  no data, all fields initially missing
    $user = new user();
    
    $missing = $user->checkRequired();
    // check that these are present anywhere in the list (order not important)
    $this->assertTrue(in_array("permanent address", $missing),
		      "incomplete permanent address detected");
    $this->assertTrue(in_array("email", $missing),
		      "incomplete email detected");
    $this->assertTrue(in_array("permanent email", $missing),
		      "incomplete permanent email detected");

    // fill in fields one at a time and check that they are correctly detected
    //  - permanent email
    $user->mads->permanent->email = "someone@nowhere.net";
    $missing = $user->checkRequired();
    $this->assertFalse(in_array("permanent email", $missing),
		      "complete permanent email is not missing");
    //  - current email
    $user->mads->current->email = "netid@emory.edu";
    $missing = $user->checkRequired();
    $this->assertFalse(in_array("email", $missing),
		       "complete email is not missing");
    //  - permanent address - several components
    $user->mads->permanent->address->street[0] = "123 Some St.";
    $missing = $user->checkRequired();
    $this->assertTrue(in_array("permanent address", $missing),
		      "incomplete permanent address detected");
    $user->mads->permanent->address->city = "Nairobi";
    $missing = $user->checkRequired();
    $this->assertTrue(in_array("permanent address", $missing),
		      "incomplete permanent address detected");
    $user->mads->permanent->address->country = "Kenya";
    $missing = $user->checkRequired();
    $this->assertTrue(in_array("permanent address", $missing),
		      "incomplete permanent address detected");
    $user->mads->permanent->address->postcode = "0300-4566";
    $missing = $user->checkRequired();
    $this->assertTrue(in_array("permanent address", $missing),
		      "incomplete permanent address detected");
    $user->mads->permanent->date = "2010-10-01";
    $missing = $user->checkRequired();
    $this->assertFalse(in_array("permanent address", $missing),
		     "complete permanent address is not missing");
  }

  function testIsRequired() {
    $this->assertTrue($this->user->isRequired("email"), "email is required");
    $this->assertTrue($this->user->isRequired("permanent email"), "permanent email is required");
    $this->assertTrue($this->user->isRequired("permanent address"),
		      "permanent address is required");
    $this->assertFalse($this->user->isRequired("bogus"), "unknown field is not required");
  } 
   

}

runtest(new TestUser());
?>