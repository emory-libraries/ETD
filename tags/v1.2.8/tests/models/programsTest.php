<?php
require_once("../bootstrap.php");
require_once('models/programs.php');

class TestPrograms extends UnitTestCase {
  private $programs;
  private $programObj;

  function setUp() {
    $this->programObj = new foxmlPrograms();
    $this->programs = $this->programObj->skos;
  }

  function tearDown() {
    // a couple of tests mess with the config in the registry; restore it here
    global $config_dir;
    $config = new Zend_Config_Xml($config_dir . "config.xml", "test");
    Zend_Registry::set('config', $config);
  }

  function testBasicProperties() {
    $this->assertIsA($this->programs, "programs");
    $this->assertIsA($this->programs, "collectionHierarchy");
    
    $this->assertEqual("Programs", $this->programs->label);
    $this->assertEqual("#programs", $this->programs->id);
    $this->assertIsA($this->programs->collection, "programCollection");
    $this->assertIsA($this->programs->collection, "skosCollection");
    $this->assertEqual(2, count($this->programs->members));
    $this->assertIsA($this->programs->members[0], "programMember");
    $this->assertIsA($this->programs->members[0], "skosMember");
    $this->assertEqual("Graduate", $this->programs->members[0]->label);
    $this->assertEqual("Undergraduate", $this->programs->members[1]->label);
    $this->assertEqual("Humanities", $this->programs->members[0]->members[0]->label);

    // still program-extended members & collections at deeper level of hierarchy?
    $this->assertIsA($this->programs->members[0]->collection, "programCollection");

    $this->assertIsA($this->programObj, "foxml");
    $this->assertIsA($this->programObj, "foxmlSkosCollection");
    $this->assertIsA($this->programObj, "foxmlPrograms");
  }

  function testInitSubCollection() {
    // initialize programs with an id for a collection other than the top level
    $programObj = new foxmlPrograms("#grad");
    $this->assertEqual("Graduate", $programObj->skos->label);
    $this->assertEqual(3, count($programObj->skos->members));
    $this->assertEqual("Humanities", $programObj->skos->members[0]->label);
    $this->assertEqual("Programs", $programObj->skos->parent->label);
    
  }

  function testGetIndexedFields() {
    $fields = $this->programs->getIndexedFields();
    $this->assertIsA($fields, "array");

    // should not contain higher-level hierarchy stuff
    $this->assertFalse(in_array("Graduate", $fields));
    $this->assertFalse(in_array("Humanities", $fields));
    // should contain programs and subfields
    $this->assertTrue(in_array("Art History", $fields), "regular program");
    $this->assertTrue(in_array("Psychology", $fields), "program with subfields");
    $this->assertTrue(in_array("Immunology", $fields), "subfield of a program");
  }

  function testInitWithBadConfig() {
    Zend_Registry::set("config", new Zend_Config());
    // test with an empty config that has no program pid
    $this->expectException(new FoxmlException("Configuration does not contain program pid, cannot initialize"));
    $programObj = new foxmlPrograms();
  }

  function testInitWithoutConfig() {
    // if config is not registered, should get an exception (object cannot be initialized)
    // save config and then unset in the registry
    $reg = Zend_Registry::getInstance();
    unset($reg["config"]);
    $this->expectException(new FoxmlException("Configuration not registered, cannot retrieve pid"));
    $programObj = new foxmlPrograms();
  }


}

runtest(new TestPrograms());
?>