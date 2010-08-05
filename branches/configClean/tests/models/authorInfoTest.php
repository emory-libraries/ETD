<?php
require_once("../bootstrap.php");
require_once('models/authorInfo.php');

class TestAuthorInfo extends UnitTestCase {
    private $authorInfo;

  function setUp() {
    $fname = '../fixtures/authorInfo.xml';
    $dom = new DOMDocument();
    //    $dom->load($fname);
    $dom->loadXML(file_get_contents($fname));
    $this->authorInfo = new authorInfo($dom);
  }
  
  function tearDown() {
  }

  function testBasicProperties() {
    // test that foxml properties are accessible
    $this->assertIsA($this->authorInfo, "authorInfo");
    $this->assertIsA($this->authorInfo->dc, "dublin_core");
    $this->assertIsA($this->authorInfo->mads, "mads");
    
    $this->assertEqual("test:user1", $this->authorInfo->pid);
    $this->assertEqual("AuthorInformation", $this->authorInfo->contentModelName());
  }

  function testTemplateInit() {
      // when creating new user from template, contentModel should be added
      $authorInfo = new authorInfo();
      $this->assertEqual("AuthorInformation", $authorInfo->contentModelName());
  }


  function testNormalizeDates() {
    $this->authorInfo->mads->permanent->date = "Jan 03 2009";	   // some kind of human-readable date
    $this->authorInfo->normalizeDates();
    $this->assertEqual("2009-01-03", $this->authorInfo->mads->permanent->date);

    $this->authorInfo->mads->permanent->date = "3 January 2009";	   // some kind of human-readable date
    $this->authorInfo->normalizeDates();
    $this->assertEqual("2009-01-03", $this->authorInfo->mads->permanent->date);

    
    // when blank, shouldn't set to zero-time unix 
    $this->authorInfo->mads->permanent->date = "";
    $this->authorInfo->normalizeDates();
    $this->assertEqual("", $this->authorInfo->mads->permanent->date);
    
  }

  function testCheckAllFields() {
    // start with a new user object -  no data, all fields initially missing
    $authorInfo = new authorInfo();
    
    $missing = $authorInfo->checkAllFields();
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
    $authorInfo->mads->permanent->email = "someone@nowhere.net";
    $missing = $authorInfo->checkAllFields();
    $this->assertFalse(in_array("permanent email", $missing),
		      "complete permanent email is not missing");
    //  - current email
    $authorInfo->mads->current->email = "netid@emory.edu";
    $missing = $authorInfo->checkAllFields();
    $this->assertFalse(in_array("email", $missing),
		       "complete email is not missing");
    //  - permanent address - several components
    $authorInfo->mads->permanent->address->street[0] = "123 Some St.";
    $missing = $authorInfo->checkAllFields();
    $this->assertTrue(in_array("permanent address", $missing),
		      "incomplete permanent address detected");
    $authorInfo->mads->permanent->address->city = "Nairobi";
    $missing = $authorInfo->checkAllFields();
    $this->assertTrue(in_array("permanent address", $missing),
		      "incomplete permanent address detected");
    $authorInfo->mads->permanent->address->country = "Kenya";
    $missing = $authorInfo->checkAllFields();
    $this->assertTrue(in_array("permanent address", $missing),
		      "incomplete permanent address detected");
    $authorInfo->mads->permanent->address->postcode = "0300-4566";
    $missing = $authorInfo->checkAllFields();
    $this->assertTrue(in_array("permanent address", $missing),
		      "incomplete permanent address detected");
    $authorInfo->mads->permanent->date = "2010-10-01";
    $missing = $authorInfo->checkAllFields();
    $this->assertFalse(in_array("permanent address", $missing),
		     "complete permanent address is not missing");
    //  - name
    $authorInfo->mads->name->first =  "Someone";
    $missing = $authorInfo->checkAllFields();
    $this->assertTrue(in_array("name", $missing), "incomplete name detected");
    $authorInfo->mads->name->first =  "";
    $authorInfo->mads->name->last =  "Else";
    $missing = $authorInfo->checkAllFields();
    $this->assertTrue(in_array("name", $missing), "incomplete name detected");
    $authorInfo->mads->name->first =  "Someone";
    $authorInfo->mads->name->last =  "Else";
    $missing = $authorInfo->checkAllFields();
    $this->assertFalse(in_array("name", $missing), "complete name - not missing");
    
  }


}

runtest(new TestAuthorInfo());
?>