<?php
require_once("../bootstrap.php");
require_once('models/foxmlCollection.php');

class TestPrograms extends UnitTestCase {
  private $programs;
  private $programObject;

  function setUp() {
    $this->programObject = new foxmlCollection();
    $this->programs = $this->programObject->skos;
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
    $this->assertEqual(4, count($this->programs->members));
    $this->assertIsA($this->programs->members[0], "programMember");
    $this->assertIsA($this->programs->members[0], "skosMember");
    $this->assertEqual("Laney Graduate School", $this->programs->members[0]->label, "got " . $this->programs->members[0]->label . " expected Laney Graduate School");
    $this->assertEqual("Emory College", $this->programs->members[1]->label, "got " . $this->programs->members[1]->label . " expected Emory College");
    $this->assertEqual("Candler School of Theology", $this->programs->members[2]->label, "got " . $this->programs->members[2]->label . " expected Candler School of Theology"); 
    $this->assertEqual("Rollins School of Public Health", $this->programs->members[3]->label, "got " . $this->programs->members[3]->label . " expected Rollins School of Public Health");
    $this->assertEqual("Humanities", $this->programs->members[0]->members[0]->label);

    // still program-extended members & collections at deeper level of hierarchy?
    $this->assertIsA($this->programs->members[0]->collection, "programCollection");

    $this->assertIsA($this->programObject, "foxml");
    $this->assertIsA($this->programObject, "foxmlSkosCollection");
    $this->assertIsA($this->programObject, "foxmlCollection");
  }

  function testInitSubCollection() {
    // initialize programs with an id for a collection other than the top level
    $programObj = new foxmlCollection("#grad");
    $this->assertEqual("Laney Graduate School", $programObj->skos->label, "Got " . $programObj->skos->label . " expected Laney Graduate School");
    $this->assertEqual(3, count($programObj->skos->members), "Got " . count($programObj->skos->members) . " expected 3");
    $this->assertEqual("Humanities", $programObj->skos->members[0]->label);
    $this->assertEqual("Programs", $programObj->skos->parent->label);
    
  }

  function testGetIndexedFields() {
    $fields = $this->programs->getIndexedFields();
    $this->assertIsA($fields, "array");

    // should not contain higher-level hierarchy stuff
    $this->assertFalse(in_array("graduate", $fields));
    $this->assertFalse(in_array("humanities", $fields));
    // should contain programs and subfields
    $this->assertTrue(in_array("arthistory", $fields), "regular program");
    $this->assertTrue(in_array("psychology", $fields), "program with subfields");
    $this->assertTrue(in_array("immunology", $fields), "subfield of a program");
  }

  function testInitWithBadConfig() {
    Zend_Registry::set("config", new Zend_Config());
    // test with an empty config that has no program pid
    $this->expectException(new FoxmlException("Configuration does not contain programs pid, cannot initialize"));
    $programObject = new foxmlCollection();
  }

  function testInitWithoutConfig() {
    // if config is not registered, should get an exception (object cannot be initialized)
    // save config and then unset in the registry
    $reg = Zend_Registry::getInstance();
    unset($reg["config"]);
    $this->expectException(new FoxmlException("Configuration not registered, cannot retrieve pid"));
    $programObject = new foxmlCollection();
  }


}

runtest(new TestPrograms());
?>