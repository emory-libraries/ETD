<?php
require_once("../bootstrap.php");
require_once('models/SchoolsConfig.php');
require_once("fixtures/etd_data.php");

class TestSchoolsConfig extends UnitTestCase {
  private $schools;
  private $etd_data;
  
  function setUp() {
    $this->schools = Zend_Registry::get("schools-config");

    //load etd database fixtures
    $this->etd_data = new etd_test_data();
    $this->etd_data->loadAll();
  }

  function tearDown() {
      // clear etd database test data
      $this->etd_data->cleanUp();
    }

  
  function testGetLabelById() {
    $this->assertEqual("Graduate School", $this->schools->getLabel("graduate_school"));
    $this->assertEqual("College Honors Program", $this->schools->getLabel("emory_college"));
    $this->assertEqual("Candler School of Theology", $this->schools->getLabel("candler"));
    $this->assertEqual("Rollins School of Public Health", $this->schools->getLabel("rollins"));    

    $this->assertNull($this->schools->getLabel("bogus"));
  }

  function testGetIdByCollection() {
    $this->assertEqual("graduate_school", $this->schools->getIdByFedoraCollection("emory-control:ETD-GradSchool-collection"));
    $this->assertEqual("emory_college", $this->schools->getIdByFedoraCollection("emory-control:ETD-College-collection"));
    $this->assertEqual("candler", $this->schools->getIdByFedoraCollection("emory-control:ETD-Candler-collection"));
    $this->assertEqual("rollins", $this->schools->getIdByFedoraCollection("emory-control:ETD-Rollins-collection"));

    $this->assertNull($this->schools->getIdByFedoraCollection("bogus"));
  }

  function testGetIdByDatabaseId() {
    $this->assertEqual("graduate_school", $this->schools->getIdByDatabaseId("GSAS"));
    $this->assertEqual("emory_college", $this->schools->getIdByDatabaseId("UCOL"));
    $this->assertEqual("candler", $this->schools->getIdByDatabaseId("THEO"));
    $this->assertEqual("rollins", $this->schools->getIdByDatabaseId("PUBH"));    

    $this->assertNull($this->schools->getIdByDatabaseId("BOGUS"));
  }


  function testIsAdmin() {
    $person = new esdPerson();
    $user = $person->getTestPerson();

    $schools = $this->schools;

    // override admin configurations for testing
    $schools->graduate_school->admin = array("netid" => array("gadmin"),
                             "department" => "Grad. School");

    $schools->emory_college->admin = array("netid" => array("llane", "mshonorable"),
                             "department" => "College");

    $schools->candler->admin = array("netid" => array("mcando"),
                             "department" => "Candler School of Theology");

    $schools->candler->admin->department = "Candler School of Theology";
    
    //grad
    $user->netid="gadmin";
    $user->department = "Grad. School";
    $this->assertEqual("grad", $schools->isAdmin($user));
    // honors 
    $user->netid = "llane";
    $user->department = "College";
    $this->assertEqual("honors", $schools->isAdmin($user));
    $user->netid = "mshonorable";
    $this->assertEqual("honors", $schools->isAdmin($user));
    // candler (by dept. only)
    $user->department = "Candler School of Theology";
    $user->netid = "mcando";
    // blank out job title field for initial test
    $schools->candler->admin->job_title = "";
    $this->assertEqual("candler", $schools->isAdmin($user));
    //rollins
    $user->netid="epusr";
    $this->assertEqual("rollins", $schools->isAdmin($user));

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
    
    $school = $this->schools->getSchoolByAclId("rollins");
    $this->assertIsA($school, "Zend_Config");
    $this->assertEqual("Rollins School of Public Health", $school->label);    

    $this->assertNull($this->schools->getSchoolByAclId("bogus"));
  }
}

runtest(new TestSchoolsConfig());
?>
