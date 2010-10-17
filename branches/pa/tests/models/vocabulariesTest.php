<?php
require_once("../bootstrap.php");
require_once('models/foxmlCollection.php');

class TestVocabularies extends UnitTestCase {
  private $vocabularies;
  private $vocabularyObj;

  function setUp() {
    $this->vocabularyObj = new foxmlCollection("#vocabularies","#vocabularies");
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
    $this->assertIsA($this->vocabularyObj, "foxmlCollection");
  }

  function testInitSubCollection() {
    // initialize vocabularies with an id for a collection other than the top level
    $vocabularyObj = new foxmlCollection("#partnering_agencies", "#vocabularies");
    $this->assertEqual("Partnering Agencies", $vocabularyObj->skos->label, "Got " . $vocabularyObj->skos->label . " expected Partnering Agencies");    
    $vocabularyObj = new foxmlCollection("#rollins", "#vocabularies");
    $this->assertEqual("Rollins School of Public Health", $vocabularyObj->skos->label, "Got " . $vocabularyObj->skos->label . " expected Rollins School of Public Health");    
    $this->assertEqual(17, count($vocabularyObj->skos->members), "Got " . count($vocabularyObj->skos->members) . " expected 3");
    $vocabularyObj = new foxmlCollection("#cdc", "#vocabularies");
    $this->assertEqual("CDC", $vocabularyObj->skos->label, "Got " . $vocabularyObj->skos->label . " expected CDC");
  }

  function testInitWithBadConfig() {
    Zend_Registry::set("config", new Zend_Config());
    // test with an empty config that has no vocabulary pid
    $this->expectException(new FoxmlException("Configuration does not contain vocabularies pid, cannot initialize"));
    $vocabularyObj = new foxmlCollection("#vocabularies", "#vocabularies");
  }

  function testInitWithoutConfig() {
    // if config is not registered, should get an exception (object cannot be initialized)
    // save config and then unset in the registry
    $reg = Zend_Registry::getInstance();
    unset($reg["config"]);
    $this->expectException(new FoxmlException("Configuration not registered, cannot retrieve pid"));
    $vocabularyObj = new foxmlCollection("#vocabularies", "#vocabularies");
  }

}

runtest(new TestVocabularies());
?>
