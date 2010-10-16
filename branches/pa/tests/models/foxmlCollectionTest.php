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
    $this->assertIsA($this->gencoll, "collectionHierarchy");
    $this->assertIsA($this->gencoll->collection, "skosCollection");

    $this->assertIsA($this->genObj, "foxml");
    $this->assertIsA($this->genObj, "foxmlSkosCollection");
    $this->assertIsA($this->genObj, "foxmlCollection");
  }

  function testInitWithBadConfig() {
    Zend_Registry::set("config", new Zend_Config());
    // test with an empty config that has no program pid
    $this->expectException(new FoxmlException("Configuration does not contain programs pid, cannot initialize"));
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
