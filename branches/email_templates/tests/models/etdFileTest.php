<?php
require_once("../bootstrap.php");
require_once('models/etdfile.php');
require_once('models/honors_etd.php');

class TestEtdFile extends UnitTestCase {
  private $etdfile;

  function setUp() {
    $fname = '../fixtures/etdfile.xml';
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

  function testDelete() {
    // Need an etd related to an etdfile both loaded to fedora
    $fedora = Zend_Registry::get("fedora");
    
    // load test objects to repository
    $etdpid = $fedora->ingest(file_get_contents('../fixtures/etd2.xml'), "loading test etd");
    $filepid = $fedora->ingest(file_get_contents('../fixtures/etdfile.xml'), "loading test etdfile");
    
    $etd = new etd($etdpid);
    $etdfile = new etd_file($filepid, $etd);
    $etdfile->policy->addRule("view");	// needed by addSupplement
    // add relation between objects
    $etdfile->rels_ext->addRelationToResource("rel:isSupplementOf", $etd->pid);
    // fixture has a blank dummy relation; remove
    //    $etdfile->rels_ext->removeRelation("rel:isPDFOf", "");
    $etd->addSupplement($etdfile);
    // confirm that etdfile is added
    $this->assertTrue(in_array($etdfile, $etd->supplements));

    // use new etdfile->delete function - should return date modified
    $this->assertNotNull($etdfile->delete("testing new delete function"));
    // 3. check that etd object no longer has relation to etdfile
    $etd = new etd($etdpid);	// re-init from Fedora
    $this->assertFalse($etd->rels_ext->supplement->includes($etdfile->pid));
    //		 Note: using DOMElementArray equivalent function for in_array

    // There is no good way to test only the desired properties using
    // Fedora API calls; getting entire object XML and testing that.
    $filexml = $fedora->getXml($filepid);
    
    // check that etdfile has status Deleted 
    $this->assertPattern('|foxml:property NAME="info:fedora/fedora-system:def/model#state" VALUE="Deleted"|', $filexml);
    // check that owner was preserved 
    $this->assertPattern('|foxml:property NAME="info:fedora/fedora-system:def/model#ownerId" VALUE="author"|', $filexml);	// (picked up from ETD object)

    // remove test objects from fedora
    $fedora->purge($etdpid, "completed test");
    $fedora->purge($filepid, "completed test");
  }


  public function testInitFromFile() {

    $etd = new etd();
    $etdfile = new etd_file(null, $etd);
    $person = new esdPerson();
    $author = $person->getTestPerson();
    $author->netid = 'jsmith';
    $author->firstname = "Joe";
    $author->lastname = "Smith";
    $etdfile->initializeFromFile("../fixtures/tinker_sample.pdf", "pdf", $author);

    $this->assertNotEqual("", $etdfile->dc->title, "dc:title must not be blank");
    $this->assertEqual("Dissertation/Thesis", $etdfile->dc->title, "dc:title should have generic value 'Dissertation/Thesis' when doctype is not known, was '" . $etdfile->dc->title . "'");
    $this->assertEqual("Joe Smith", $etdfile->dc->creator,
		       "dc:creator should be set to author name 'Joe Smith', was '"
		       . $etdfile->dc->creator . "'");
    $this->assertEqual("Text", $etdfile->dc->type, "dc:type for pdf should be text, got '" .
		       $etdfile->dc->type . "'");

    // file info pulled automatically from the file itself
    // NOTE: assuming a certain order in format fields, may not be reliable
    $this->assertEqual(filesize("../fixtures/tinker_sample.pdf"), $etdfile->dc->formats[0],
		       "filesize in dc:format should match '" .
		       filesize("../fixtures/tinker_sample.pdf") . "', got '" .
		       $etdfile->dc->formats[0] . "' instead");
    $this->assertEqual("application/pdf", $etdfile->dc->formats[1]);
    $this->assertEqual("8 p.", $etdfile->dc->formats[2]);
    $this->assertEqual("filename:tinker_sample.pdf", $etdfile->dc->source);

    // if genre/doctype is set in etd record, that should be used for title
    $etd->mods->genre = "Masters Thesis";
    $etdfile->initializeFromFile("../fixtures/tinker_sample.pdf", "pdf", $author);
    $this->assertEqual("Masters Thesis", $etdfile->dc->title);
    


    $honors_etd = new honors_etd();
    $etdfile = new etd_file(null, $honors_etd);
    $etdfile->initializeFromFile("../fixtures/tinker_sample.pdf", "pdf", $author);
    $this->assertEqual("Honors Thesis", $etdfile->dc->title,
		       "dc:title should be set to 'Honors Thesis' for honors etd, got '" .
		       $etdfile->dc->title . "' instead");


    // FIXME: would be more thorough to add tests for
    // original/supplemental file types, files with other mimetypes, etc.
  }

}


runtest(new TestEtdFile());
?>