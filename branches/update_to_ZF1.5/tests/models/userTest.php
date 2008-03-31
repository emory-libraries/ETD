<?php

require_once('models/user.php');

class TestUser extends UnitTestCase {
    private $user;

  function setUp() {
    $fname = 'fixtures/user.xml';
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

}

