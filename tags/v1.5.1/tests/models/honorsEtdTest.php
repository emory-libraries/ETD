<?php
require_once("../bootstrap.php");
require_once('models/honors_etd.php');

class TestHonorsEtd extends UnitTestCase {
  private $etd;

  // fedoraConnection
  private $fedora;

  private $etdpid;
  private $userpid; 

  function __construct() {
    $this->fedora = Zend_Registry::get("fedora");
    $fedora_cfg = Zend_Registry::get('fedora-config');

    // get 2 test pids 
    list($this->etdpid, $this->userpid) = $this->fedora->getNextPid($fedora_cfg->pidspace, 2);
  }

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


    // researchfields should not be deleted for any non-template init
    $this->assertEqual(1, count($this->etd->mods->researchfields),
		       "researchfields present");
    $this->assertTrue(isset($this->etd->mods->researchfields[0]->id),
		      "researchfield id is set");
    $this->assertPattern('|<mods:subject ID=".*" authority="proquestresearchfield">|',
			 $this->etd->mods->saveXML(), "researchfields present in MODS");
	
  }

  function testInitializeByTemplate() {
    $config = Zend_Registry::get('config');
    $etd = new honors_etd();
    $this->assertEqual(2, count($etd->rels_ext->isMemberOfCollections), "honors etd isMemberOfCollection - 2 collections");
    $this->assertTrue($etd->rels_ext->isMemberOfCollections->includes($etd->rels_ext->pidToResource($config->collections->college_honors)),
        "template honors etd has isMemberOfCollection relation to honors collection");
    $this->assertPattern('|<rel:isMemberOfCollection\s+rdf:resource="info:fedora/' .
			 $config->collections->college_honors . '".*/>|',
			 $etd->rels_ext->saveXML());

    // researchfields should not be present when initializing from template
    $this->assertEqual(0, count($etd->mods->researchfields),
		       "no researchfields mapped");
    $this->assertNoPattern('|<mods:subject ID="" authority="proquestresearchfield">|',
			   $etd->mods->saveXML(), "researchfields not present in MODS");
  }

  function testAuthorInfo() {

    // Need an etd and related authorInfo object both loaded to fedora
    $fedora = Zend_Registry::get("fedora");
    
    // load test objects to repository
    $dom = new DOMDocument();
    $dom->loadXML(file_get_contents("../fixtures/etd2.xml"));
    $foxml = new etd($dom);
    $foxml->pid = $this->etdpid;
    // set relation to test user object
    $foxml->rels_ext->hasAuthorInfo = $this->userpid;	
    $this->fedora->ingest($foxml->saveXML(), "loading test etd");

    $dom->loadXML(file_get_contents("../fixtures/user.xml"));
    $foxml = new foxml($dom);
    $foxml->pid = $this->userpid;
    $this->fedora->ingest($foxml->saveXML(), "loading test etd");
    
    // check that user object is initialized properly
    $etd = new honors_etd($this->etdpid);
    $this->assertIsA($etd->authorInfo, "honors_user");

    $fedora->purge($this->etdpid, "removing test etd");
    $fedora->purge($this->userpid, "removing test user");
  }

  function testGetResourceId() {
    // resource id used for all ACL checks
    $this->assertEqual("published honors etd", $this->etd->getResourceId());
    $this->etd->rels_ext->status = "draft";
    $this->assertEqual("draft honors etd", $this->etd->getResourceId());
  }

  
  function testIsHonors() {
    $this->assertTrue($this->etd->isHonors(), "honors etd is honors");
  }
    
  
}

runtest(new TestHonorsEtd());

?>