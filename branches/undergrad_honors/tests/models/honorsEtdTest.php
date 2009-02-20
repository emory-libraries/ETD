<?php
require_once("../bootstrap.php");
require_once('models/honors_etd.php');

class TestHonorsEtd extends UnitTestCase {
  private $etd;

  function setUp() {
    $fname = '../fixtures/etd1.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $this->etd = new honors_etd($dom);
  }
  
  function tearDown() {
  }
  
  function testBasicProperties() {
    $this->assertIsA($this->etd, "honors_etd");
    $this->assertIsA($this->etd, "etd");
  }

  function testInitializeByTemplate() {
    $config = Zend_Registry::get('config');
    $etd = new honors_etd();
    $this->assertTrue(isset($etd->rels_ext->memberOf));
    $this->assertEqual($config->honors_collection, $etd->rels_ext->memberOf);
    $this->assertPattern('|<rel:isMemberOf.*rdf:resource="info:fedora/' .
			 $config->honors_collection . '".*/>|',
			 $etd->rels_ext->saveXML());
  }
  
}

runtest(new TestHonorsEtd());

?>