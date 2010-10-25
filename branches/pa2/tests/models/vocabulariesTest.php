<?php
require_once("../bootstrap.php");
require_once('models/vocabularies.php');

class TestVocabularies extends UnitTestCase {
  private $vocabularies;
  private $vocabularyObj;

  function setUp() {
    $this->vocabularyObj = new foxmlVocabularies();
    $this->vocabularies = $this->vocabularyObj->skos;
  }

  function tearDown() {
    // a couple of tests mess with the config in the registry; restore it here
    global $config_dir;
    $config = new Zend_Config_Xml($config_dir . "config.xml", "test");
    Zend_Registry::set('config', $config);
  }

  function testBasicProperties() {
    $this->assertIsA($this->vocabularies, "vocabularies");
    $this->assertIsA($this->vocabularies, "collectionHierarchy");
    $this->assertEqual("ETD Controlled Vocabularies Hierarchy", $this->vocabularies->label);
    $this->assertEqual("#vocabularies", $this->vocabularies->id);
    $this->assertIsA($this->vocabularies->collection, "skosCollection");
    $this->assertEqual(1, count($this->vocabularies->members));
    $this->assertIsA($this->vocabularies->members[0], "skosMember");
    $this->assertEqual("Partnering Agencies", $this->vocabularies->members[0]->label, "got " . $this->vocabularies->members[0]->label . " expected Partnering Agencies");
    $this->assertEqual("Rollins School of Public Health", $this->vocabularies->members[0]->members[0]->label);
    $this->assertIsA($this->vocabularyObj, "foxml");
    $this->assertIsA($this->vocabularyObj, "foxmlSkosCollection");
    $this->assertIsA($this->vocabularyObj, "foxmlVocabularies");
  }

  function testInitSubCollection() {
    // initialize vocabularies with an id for a collection other than the top level
    $vocabularyObj = new foxmlVocabularies("#rollins");
    $this->assertEqual("Rollins School of Public Health", $vocabularyObj->skos->label, "Got " . $vocabularyObj->skos->label . " expected Rollins School of Public Health");
    $this->assertEqual(17, count($vocabularyObj->skos->members), "Got " . count($vocabularyObj->skos->members) . " expected 3");
    $this->assertEqual("Partnering Agencies", $vocabularyObj->skos->parent->label);
    $this->assertEqual("Does not apply (no collaborating organization)", $vocabularyObj->skos->members[0]->label);
    $this->assertEqual("CDC", $vocabularyObj->skos->members[1]->label);
    $this->assertEqual("US (Federal) agency other than CDC", $vocabularyObj->skos->members[2]->label);
    $this->assertEqual("Georgia state or local health department", $vocabularyObj->skos->members[3]->label);
  }

  function testGetIndexedFields() {
    $fields = $this->vocabularies->getIndexedFields();
    $this->assertIsA($fields, "array");

    // should not contain higher-level hierarchy stuff
    $this->assertFalse(in_array("rollins", $fields));
    $this->assertFalse(in_array("partnering_agencies", $fields));
    // should contain vocabularies and subfields
    $this->assertTrue(in_array("ETD Controlled Vocabularies Hierarchy", $fields), "top level");
    $this->assertTrue(in_array("Partnering Agencies", $fields), "second level");
    $this->assertTrue(in_array("Rollins School of Public Health", $fields), "third level");
    $this->assertTrue(in_array("CDC", $fields), "forth level");
  }

  function testInitWithBadConfig() {
    Zend_Registry::set("config", new Zend_Config());
    // test with an empty config that has no vocabulary pid
    $this->expectException(new FoxmlException("Configuration does not contain vocabularies pid, cannot initialize"));
    $vocabularyObj = new foxmlVocabularies();
  }

  function testInitWithoutConfig() {
    // if config is not registered, should get an exception (object cannot be initialized)
    // save config and then unset in the registry
    $reg = Zend_Registry::getInstance();
    unset($reg["config"]);
    $this->expectException(new FoxmlException("Configuration not registered, cannot retrieve pid"));
    $vocabularyObj = new foxmlVocabularies();
  }

}

runtest(new TestVocabularies());
?>
