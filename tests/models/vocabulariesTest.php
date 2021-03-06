<?php
require_once("../bootstrap.php");
require_once('models/vocabularies.php');

class TestVocabularies extends UnitTestCase {
  private $vocabularies;
  private $vocabularyObj;
  private $fedora;

  function setUp() {
    $this->fedora = Zend_Registry::get('fedora');
    $this->vocabularyObj = new foxmlVocabularies("#vocabularies");
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
    $this->assertEqual("Vocabularies Hierarchy", $this->vocabularies->label);
    $this->assertEqual("#vocabularies", $this->vocabularies->id);
    $this->assertIsA($this->vocabularies->collection, "skosCollection");
    $this->assertEqual(1, count($this->vocabularies->members));
    $this->assertIsA($this->vocabularies->members[0], "skosMember");
    $this->assertEqual("Partnering Agencies", $this->vocabularies->members[0]->label, "got " . $this->vocabularies->members[0]->label . " expected Partnering Agencies");
    $this->assertIsA($this->vocabularyObj, "foxml");
    $this->assertIsA($this->vocabularyObj, "foxmlSkosCollection");
    $this->assertIsA($this->vocabularyObj, "foxmlVocabularies");
  }

  function testInitSubCollection() {
    // initialize vocabularies with an id for a collection other than the top level
    $vocabularyObj = new foxmlVocabularies("#partnering_agencies");
    $this->assertEqual(17, count($vocabularyObj->skos->members), "Got " . count($vocabularyObj->skos->members) . " expected 3");
    $this->assertEqual("Partnering Agencies", $vocabularyObj->skos->label);
    $this->assertEqual("Does not apply (no collaborating organization)", $vocabularyObj->skos->members[0]->label);
    $this->assertEqual("#pa-cdc", $vocabularyObj->skos->members[1]->id);
    $this->assertEqual("US (Federal) agency other than CDC", $vocabularyObj->skos->members[2]->label);
    $this->assertEqual("Georgia state or local health department", $vocabularyObj->skos->members[3]->label);
  }

  function testGetIndexedFields() {
    $fields = $this->vocabularies->getIndexedFields();
    $this->assertIsA($fields, "array");

    // should not contain higher-level hierarchy stuff
    $this->assertFalse(in_array("partnering_agencies", $fields));
    // should contain vocabularies and subfields
    $this->assertTrue(in_array("Vocabularies Hierarchy", $fields), "top level");
    $this->assertTrue(in_array("Partnering Agencies", $fields), "second level");
    $this->assertTrue(in_array("CDC", $fields), "third level");
  }

  function testCreateFedoraObject() {
    
    // store real config to restore later
    $prev_config = Zend_Registry::get('config');
    // Get the owner of the fedora object
    $owner = $prev_config->etdOwner;
    
    // stub config with test pid for programs_pid just for this test.
    $tmp_config = new Zend_Config(array(
          "etdOwner" => 'etdadmin',
          "vocabularies_collection" => array(
             "id" => '#vocabularies',          
             "pid" => 'emory-control:ETD-vocabulary-TEST',
             "label" => 'ETD Controlled Vocabularies Hierarchy Test',
             "skos_label" => 'Vocabularies',
             "model_object" => 'emory-control:Hierarchy-1.0-Test'),
          ));
    
    // temporarily override config in with test configuration
    Zend_Registry::set('config', $tmp_config);     

    try { // Remove the test pid from fedora, if it exists.
      $this->fedora->purge($tmp_config->vocabularies_collection->pid, "removing test pid if it exists"); 
    } catch (FedoraObjectNotFound $e) {}
    
    $new_collection  = new foxmlVocabularies();  
    $new_collection->skos->label = $tmp_config->vocabularies_collection->label;    

    $new_collection->ingest("creating TEST SKOS collection object");
    
    $new_vocab_skos = $new_collection->skos;    
    $this->assertIsA($new_vocab_skos, "vocabularies");
    $this->assertIsA($new_vocab_skos, "collectionHierarchy");
    $this->assertEqual($tmp_config->vocabularies_collection->label, $new_vocab_skos->label);
    $this->assertEqual($tmp_config->vocabularies_collection->id, $new_vocab_skos->id);
    
    // content model
    $this->assertNotNull($new_collection->rels_ext->hasModel);
    $this->assertEqual($tmp_config->vocabularies_collection->model_object, 
              $new_collection->rels_ext->hasModel);    
    
    try { // Remove the test pid from fedora, if it exists.
      $this->fedora->purge($tmp_config->vocabularies_collection->pid, "removing test pid if it exists"); 
    } catch (FedoraObjectNotFound $e) {}
    
    // Restore the previous configuration
    Zend_Registry::set('config', $prev_config); 
  }

  function testInitWithBadConfig() {
    Zend_Registry::set("config", new Zend_Config());
    // test with an empty config that has no vocabulary pid
    $this->expectException(new FoxmlException("Configuration does not contain vocabularies pid, cannot initialize"));
    $vocabularyObj = new foxmlVocabularies("#vocabularies");
  }

  function testInitWithoutConfig() {
    // if config is not registered, should get an exception (object cannot be initialized)
    // save config and then unset in the registry
    $reg = Zend_Registry::getInstance();
    unset($reg["config"]);
    $this->expectException(new FoxmlException("Configuration not registered, cannot retrieve pid"));
    $vocabularyObj = new foxmlVocabularies("#vocabularies");
  }

}

runtest(new TestVocabularies());
?>
