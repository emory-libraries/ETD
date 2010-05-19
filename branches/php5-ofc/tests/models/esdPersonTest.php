<?php
    require_once("../bootstrap.php");
    require_once('models/esdPerson.php');
    require_once("fixtures/esd_data.php");

    class TestEsdPerson extends UnitTestCase {
      private $esd;
      private $user;
      private $data;

      private $_realconfig;
      private $_schools_config;

      
      private $grad_department = "Laney Graduate School Administration";


      function setUp() {
        $this->esd = new esdPersonObject();
        // initialize non-db test person with blank data
        $person = new esdPerson();
        $this->user = $person->getTestPerson();

	$this->data = new esd_test_data();
	$this->data->loadAll();

	// store real config files to restore later
	$this->_realconfig = Zend_Registry::get('config');
	$this->_schools_config = Zend_Registry::get('schools-config');
	
	// stub config with just the portion relevant to special user roles
	$testconfig = new Zend_Config(array("techsupport" => array("user" => array("jolsen")),
					    "superusers" => array("user" => array("ckent")),
                        "reportviewer" => array("department" => array("Reports Department")),
					    ));
	// temporarily override config with test configuration
	Zend_Registry::set('config', $testconfig);

	// add test users to school config
	$schools = $this->_schools_config;
	// override admin configurations for testing
	 $schools->graduate_school->admin = array("netid" => array('gadmin', 'jsmith'),
                                              "department" => $this->grad_department);

    $schools->emory_college->admin = array("netid" =>
					   array("llane", "mshonorable"),
                             "department" => "Emory College");

	// temporarily override school config  with test configuration
	Zend_Registry::set('schools-config', $schools);
      }

      function tearDown() {
	// restore real config to registry
	Zend_Registry::set('config', $this->_realconfig);
	Zend_Registry::set('schools-config', $this->_schools_config);
	
	$this->data->cleanUp();
      }

      function testBasicProperties() {
	$user = $this->esd->findByUsername("pstaff");
	
        $this->assertIsA($user, "esdPerson", "object returned by findByUsername is type 'esdPerson'");  
        $this->assertIsA($user, "Zend_Acl_Role_Interface", "user object implements Zend_Acl_Role_Interface");
        // test acessing field via db column name
        $this->assertEqual($user->LOGN8NTWR_I, "PSTAFF", "user LOGN8NTWR_I should be 'PSTAFF', got " . $user->LOGN8NTWR_I);
        // test that human-readable aliases work for accessing fields
        $this->assertEqual($user->netid, "pstaff", "netid should be 'pstaff', got " . $user->netid);
        $this->assertEqual($user->id, "1", "id should be 1, got " . $user->id);
        $this->assertEqual($user->type, "E", "type should be 'E', got " .  $user->type);
        $this->assertEqual($user->lastname, "Staff", "lastname should be 'Staff', got " . $user->lastname);
        $this->assertEqual($user->firstname, "Person", "firstname should be 'Person', got " . $user->firstname);
        $this->assertEqual($user->directory_name, "A. Person", "directory name should be 'A. Person', got " . $user->directory_name);
        $this->assertEqual($user->department_code, "1000", "dept. code should be 1000, got " . $user->department_code);
        $this->assertEqual($user->department, "University Libraries", "department should be 'University Libraries', got " . $user->department);
        $this->assertEqual($user->email, "person.staff@emory.edu", "email should be 'person.staff@emory.edu', got " . $user->email);
        $this->assertNull($user->program_coord, "staff user should not be program coordinator");
	$this->assertFalse($user->isCoordinator("Chemistry"), "staff user is not program coordinator for Chemistry");
        $this->assertEqual($user->role, "staff", "staff user role should be 'staff', got " . $user->role);
	$this->assertNull($user->address, "staff user address should be null");

	// magic properties constructed from other fields
	$this->assertEqual($user->fullname, "A. Person Staff", "fullname should be 'A. Person Staff', got " . $user->fullname);
	$this->assertEqual($user->lastnamefirst, "Staff, A. Person", "lastnamfirst should be 'Staff, A. Person', got " . $user->lastnamefirst);
	$this->assertEqual($user->name, "A. Person", "name should be 'A. Person', got " . $user->name);

	// use alternate user account for student-only info
	$user = $this->esd->findByUsername("mstuden");
	$this->assertEqual($user->degree, "PHD", "degree should be 'PHD', got " . $user->degree);     
	$this->assertEqual($user->academic_plan_id, "BBSPHD", "academic plan id should be 'BBSPHD', got " . $user->academic_plan_id);
	$this->assertEqual($user->academic_plan, "Bioscience", "academic plan should be 'Bioscience', got " . $user->academic_plan);
	$this->assertEqual($user->academic_career, "GSAS", "academic career should be 'GSAS', got " . $user->academic_career);
	$this->assertEqual("N", $user->honors_student, "honors student flag should be 'N', got " . $user->honors_student);
	$this->assertEqual($user->name, "Mary Kim", "name should be 'Mary Kim', got " . $user->name);

	// users with special roles / permissions
	$user = $this->esd->findByUsername("gadmin");
	// override department with test name and recalculate role
	$user->department = $this->grad_department;
	$user->setRole();
	$this->assertEqual("grad admin", $user->getRoleId(), "grad admin user role should be 'grad admin', got " . $user->getRoleId());

	$user = $this->esd->findByUsername("dadmin");
	$this->assertEqual("staff", $user->getRoleId(), "dept. admin user role should be 'staff', got " . $user->getRoleId());
	$this->assertTrue($user->isCoordinator("Philosophy"), "dept. admin user should be coordinator for Philosophy");

      }

      function testfindByUsername_studentEmployee() {
	$user = $this->esd->findByUsername("jstuden");
	$this->assertNotNull($user->academic_career,
			     "info for student employee with 2 ESD records should include academic career");
      }

      function testFindNonexistent() {
        $user = $this->esd->findByUsername("nonexistent");
        $this->assertFalse($user, "searching non-existent user returns nothing");
      }

      function testRole() {        
        $this->user->netid = "jsmith";
        // various student types (includes student/staff)
        $this->user->type = "B";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "student", "person code B = role 'student', got " . $this->user->role);
        $this->user->type = "S";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "student", "person code S = role 'student', got " . $this->user->role);
        $this->user->type = "C";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "student", "person code C = role 'student', got " . $this->user->role);
        // faculty
        $this->user->type = "F";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "faculty", "person code F = role 'faculty', got " . $this->user->role);
        // staff
        $this->user->type = "E";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "staff", "person code E = role 'staff', got " . $this->user->role);

        /* special cases */

        // graduate school administration
        $this->user->department = $this->grad_department;
        $this->user->setRole();
        $this->assertEqual($this->user->role, "grad admin");

        // honors undergrad student
        $this->user->department = "Anthropology";
        $this->user->type = "S";
        $this->user->honors_student = "Y";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "honors student");

        // honors program administrator - based on *school* config 
        $this->user->netid = "llane";
        $this->user->department = "Emory College";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "honors admin");

        // etd superuser - based on config 
        $this->user->netid = "ckent";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "superuser");

        // etd report viewer - based on config
        $viewer = $this->esd->findByUsername("rviewer");
        $this->assertEqual($viewer->role, "report viewer");
        

	// tech support - based on config 
        $this->user->netid = "jolsen";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "techsupport");
      }

      function testRole_single_entry() {
	// gracefully handle multiple *and* single entries in config file usernames
	$testconfig = new Zend_Config(array("techsupport" => array("user" => "jolsen"),
					    "superusers" => array("user" => "ckent"),
                        "reportviewer" => array("department" => "Reports Department")
					    ));
	// temporarily override config with test configuration
	Zend_Registry::set('config', $testconfig);
	
        // etd superuser - based on config 
        $this->user->netid = "ckent";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "superuser");

	// tech support - based on config 
        $this->user->netid = "jolsen";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "techsupport");

	// add test users to school config
	$school_config = $this->_schools_config->toArray();
	// single entry instead of multiple format
	$school_config["emory_college"]["admin"]["netid"] = "llane";

        // honors program administrator - based on *school* config 
        $this->user->netid = "llane";
        $this->user->department = "Emory College";
        $this->user->setRole();
        $this->assertEqual($this->user->role, "honors admin");

        // etd report viewer - based on config
        $viewer = $this->esd->findByUsername("rviewer");
        $this->assertEqual($viewer->role, "report viewer");

      }

      function testProgramCoordinator(){
        $this->user->grad_coord = "English";
        $this->assertTrue($this->user->isCoordinator("English"), "");
        $this->assertFalse($this->user->isCoordinator("Chemistry"));

        // blank coordinator & program should NOT match
        $this->user->grad_coord = "";
        $this->assertFalse($this->user->isCoordinator(""), "isCoordinator with empty-string arg should be false");
        $this->user->grad_coord = null;
        $this->assertFalse($this->user->isCoordinator(null), "isCoordinator with null arg should be false");
      }

      function testFormerFaculty_id() {
        $this->user->current_faculty = "N";
        $this->user->id = "007";
        $this->assertEqual("esdid007", $this->user->netid);
      }

      function testfindById() {
        $user = $this->esd->findByUsername("pstaff");
        $user2 = $this->esd->findByUsername("esdid" . $user->id);
        $this->assertEqual($user, $user2, "find by esd id equivalent to find by netid");
      }

      function testGetGenericAgent() {
        $this->user->role = "grad admin";
        $this->assertEqual("Graduate School", $this->user->getGenericAgent());
        $this->user->role = "honors admin";
        $this->assertEqual("College Honors Program", $this->user->getGenericAgent());
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
	  $this->assertEqual("testuser", $user->__toString());
       }

       function testInitWithoutESD_techsupport() {
	 // tech support - based on config 
	 $user = $this->esd->initializeWithoutEsd("jolsen");
	 $this->assertEqual("techsupport", $user->getRoleId(),
			    "tech support user can be initialized without ESD and gets correct role");
	 $this->assertFalse($user->isCoordinator("Biology"),
			    "tech support user is not coordinator for Biology; isCoordinator should not throw exception when tech supporp user is initialized without ESD");
       }

       function testMatchFaculty() {
	 // name that matches two different people
	 $matches = $this->esd->match_faculty("Brad Scholar");
	 $this->assertIsA($matches, "esdPeople");
	 $this->assertIsA($matches[0], "esdPerson");
	 $this->assertEqual(2, count($matches));
	 $this->assertEqual($matches[0]->netid, "engrbs");
	 $this->assertEqual($matches[1]->netid, "bschola");
       }

       function testFindFacultyByName() {
           $result = $this->esd->findFacultyByName("Michele Thinker");
           $this->assertIsA($result, "esdPerson");
           $this->assertEqual($result->netid, "mthink");

	   // searching by directory name
	   $result = $this->esd->findFacultyByName("Bradley Scholar");
           $this->assertIsA($result, "esdPerson");
           $this->assertEqual($result->netid, "engrbs");
	   
       }

       function testFindAddress() {
           $person = $this->esd->findByUsername("mstuden");
           $adr = new esdAddressObject();
           $address = $adr->find($person->id);
           $this->assertIsA($address, "esdAddressInfo");
           $this->assertIsA($address->current, "esdAddress");
	   $this->assertEqual("5544 Rambling Road", $address->current->street[0]);
	   $this->assertEqual("Apt. 3C", $address->current->street[1]);
	   $this->assertEqual("c/o Spot", $address->current->street[2]);
	   $this->assertEqual("Atlanta", $address->current->city);
	   $this->assertEqual("GA", $address->current->state);
	   $this->assertEqual("30432", $address->current->zip);
	   $this->assertEqual("USA", $address->current->country);
	   $this->assertEqual("4041234566", $address->current->telephone);
	   $this->assertIsA($address->permanent, "esdAddress");
	   $this->assertEqual("346 Lamplight Trail", $address->permanent->street[0]);
	   $this->assertEqual("Suite #2", $address->permanent->street[1]);
	   $this->assertEqual("Rm. 323", $address->permanent->street[2]);
	   $this->assertEqual("Cleveland", $address->permanent->city);
	   $this->assertEqual("OH", $address->permanent->state);
	   $this->assertEqual("99304", $address->permanent->zip);
	   $this->assertEqual("545234566", $address->permanent->telephone);
	   $this->assertEqual("CA", $address->permanent->country);

	   // test referencing via person shortcut
	   $this->assertIsA($person->address, "esdAddressInfo");
       }

       function testAddressPersonRelation() {
	 $person = $this->esd->findByUsername("mstuden");
	 $address = $person->findEsdAddressObject()->current();
	 $this->assertIsA($address, "esdAddressInfo");
	 $this->assertIsA($address->current, "esdAddress");
	 $this->assertIsA($address->permanent, "esdAddress");

	 // non-student (no address)
	 $person = $this->esd->findByUsername("pstaff");
	 $address = $person->findEsdAddressObject()->current();
	 $this->assertNull($address, "staff person should not have an address");
       }

       function testGetSchoolId() {
	 $person = $this->esd->findByUsername("mstuden");
	 $this->assertEqual("graduate_school", $person->getSchoolId(), "getSchool should return 'grad' for acadamic career 'GSAS'; got '" . $person->getSchoolId() . "'");
	 
	 $person = $this->esd->findByUsername("pstaff");
	 $this->assertNull($person->getSchoolId(), "getSchool should return null when no acadamic career is set, got '" . $person->getSchoolId() . "'");

	 $person->academic_career = "UCOL";
	 $this->assertEqual("emory_college", $person->getSchoolId(), "getSchool should return 'honors' for acadamic career 'UCOL'; got '" . $person->getSchoolId() . "'");

	 $person->academic_career = "THEO";
	 $this->assertEqual("candler", $person->getSchoolId(), "getSchool should return 'candler' for acadamic career 'THEO'; got '" . $person->getSchoolId() . "'");
	 
       }

    }

    runtest(new TestEsdPerson());
    ?>