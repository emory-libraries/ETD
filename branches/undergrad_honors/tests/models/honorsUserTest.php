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
	  // no data
		$missing=$this->honors_user->checkRequired();
		$this->assertEqual(2,count($missing));
		$this->assertPattern("/permanent/", implode(' ', $missing));
		$this->assertPattern("/current/", implode(' ', $missing));

// permanent email only
		$this->honors_user->mads->permanent->email="test";

		$missing=$this->honors_user->checkRequired();
		$this->assertEqual(1,count($missing));
        $this->assertNoPattern("/permanent/", implode(' ', $missing));
		$this->assertPattern("/current/", implode(' ', $missing));

// current email only
		$this->honors_user->mads->permanent->email="";
		$this->honors_user->mads->current->email="test2";
		$missing=$this->honors_user->checkRequired();
		$this->assertEqual(1,count($missing));
        $this->assertPattern("/permanent/", implode(' ', $missing));
		$this->assertNoPattern("/current/", implode(' ', $missing));


// both emails
		$this->honors_user->mads->permanent->email="perm";
		$this->honors_user->mads->current->email="current";
		$missing=$this->honors_user->checkRequired();
		$this->assertEqual(0,count($missing));
        $this->assertNoPattern("/permanent/", implode(' ', $missing));
		$this->assertNoPattern("/current/", implode(' ', $missing));



  }
}

runtest(new TestHonorsUser());
?>