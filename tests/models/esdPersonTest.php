<?php
require_once("../bootstrap.php");
require_once('models/esdPerson.php');

/**
 NOTE: using rsutton account for testing - test will fail when this account is no longer in ESD
 */

class TestEsdPerson extends UnitTestCase {
  private $esd;

  function setUp() {
    $this->esd = new esdPersonObject();
  }

  function tearDown() {}

  function testBasicProperties() {
    $user = $this->esd->findByUsername("rsutton");
    
    $this->assertIsA($user, "esdPerson");
    // test that human-readable aliases work for accessing fields
    $this->assertEqual($user->netid, "rsutton");
    $this->assertEqual($user->type, "E");
    $this->assertEqual($user->lastname, "Koeser");
    $this->assertEqual($user->firstname, "Rebecca");
    $this->assertEqual($user->directory_name, "Rebecca Sutton");
    $this->assertEqual($user->department_code, "2870");
    $this->assertEqual($user->department, "University Libraries");
    $this->assertEqual($user->academic_career, "GSAS");
    $this->assertEqual($user->academic_program, null);
    $this->assertEqual($user->degree, "PHD");
    $this->assertEqual($user->term_complete, "5061");
    $this->assertEqual($user->email, "rsutton@emory.edu");
    $this->assertEqual($user->academic_plan_id, "ENGLISHMA");
    $this->assertEqual($user->academic_plan, "English");

    // FIXME: temporary data in ESD to simulate grad program coordinator stuff
    $this->assertEqual($user->program_coord, "English");

    $this->assertEqual($user->role, "superuser");
  }

  function testFindNonexistent() {
    $user = $this->esd->findByUsername("nonexistent");
    $this->assertFalse($user);
  }

  function testRole() {
    $user = new esdPerson();
    // various student types (includes student/staff)
    $user->type = "B";
    $user->setRole();
    $this->assertEqual($user->role, "student");
    $user->type = "S";
    $user->setRole();
    $this->assertEqual($user->role, "student");
    $user->type = "C";
    $user->setRole();
    $this->assertEqual($user->role, "student");
    // faculty
    $user->type = "F";
    $user->setRole();
    $this->assertEqual($user->role, "faculty");
    // staff
    $user->type = "E";
    $user->setRole();
    $this->assertEqual($user->role, "staff");

    /* special cases */

    // graduate school administration
    $user->department = "Graduate School Administration";
    $user->setRole();
    $this->assertEqual($user->role, "admin");

    // etd superuser - based on config file
    $user->netid = "rsutton";
    $user->setRole();
    $this->assertEqual($user->role, "superuser");
  }

  function testProgramCoordinator(){
    $user = new esdPerson();
    $user->grad_coord = "English";
    $this->assertTrue($user->isCoordinator("English"));
    $this->assertFalse($user->isCoordinator("Chemistry"));
  }

  // FIXME: add tests for functions that find faculty names
    
}

runtest(new TestEsdPerson());
?>