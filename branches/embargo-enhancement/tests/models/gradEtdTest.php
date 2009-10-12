<?php
require_once("../bootstrap.php");
require_once('models/grad_etd.php');

class TestGradEtd extends UnitTestCase {
  private $etd;

  function setUp() {
    $fname = '../fixtures/etd1.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $this->etd = new grad_etd($dom);
  }

  function tearDown() {
  }

  function testBasicProperties() {
    $this->assertIsA($this->etd, "grad_etd");
    $this->assertIsA($this->etd, "etd");		// inherits
  }

  function testInitializeByTemplate() {
    $config = Zend_Registry::get('config');
    $etd = new grad_etd();
    $this->assertEqual(2, count($etd->rels_ext->isMemberOfCollections), "grad etd is member of 2 collections");
    $this->assertTrue($etd->rels_ext->isMemberOfCollections->includes($etd->rels_ext->pidToResource($config->collections->grad_school)),
        "template grad etd has isMemberOfCollection relation to grad collection");
    $this->assertPattern('|<rel:isMemberOfCollection\s+rdf:resource="info:fedora/' .
			 $config->collections->grad_school . '".*/>|',
			 $etd->rels_ext->saveXML());
  }

}

runtest(new TestGradEtd());

?>