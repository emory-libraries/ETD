<?php

require_once('models/etdfile.php');

class TestEtdFile extends UnitTestCase {
  private $etdfile;

  function setUp() {
    $fname = 'fixtures/etdfile.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $this->etdfile = new etd_file($dom);

    //    $this->etd->policy->addRule("view");
    //    $this->etd->policy->addRule("draft");

  }
  
  function tearDown() {
  }
  
  function testBasicProperties() {
    // test that foxml properties are accessible
    $this->assertIsA($this->etdfile, "etd_file");
    $this->assertIsA($this->etdfile->dc, "dublin_core");
    $this->assertIsA($this->etdfile->rels_ext, "rels_ext");
    $this->assertIsA($this->etdfile->policy, "EtdFileXacmlPolicy");
    
    $this->assertEqual("test:etdfile1", $this->etdfile->pid);
    $this->assertEqual("etdFile", $this->etdfile->cmodel);
  }

  function testPolicies() {
    $this->etdfile->policy->addRule("view");
    $this->etdfile->policy->addRule("draft");
    $this->assertIsA($this->etdfile->policy->draft, "PolicyRule");
    $this->assertIsA($this->etdfile->policy->view, "PolicyRule");
  }
}

?>
