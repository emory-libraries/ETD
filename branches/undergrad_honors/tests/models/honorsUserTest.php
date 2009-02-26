<?php
require_once("../bootstrap.php");
require_once('models/honors_user.php');

class TestHonorsUser extends UnitTestCase {
    private $honors_user;

  function setUp() {
    //$fname = '../fixtures/user.xml';
    //$dom = new DOMDocument();
    //    $dom->load($fname);
    //$dom->loadXML(file_get_contents($fname));
    $this->honors_user = new honors_user();
  }
  
  function tearDown() {
  }

 
  function testCheckRequired() {
    // starting with an empty object - no data, all required fields are missing
    $missing = $this->honors_user->checkRequired();
    // check that these are present anywhere in the list (order not important)
    $this->assertTrue(in_array("permanent email", $missing), "permanent email is missing");
    $this->assertTrue(in_array("email", $missing), "current email is missing");
    $this->assertFalse(in_array("permanent address", $missing), "permanent address is not missing (not required)");

    // fill in data to check that it is detected correctly
    // permanent email only
    $this->honors_user->mads->permanent->email = "test@no.net";
    $missing = $this->honors_user->checkRequired();
    $this->assertFalse(in_array("permanent email", $missing), "complete permanent email detected");
    $this->assertTrue(in_array("email", $missing), "curren email is still missing");
    
    // current email only
    $this->honors_user->mads->permanent->email = "";
    $this->honors_user->mads->current->email = "test2@yes.com";
    $missing = $this->honors_user->checkRequired();
    $this->assertTrue(in_array("permanent email", $missing), "permanent email missing");
    $this->assertFalse(in_array("email", $missing), "complete email detected");
    
    // both emails set
    $this->honors_user->mads->permanent->email = "perm@ane.net";
    $this->honors_user->mads->current->email = "cu@rr.ent";
    $missing = $this->honors_user->checkRequired();
    $this->assertEqual(0,count($missing));	// nothing missing
    $this->assertFalse(in_array("permanent", $missing), "complete permanent email not missing");
    $this->assertFalse(in_array("current", $missing), "complete current email not missing");
  }

  function testIsRequired() {
    // isrequired function is inherited, but should use honors user list of required fields
    $this->assertTrue($this->honors_user->isRequired("email"),
		      "email is required");
    $this->assertTrue($this->honors_user->isRequired("permanent email"),
		      "permanent email is required");
    $this->assertFalse($this->honors_user->isRequired("permanent address"),
		      "permanent address is not required for honors user");

  }
}

runtest(new TestHonorsUser());
?>