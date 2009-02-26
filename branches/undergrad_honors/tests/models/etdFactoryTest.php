<?php
require_once("../bootstrap.php");
require_once('models/EtdFactory.php');

class TestEtdFactory extends UnitTestCase {
  private $fedora;
  private $etdpid;
  private $usrpid;
  private $honors_etdpid;
  private $honors_usrpid;

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

    $dom = new DOMDocument();
    $dom->loadXML(file_get_contents("../fixtures/user.xml"));
    $user = new user($dom);
    $user->rels_ext->addRelationToResource("rel:authorInfoFor", $this->etdpid);
    $this->usrpid = $this->fedora->ingest($user->saveXML(), "test obj");

    $user->pid = "test:user2";
    $user->rels_ext->etd = $this->honors_etdpid;
    $this->honors_usrpid = $this->fedora->ingest($user->saveXML(), "test obj");
  }
  
  function tearDown() {
    $this->fedora->purge($this->etdpid, "removing test etd");
    $this->fedora->purge($this->usrpid, "removing test user");
    $this->fedora->purge($this->honors_etdpid, "removing test honors etd");
    $this->fedora->purge($this->honors_usrpid, "removing test honors user");
  }
  
  function test_etdByPid() {
    $etd = EtdFactory::etdByPid($this->etdpid);
    $this->assertIsA($etd, "etd");
    $this->assertNotA($etd, "honors_etd");
    $this->assertEqual("regular etd", $etd->label);
    
    $hons_etd = EtdFactory::etdByPid($this->honors_etdpid);
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

  function test_userByPid() {
    $user = EtdFactory::userByPid($this->usrpid);
    $this->assertIsA($user, "user");
    $this->assertNotA($user, "honors_user");

    $honors_user = EtdFactory::userByPid($this->honors_usrpid);
    $this->assertIsA($honors_user, "user");
    $this->assertIsA($honors_user, "honors_user");
  }

  function test_init() {
    // generic init - wrapper around the other types
    $etd = EtdFactory::init($this->etdpid, "etd");
    $this->assertIsA($etd, "etd");
    $this->assertNotA($etd, "honors_etd");

    $etd = EtdFactory::init($this->honors_etdpid, "etd");
    $this->assertIsA($etd, "honors_etd");

    $user = EtdFactory::init($this->usrpid, "user");
    $this->assertIsA($user, "user");
    $this->assertNotA($user, "honors_user");

    $user = EtdFactory::init($this->honors_usrpid, "user");
    $this->assertIsA($user, "honors_user");
  }
  

}

runtest(new TestEtdFactory());

?>