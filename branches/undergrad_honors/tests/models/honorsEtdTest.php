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
    $this->assertIsA($this->etd, "etd");		// inherits 
    $this->assertIsA($this->etd->mods, "honors_mods");
    $this->assertIsA($this->etd->mods, "etd_mods");	// inherits 
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

  function testAuthorInfo() {

    // Need an etd and related authorInfo object both loaded to fedora
    $fedora = Zend_Registry::get("fedora");
    
    // load test objects to repository
    $etdpid = $fedora->ingest(file_get_contents('../fixtures/etd2.xml'),
			      "loading test etd");
    $userpid = $fedora->ingest(file_get_contents('../fixtures/user.xml'),
			       "loading test user");
    $etd = new etd($etdpid);
    $user = new user($userpid);
    // add relation between objects  
    $etd->rels_ext->addRelationToResource("rel:hasAuthorInfo", $userpid);


    // NOW: check that user object is initialized properly
    $etd = new honors_etd($etdpid);
    $this->assertIsA($etd->authorInfo, "honors_user");

    $fedora->purge($etdpid, "removing test etd");
    $fedora->purge($userpid, "removing test user");
  }
  
}

runtest(new TestHonorsEtd());

?>