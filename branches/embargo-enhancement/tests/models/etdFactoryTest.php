<?php
require_once("../bootstrap.php");
require_once('models/EtdFactory.php');

class TestEtdFactory extends UnitTestCase {
  private $fedora;
  
  private $etdpid;
  private $userpid;
  private $honors_etdpid;
  private $honors_userpid;
  private $grad_etdpid;

  
  function __construct() {
    $this->fedora = Zend_Registry::get("fedora");
    $fedora_cfg = Zend_Registry::get('fedora-config');

    // get 5 test pids to be used throughout test
    $this->pids = $this->fedora->getNextPid($fedora_cfg->pidspace, 5);
    list($this->etdpid, $this->userpid, $this->honors_etdpid, $this->honors_userpid,
	 $this->grad_etdpid) = $this->pids;
  }

  
  function setUp() {
    $etd = new etd();
    $etd->pid = $this->etdpid;
    $etd->title = "regular etd";
    $this->fedora->ingest($etd->saveXML(), "test etd factory init");
    
    $etd = new honors_etd();
    $etd->pid = $this->honors_etdpid;
    $etd->title = "honors etd";
    $this->fedora->ingest($etd->saveXML(), "test etd factory init");

    $etd = new grad_etd();
    $etd->pid = $this->grad_etdpid;
    $etd->title = "grad etd";
    $this->fedora->ingest($etd->saveXML(), "test etd factory init");
    
    $dom = new DOMDocument();
    $dom->loadXML(file_get_contents("../fixtures/user.xml"));
    $user = new user($dom);
    $user->pid = $this->userpid;
    $user->rels_ext->addRelationToResource("rel:authorInfoFor", $this->etdpid);
    $this->fedora->ingest($user->saveXML(), "test obj");

    $user->pid = $this->honors_userpid;
    $user->rels_ext->etd = $this->honors_etdpid;
    $this->fedora->ingest($user->saveXML(), "test obj");
  }
  
  function tearDown() {
    foreach ($this->pids as $pid) {
      $this->fedora->purge($pid, "removing test etd object");
    }
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

    $grad_etd = EtdFactory::etdByPid($this->grad_etdpid);
    $this->assertIsA($grad_etd, "etd");
    $this->assertIsA($grad_etd, "grad_etd");
    $this->assertEqual("grad etd", $grad_etd->label);

    // alternate pid format should also work
    $etd = EtdFactory::etdByPid("info:fedora/" . $this->grad_etdpid);
    $this->assertIsA($etd, "etd");
    $this->assertNotA($etd, "honors_etd");

    $hons_etd = EtdFactory::etdByPid("info:fedora/" . $this->honors_etdpid);
    $this->assertIsA($hons_etd, "etd");
    $this->assertIsA($hons_etd, "honors_etd");    
  }

  function test_userByPid() {
    $user = EtdFactory::userByPid($this->userpid);
    $this->assertIsA($user, "user");
    $this->assertNotA($user, "honors_user");

    $honors_user = EtdFactory::userByPid($this->honors_userpid);
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

    $user = EtdFactory::init($this->userpid, "user");
    $this->assertIsA($user, "user");
    $this->assertNotA($user, "honors_user");

    $user = EtdFactory::init($this->honors_userpid, "user");
    $this->assertIsA($user, "honors_user");
  }
  

}

runtest(new TestEtdFactory());

?>