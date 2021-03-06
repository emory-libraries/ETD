<?php
require_once("../bootstrap.php");
require_once('models/programs.php');

class TestPrograms extends UnitTestCase {
  private $programs;
  private $programObj;
  private $fedora;

  function setUp() {
    $this->fedora = Zend_Registry::get('fedora');
    $this->programObj = new foxmlPrograms("#programs");
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

    $this->assertIsA($this->programObj, "foxml");
    $this->assertIsA($this->programObj, "foxmlSkosCollection");
    $this->assertIsA($this->programObj, "foxmlPrograms");
  }

  function testInitSubCollection() {
    // initialize programs with an id for a collection other than the top level
    $programObj = new foxmlPrograms("#grad");
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
  
  function testCreateFedoraObject() {
        
    // store real config to restore later
    $prev_config = Zend_Registry::get('config');

    // stub config with test pid for programs_pid just for this test.
    $tmp_config = new Zend_Config(array(
          "etdOwner" => 'etdadmin',
          "programs_collection" => array(
             "id" => '#programs',          
             "pid" => 'emory-control:ETD-programs-TEST',
             "label" => 'ETD Programs Hierarchy Test',
             "skos_label" => 'Programs',
             "model_object" => 'emory-control:Hierarchy-1.0-Test'),
          ));
    
    // temporarily override config in with test configuration
    Zend_Registry::set('config', $tmp_config);     

    try { // Remove the test pid from fedora, if it exists.
      $this->fedora->purge($tmp_config->programs_collection->pid, "removing test pid if it exists"); 
    } catch (FedoraObjectNotFound $e) {}
    
    $new_collection  = new foxmlPrograms();  
    $new_collection->skos->dslabel = $tmp_config->programs_collection->skos_label;      
    
    $new_collection->ingest("creating TEST SKOS collection object");
    
    $new_pgms_coll_skos = $new_collection->skos;    
    $this->assertIsA($new_pgms_coll_skos, "programs");
    $this->assertIsA($new_pgms_coll_skos, "collectionHierarchy");
    $this->assertEqual($tmp_config->programs_collection->skos_label, $new_pgms_coll_skos->label);
    $this->assertEqual($tmp_config->programs_collection->id, $new_pgms_coll_skos->id);
    
    // content model
    $this->assertNotNull($new_collection->rels_ext->hasModel);
    $this->assertEqual($tmp_config->programs_collection->model_object, $new_collection->rels_ext->hasModel);    
    
    try {  //Remove the test pid from fedora, if it exists.
      $this->fedora->purge($tmp_config->programs_collection->pid, "removing test pid if it exists"); 
    } catch (FedoraObjectNotFound $e) {}
    
    // Restore the previous configuration
    Zend_Registry::set('config', $prev_config); 
  }  

  function testInitWithBadConfig() {
    Zend_Registry::set("config", new Zend_Config());
    // test with an empty config that has no program pid
    $this->expectException(new FoxmlException("Configuration does not contain program pid, cannot initialize"));
    $programObj = new foxmlPrograms("#programs");
  }

  function testInitWithoutConfig() {
    // if config is not registered, should get an exception (object cannot be initialized)
    // save config and then unset in the registry
    $reg = Zend_Registry::getInstance();
    unset($reg["config"]);
    $this->expectException(new FoxmlException("Configuration not registered, cannot retrieve pid"));
    $programObj = new foxmlPrograms("#programs");
  }


}

runtest(new TestPrograms());
?>
