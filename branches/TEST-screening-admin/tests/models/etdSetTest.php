<?php
require_once("../bootstrap.php");
require_once('models/EtdSet.php');
require_once('models/etd.php');
//require_once('models/honors_etd.php');

class TestEtdSet extends UnitTestCase {
  private $etdset;
  private $etdpid;
  private $honors_etdpid;

  // fedoraConnection
  private $fedora;
  
  function __construct() {
    $this->fedora = Zend_Registry::get("fedora");
    $fedora_cfg = Zend_Registry::get('fedora-config');

    // get 2 test pids 
    list($this->etdpid, $this->honors_etdpid) = $this->fedora->getNextPid($fedora_cfg->pidspace, 5);
  }

  function setUp() {
    $this->etdset = new EtdSet();
    
    $this->fedora = Zend_Registry::get("fedora");
    
    $etd = new etd();
    $etd->pid = $this->etdpid;
    $etd->title = "regular etd";
    $this->fedora->ingest($etd->saveXML(), "test etd factory init");
    
    $etd = new honors_etd();
    $etd->pid = $this->honors_etdpid;
    $etd->title = "honors etd";
    $this->fedora->ingest($etd->saveXML(), "test etd factory init");
    

    $solr = new Mock_Etd_Service_Solr();
    $etd = &new MockEtd();
    $etd->PID = $this->etdpid;
    $etd2 = &new MockEtd();
    $etd2->PID = $this->honors_etdpid;
    $solr->response->docs[] = $etd;
    $solr->response->docs[] = $etd2;

    Zend_Registry::set("solr", $solr);
  }
  
  function tearDown() {
    $this->fedora->purge($this->etdpid, "removing test etd");
    $this->fedora->purge($this->honors_etdpid, "removing test honors etd");
  }

  function testInitialize() {
    $this->etdset->find(array());	// find everything

    // first item should be initialized as a regular etd
    $this->assertEqual($this->etdset->etds[0]->pid, $this->etdpid);
    $this->assertIsA($this->etdset->etds[0], "etd");
    $this->assertNotA($this->etdset->etds[0], "honors_etd");  

    // second item should be initialized as honors etd
    $this->assertEqual($this->etdset->etds[1]->pid, $this->honors_etdpid);
    $this->assertIsA($this->etdset->etds[1], "honors_etd");
    
  }
  
  

}

runtest(new TestEtdSet());

?>