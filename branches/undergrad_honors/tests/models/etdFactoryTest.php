<?php
require_once("../bootstrap.php");
require_once('models/EtdFactory.php');

class TestEtdFactory extends UnitTestCase {
  private $fedora;
  private $etdpid;
  private $honors_etdpid;

  function setUp() {
    $this->fedora = Zend_Registry::get("fedora");
    
    $etd = new etd();
    $etd->pid = "demo:12";
    $etd->title = "regular etd";
    $this->etdpid = $this->fedora->ingest($etd->saveXML(), "test etd factory init");
    
    $etd = new honors_etd();
    $etd->pid = "demo:13";
    $etd->title = "honors etd";
    $this->honors_etdpid = $this->fedora->ingest($etd->saveXML(), "test etd factory init");
    
    /*$etd = new honors_etd();
    $etd->pid = "demo:14";
    $etd->title = "demo:14";
    $this->honors_etdpid = $this->fedora->ingest($etd->saveXML(), "test etd factory init");
    */
    /*  $fname = '../fixtures/etd1.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $this->etd = new etd($dom);

    $this->etd->policy->addRule("view");
    $this->etd->policy->addRule("draft");
    */
  }
  
  function tearDown() {
    $this->fedora->purge($this->etdpid, "removing test etd");
    $this->fedora->purge($this->honors_etdpid, "removing test honors etd");
  }

  function test_etdByPid() {
    $etd = EtdFactory::etdByPid("demo:12");
    $this->assertIsA($etd, "etd");
    $this->assertNotA($etd, "honors_etd");
    $this->assertEqual("regular etd", $etd->label);
    
    $hons_etd = EtdFactory::etdByPid("demo:13");
    $this->assertIsA($hons_etd, "etd");
    $this->assertIsA($hons_etd, "honors_etd");
    $this->assertEqual("honors etd", $hons_etd->label);

    // alternate pid format should also work
    $etd = EtdFactory::etdByPid("info:fedora/demo:12");
    $this->assertIsA($etd, "etd");
    $this->assertNotA($etd, "honors_etd");

    $hons_etd = EtdFactory::etdByPid("info:fedora/demo:13");
    $this->assertIsA($hons_etd, "etd");
    $this->assertIsA($hons_etd, "honors_etd");    
  }
  

}

runtest(new TestEtdFactory());

?>