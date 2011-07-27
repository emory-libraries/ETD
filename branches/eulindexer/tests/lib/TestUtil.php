<?php

require_once("../bootstrap.php");

class TestUtil extends UnitTestCase {


  function testValueInConfig() {
    // configurations for testing
    $grad_cfg = array("emory_college" => array("admin" => array("netid" => array("llane", "mshonorable"),
                             "department" => "College")));
      $grad = new Zend_Config($grad_cfg);

      //Single value in config
      $this->assertTrue(valueInConfig("College", $grad->emory_college->admin->department));

      //Array of values in config
      $this->assertTrue(valueInConfig("llane", $grad->emory_college->admin->netid));
      $this->assertTrue(valueInConfig("mshonorable", $grad->emory_college->admin->netid));

      //Value not in config
      $this->assertFalse(valueInConfig("fakeUser", $grad->emory_college->admin->netid));

      //Element not in config
      $this->assertFalse(valueInConfig("bla bla bla", $grad->emory_college->admin->doesnotexist));
  }

}

runtest(new TestUtil());