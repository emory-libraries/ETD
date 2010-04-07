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
    $this->assertEqual("AuthorInformation", $this->user->contentModelName());
  }

  function testTemplateInit() {
      // when creating new user from template, contentModel should be added
      $user = new User();
      $this->assertEqual("AuthorInformation", $user->contentModelName());
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

  function testCheckAllFields() {
    // start with a new user object -  no data, all fields initially missing
    $user = new user();
    
    $missing = $user->checkAllFields();
    // check that these are present anywhere in the list (order not important)
    $this->assertTrue(in_array("name", $missing),"missing name detected");
    $this->assertTrue(in_array("permanent address", $missing),
		      "incomplete permanent address detected");
    $this->assertTrue(in_array("email", $missing),
		      "incomplete email detected");
    $this->assertTrue(in_array("permanent email", $missing),
		      "incomplete permanent email detected");

    // fill in fields one at a time and check that they are correctly detected
    //  - permanent email
    $user->mads->permanent->email = "someone@nowhere.net";
    $missing = $user->checkAllFields();
    $this->assertFalse(in_array("permanent email", $missing),
		      "complete permanent email is not missing");
    //  - current email
    $user->mads->current->email = "netid@emory.edu";
    $missing = $user->checkAllFields();
    $this->assertFalse(in_array("email", $missing),
		       "complete email is not missing");
    //  - permanent address - several components
    $user->mads->permanent->address->street[0] = "123 Some St.";
    $missing = $user->checkAllFields();
    $this->assertTrue(in_array("permanent address", $missing),
		      "incomplete permanent address detected");
    $user->mads->permanent->address->city = "Nairobi";
    $missing = $user->checkAllFields();
    $this->assertTrue(in_array("permanent address", $missing),
		      "incomplete permanent address detected");
    $user->mads->permanent->address->country = "Kenya";
    $missing = $user->checkAllFields();
    $this->assertTrue(in_array("permanent address", $missing),
		      "incomplete permanent address detected");
    $user->mads->permanent->address->postcode = "0300-4566";
    $missing = $user->checkAllFields();
    $this->assertTrue(in_array("permanent address", $missing),
		      "incomplete permanent address detected");
    $user->mads->permanent->date = "2010-10-01";
    $missing = $user->checkAllFields();
    $this->assertFalse(in_array("permanent address", $missing),
		     "complete permanent address is not missing");
    //  - name
    $user->mads->name->first =  "Someone";
    $missing = $user->checkAllFields();
    $this->assertTrue(in_array("name", $missing), "incomplete name detected");
    $user->mads->name->first =  "";
    $user->mads->name->last =  "Else";
    $missing = $user->checkAllFields();
    $this->assertTrue(in_array("name", $missing), "incomplete name detected");
    $user->mads->name->first =  "Someone";    
    $user->mads->name->last =  "Else";
    $missing = $user->checkAllFields();
    $this->assertFalse(in_array("name", $missing), "complete name - not missing");
    
  }


}

runtest(new TestUser());
?>