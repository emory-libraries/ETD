<?php

require_once('models/user.php');

class TestUser extends UnitTestCase {
    private $user;

  function setUp() {
    $fname = 'fixtures/user.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
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

}

