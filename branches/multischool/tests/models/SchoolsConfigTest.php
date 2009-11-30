<?php
require_once("../bootstrap.php");
require_once('models/SchoolsConfig.php');

class TestSchoolsConfig extends UnitTestCase {
  private $schools;
  
  function setUp() {
    $this->schools = Zend_Registry::get("schools-config");
  }
  
  function testGetLabelById() {
    $this->assertEqual("Graduate School", $this->schools->getLabel("graduate_school"));
    $this->assertEqual("College Honors Program", $this->schools->getLabel("emory_college"));
    $this->assertEqual("Candler School of Theology", $this->schools->getLabel("candler"));

    $this->assertNull($this->schools->getLabel("bogus"));
  }

  function testGetIdByCollection() {
    $this->assertEqual("graduate_school", $this->schools->getIdByFedoraCollection("emory:ETD-GradSchool-collection"));
    $this->assertEqual("emory_college", $this->schools->getIdByFedoraCollection("emory:ETD-College-collection"));
    $this->assertEqual("candler", $this->schools->getIdByFedoraCollection("emory:ETD-Candler-collection"));

    $this->assertNull($this->schools->getIdByFedoraCollection("bogus"));
  }

  function testGetIdByDatabaseId() {
    $this->assertEqual("graduate_school", $this->schools->getIdByDatabaseId("GSAS"));
    $this->assertEqual("emory_college", $this->schools->getIdByDatabaseId("UCOL"));
    $this->assertEqual("candler", $this->schools->getIdByDatabaseId("THEO"));

    $this->assertNull($this->schools->getIdByDatabaseId("BOGUS"));
  }


  function testIsAdmin() {
    $person = new esdPerson();
    $user = $person->getTestPerson();

    $schools = $this->schools;
    // override admin configurations for testing
    $schools->graduate_school->admin->department = "Grad. School";
    $schools->emory_college->admin = array("netid" =>
					   array("llane", "mshonorable"));

    //grad
    $user->department = "Grad. School";
    $this->assertEqual("grad", $schools->isAdmin($user));
    // honors 
    $user->netid = "llane";
    $user->department = "College";
    $this->assertEqual("honors", $schools->isAdmin($user));
    $user->netid = "mshonorable";
    $this->assertEqual("honors", $schools->isAdmin($user));
    // candler
    // @todo test detecting candler admin; currently no configuration for this

    // generic user 
    $student = $person->getTestPerson();
    $this->assertFalse($schools->isAdmin($student));

  }

  function testGetSchoolByAclId() {
    $school = $this->schools->getSchoolByAclId("grad");
    $this->assertIsA($school, "Zend_Config");
    $this->assertEqual("Graduate School", $school->label);
    
    $school = $this->schools->getSchoolByAclId("honors");
    $this->assertIsA($school, "Zend_Config");
    $this->assertEqual("College Honors Program", $school->label);
    
    $school = $this->schools->getSchoolByAclId("candler");
    $this->assertIsA($school, "Zend_Config");
    $this->assertEqual("Candler School of Theology", $school->label);

    $this->assertNull($this->schools->getSchoolByAclId("bogus"));
  }

  
}

runtest(new TestSchoolsConfig());
?>