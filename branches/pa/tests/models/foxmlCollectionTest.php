<?php
require_once("../bootstrap.php");
require_once('models/foxmlCollection.php');

class TestFoxmlCollection extends UnitTestCase {
  private $fedora;    // fedoraConnection
  private $test;
  private $pid;
  private $id;
  private $owner;
  private $label;
  private $model;
  
  function setUp() {
    
    $test_config = Zend_Registry::get("config");
    $this->pid =  $test_config->test_collection->pid;     
    $this->id =  $test_config->test_collection->id;
    $this->label =  $test_config->test_collection->label; 
    $this->model =  $test_config->test_collection->object_model; 
    $this->owner =  $test_config->etdOwner;                
    $this->fedora = Zend_Registry::get("fedora");
  }

  function tearDown() {  
    // a couple of tests mess with the config in the registry; restore it here
    global $config_dir;
    $config = new Zend_Config_Xml($config_dir . "config.xml", "test");
    Zend_Registry::set('config', $config);     
  }

  function testBasicProperties() {
    try {
      $this->fedora->purge($this->pid, "removing test etd");    
    }
    catch (FedoraObjectNotFound $e) { }// Collection does not exist in fedora.   

    try {
      $this->fedora->purge($this->pid, "removing test etd");    
    }
    catch (FedoraObjectNotFound $e) { // Collection does not exist in fedora.
    }
    //$this->genObj = new foxmlCollection("#test", "#test");
    /*
    $this->genObj = new foxmlCollection("#test", "#test");
    $this->gencoll = $this->genObj->skos;    
    $this->assertIsA($this->gencoll, "collectionHierarchy");
    $this->assertIsA($this->gencoll->collection, "skosCollection");

    $this->assertIsA($this->genObj, "foxml");
    $this->assertIsA($this->genObj, "foxmlSkosCollection");
    $this->assertIsA($this->genObj, "foxmlCollection");
   */ 
    /*
    try {
      $this->fedora->purge($pid, "removing test etd");    
    }
    catch (FedoraObjectNotFound $e) { // Collection does not exist in fedora.
    }
    * */
  }

}

runtest(new TestFoxmlCollection());
?>
