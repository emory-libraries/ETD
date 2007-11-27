<?php

require_once('models/fezetd.php');

class TestFezEtd extends UnitTestCase {
    private $etd;
    private $testfile;

  function setUp() {
    $this->testfile = array("pid" => "test:fezetd1",
		      "name" => "fezetd.xml");
    fedora::ingest(file_get_contents('fixtures/' . $this->testfile["name"]), "loading test object");
    $this->etd = new FezEtd($this->testfile["pid"]);
  }
  
  function tearDown() {
    fedora::purge($this->testfile["pid"], "removing test object");
  }
  
  function testBasicProperties() {
    /*    print "<pre>";
    print_r($this->etd);
    print "</pre>";*/
    // test that foxml properties are accessible
    $this->assertIsA($this->etd, "FezEtd");
    $this->assertIsA($this->etd->dc, "dublin_core");
    $this->assertIsA($this->etd->rels_ext, "rels_ext");
    $this->assertIsA($this->etd->mods, "etd_mods");
    $this->assertIsA($this->etd->fezmd, "FezMD");
    
    $this->assertEqual("test:fezetd1", $this->etd->pid);
    $this->assertEqual("Fez is Lame", $this->etd->label);

    // can access Fez metadata
    $this->assertEqual(2, $this->etd->fezmd->status);
    // fez metadata status id translated into human-readable string
    $this->assertEqual("published", $this->etd->status);
  }

}

?>