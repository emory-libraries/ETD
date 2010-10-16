<?php
require_once("../bootstrap.php");
require_once('models/foxmlCollection.php');

class TestFoxmlCollection extends UnitTestCase {
  private $gencoll;
  private $genObj;

  function setUp() {
    $this->genObj = new foxmlCollection();
    $this->gencoll = $this->genObj->skos;
  }

  function tearDown() {
    // a couple of tests mess with the config in the registry; restore it here
    global $config_dir;
    $config = new Zend_Config_Xml($config_dir . "config.xml", "test");
    Zend_Registry::set('config', $config);
  }

  function testBasicProperties() {
    $this->assertIsA($this->gencoll, "gencoll");
    $this->assertIsA($this->gencoll, "collectionHierarchy");
    
    $this->assertEqual("Programs", $this->gencoll->label);
    $this->assertEqual("#programs", $this->gencoll->id);
    $this->assertIsA($this->gencoll->collection, "genCollection");
    $this->assertIsA($this->gencoll->collection, "skosCollection");
    $this->assertEqual(4, count($this->gencoll->members));
    $this->assertIsA($this->gencoll->members[0], "genMember");
    $this->assertIsA($this->gencoll->members[0], "skosMember");
    $this->assertEqual("Laney Graduate School", $this->gencoll->members[0]->label, "got " . $this->gencoll->members[0]->label . " expected Laney Graduate School");
    $this->assertEqual("Emory College", $this->gencoll->members[1]->label, "got " . $this->gencoll->members[1]->label . " expected Emory College");
    $this->assertEqual("Candler School of Theology", $this->gencoll->members[2]->label, "got " . $this->gencoll->members[2]->label . " expected Candler School of Theology"); 
    $this->assertEqual("Rollins School of Public Health", $this->gencoll->members[3]->label, "got " . $this->gencoll->members[3]->label . " expected Rollins School of Public Health");
    $this->assertEqual("Humanities", $this->gencoll->members[0]->members[0]->label);

    // still program-extended members & collections at deeper level of hierarchy?
    $this->assertIsA($this->gencoll->members[0]->collection, "genCollection");

    $this->assertIsA($this->genObj, "foxml");
    $this->assertIsA($this->genObj, "foxmlSkosCollection");
    $this->assertIsA($this->genObj, "foxmlCollection");
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
    $fields = $this->gencoll->getIndexedFields();
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
    $this->expectException(new FoxmlException("Configuration does not contain program pid, cannot initialize"));
    $genObj = new foxmlCollection();
  }

  function testInitWithoutConfig() {
    // if config is not registered, should get an exception (object cannot be initialized)
    // save config and then unset in the registry
    $reg = Zend_Registry::getInstance();
    unset($reg["config"]);
    $this->expectException(new FoxmlException("Configuration not registered, cannot retrieve pid"));
    $genObj = new foxmlCollection();
  }


}

runtest(new TestFoxmlCollection());
?>
