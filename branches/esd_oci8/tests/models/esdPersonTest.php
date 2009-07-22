<?php
    require_once("../bootstrap.php");
    require_once('models/esdPerson.php');

    /**
     NOTE: using rsutton account for testing - test will fail when this account is no longer in ESD
     */

    class TestEsdPerson extends UnitTestCase {
      private $esd;
      private $user;

      function setUp() {
        //    $this->esd = new esdPersonObject();

        $this->esd = new esdPersonObject();
        // initialize non-db test person with blank data
        $person = new esdPerson();
        $this->user = $person->getTestPerson();
      }

      function tearDown() {}

      function testBasicProperties() {
        $user = $this->esd->findByUsername("rsutton");

        $this->assertIsA($user, "esdPerson");	// temporary
        $this->assertIsA($user, "Zend_Acl_Role_Interface");
        // test acessing field via db column name
        $this->assertEqual($user->LOGN8NTWR_I, "RSUTTON");
        // test that human-readable aliases work for accessing fields
        $this->assertEqual($user->netid, "rsutton");
        $this->assertEqual($user->id, "139060");
        $this->assertEqual($user->type, "E");
        $this->assertEqual($user->lastname, "Koeser");
        $this->assertEqual($user->firstname, "Rebecca");
        $this->assertEqual($user->directory_name, "Rebecca Sutton");
        $this->assertEqual($user->department_code, "2870");
        $this->assertEqual($user->department, "University Libraries");
        $this->assertEqual($user->email, "rebecca.s.koeser@emory.edu");

        // NOTE: this test is useless; value returned by ESD oscillates between MA and PHD for no apparent reason
        //    $this->assertEqual($user->degree, "PHD");	// FIXME: why does this change?
        // these fields are now empty in ESD (?) - find better test subject ?
        // OR figure out how to mock the data (? is that a useful test?)
        //    $this->assertEqual($user->term_complete, "5061");
        //    $this->assertEqual($user->academic_plan_id, "ENGLISHMA");
        //    $this->assertEqual($user->academic_plan, "English");
        //    $this->assertEqual($user->academic_career, "GSAS");
        $this->assertNull($user->program_coord);

        $this->assertEqual($user->role, "superuser");
      }

      function testFindNonexistent() {
        $user = $this->esd->findByUsername("nonexistent");
        $this->assertFalse($user);
      }

      function testRole() {        
        $this->user->netid = "jsmith";
        // various student types (includes student/staff)
        $this->user->type = "B";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "student");
        $this->user->type = "S";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "student");
        $this->user->type = "C";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "student");
        // faculty
        $this->user->type = "F";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "faculty");
        // staff
        $this->user->type = "E";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "staff");

        /* special cases */

        // graduate school administration
        $this->user->department = "Graduate School Administration";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "grad admin");

        // honors undergrad student
        $this->user->department = "Anthropology";
        $this->user->type = "S";
        $this->user->honors_student = "Y";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "honors student");

        // honors program administrator - based on config file
        $this->user->netid = "mabell";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "honors admin");

        // etd superuser - based on config file
        $this->user->netid = "rsutton";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "superuser");
      }

      function testProgramCoordinator(){
        $this->user->grad_coord = "English";
        $this->assertTrue($this->user->isCoordinator("English"), "");
        $this->assertFalse($this->user->isCoordinator("Chemistry"));

        // blank coordinator & program should NOT match
        $this->user->grad_coord = "";
        $this->assertFalse($this->user->isCoordinator(""));
        $this->user->grad_coord = null;
        $this->assertFalse($this->user->isCoordinator(null));
      }

      function testFormerFaculty_id() {
        $this->user->current_faculty = "N";
        $this->user->id = "007";
        $this->assertEqual("esdid007", $this->user->netid);
      }

      function testfindById() {
        $user = $this->esd->findByUsername("rsutton");
        $user2 = $this->esd->findByUsername("esdid" . $user->id);
        $this->assertEqual($user, $user2);
      }

      function testGetGenericAgent() {
        $this->user->role = "grad admin";
        $this->assertEqual("the Graduate School", $this->user->getGenericAgent());
        $this->user->role = "honors admin";
        $this->assertEqual("the College Honors Program", $this->user->getGenericAgent());
        $this->user->role = "admin";
        $this->assertEqual("ETD Administrator", $this->user->getGenericAgent());
        $this->user->role = "superuser";
        $this->assertEqual("ETD Administrator", $this->user->getGenericAgent());

        // role with no generic agent
        $this->user->role = "guest";
        // should get a warning about this
        $this->expectError("This role (guest) does not have a generic agent defined");
        $this->assertEqual("?", $this->user->getGenericAgent());

      }
       function testPassword() {
           $this->user->setPassword("pass!@#$");
           $this->assertEqual("pass!@#$", $this->user->getPassword());
       }


       function testInitWithoutESD() {
          $user = $this->esd->initializeWithoutEsd("testuser");
          $this->assertIsA($user, "esdPerson");
          $this->assertEqual("testuser", $user->netid);
          $this->assertEqual("guest", $user->getRoleId());
       }

       function testMatchFaculty() {
           $matches = $this->esd->match_faculty("Ron Schuchard");
           $this->assertIsA($matches, "esdPeople");
           $this->assertIsA($matches[0], "esdPerson");
           $this->assertEqual(2, count($matches));
           $this->assertEqual($matches[1]->netid, "engrs");
       }

       function testFindFacultyByName() {
           $result = $this->esd->findFacultyByName("Michael Elliot");
           $this->assertIsA($result, "esdPerson");
           $this->assertEqual($result->netid, "mellio2");
       }

       function testFindAddress() {
           $person = $this->esd->findByUsername("roza");
           $adr = new esdAddressObject();
           $address = $adr->find($person->id);
           $this->assertIsA($address, "esdAddressInfo");
           $this->assertIsA($address->current, "esdAddress");
           $this->assertIsA($address->permanent, "esdAddress");
           // FIXME: test with actual data
       }



    }

    runtest(new TestEsdPerson());
    ?>