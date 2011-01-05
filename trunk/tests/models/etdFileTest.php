<?php
require_once("../bootstrap.php");
require_once('models/datastreams/etdfile.php');

class TestEtdFile extends UnitTestCase {
  private $etdfile;

  // fedoraConnection
  private $fedora;

  private $pids;
  private $etdpid;
  private $filepid; 

  function __construct() {
    $this->fedora = Zend_Registry::get("fedora");
    $fedora_cfg = Zend_Registry::get('fedora-config');

    // get 2 test pids 
    $this->pids = $this->fedora->getNextPid($fedora_cfg->pidspace, 2);
    list($this->etdpid, $this->filepid) = $this->pids;
  }

  
  function setUp() {
    $fname = '../fixtures/etdfile.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $this->etdfile = new etd_file($dom);
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
    $this->assertEqual("EtdFile", $this->etdfile->contentModelName());
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
    $dom = new DOMDocument();
    $dom->loadXML(file_get_contents("../fixtures/etd1.xml"));
    $foxml = new foxml($dom);
    $foxml->pid = $this->etdpid;
    $this->fedora->ingest($foxml->saveXML(), "loading test etd");

    $dom->loadXML(file_get_contents("../fixtures/etdfile.xml"));
    $foxml = new foxml($dom);
    $foxml->pid = $this->filepid;
    $this->fedora->ingest($foxml->saveXML(), "loading test etdfile");
    
    $etd = new etd($this->etdpid);
    $etdfile = new etd_file($this->filepid, $etd);
    $etdfile->owner = 'mmouse';
    $etdfile->policy->addRule("view");  // needed by addSupplement
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
    $etd = new etd($this->etdpid);  // re-init from Fedora
    $this->assertFalse($etd->rels_ext->supplement->includes($etdfile->pid));
    //     Note: using DOMElementArray equivalent function for in_array

    // There is no good way to test only the desired properties using
    // Fedora API calls; getting entire object XML and testing that.
    $filexml = $fedora->getXml($this->filepid);
    
    // check that etdfile has status Deleted 
    $this->assertPattern('|foxml:property NAME="info:fedora/fedora-system:def/model#state" VALUE="Deleted"|', $filexml);
    // check that owner was preserved
    $this->assertPattern('|foxml:property NAME="info:fedora/fedora-system:def/model#ownerId" VALUE="mmouse"|', $filexml); // (picked up from ETD object)

    // remove test objects from fedora
    $fedora->purge($this->etdpid, "completed test");
    $fedora->purge($this->filepid, "completed test");
  }


  public function testInitFromFile() {
    $schools_cfg = Zend_Registry::get("schools-config");

    $grad_etd = new etd($schools_cfg->graduate_school);
    $etdfile = new etd_file(null, $grad_etd);
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
    $this->assertEqual(filesize("../fixtures/tinker_sample.pdf"), $etdfile->dc->filesize,
           "filesize in dc:format should match '" .
           filesize("../fixtures/tinker_sample.pdf") . "', got '" .
           $etdfile->dc->filesize . "' instead");
    $this->assertEqual("application/pdf", $etdfile->file->mimetype);
    $this->assertEqual("8", $etdfile->dc->pages);
    $this->assertEqual("filename:tinker_sample.pdf", $etdfile->dc->source);

    // if genre/doctype is set in etd record, that should be used for title
    $grad_etd->mods->genre = "Masters Thesis";
    $etdfile->initializeFromFile("../fixtures/tinker_sample.pdf", "pdf", $author);
    $this->assertEqual("Masters Thesis", $etdfile->dc->title);
    

    $honors_etd = new etd($schools_cfg->emory_college);
    $etdfile = new etd_file(null, $honors_etd);
    $etdfile->initializeFromFile("../fixtures/tinker_sample.pdf", "pdf", $author);
    $this->assertEqual("Honors Thesis", $etdfile->dc->title,
           "dc:title should be set to 'Honors Thesis' for honors etd, got '" .
           $etdfile->dc->title . "' instead");


    // FIXME: would be more thorough to add tests for
    // original/supplemental file types, files with other mimetypes, etc.
  }

  /* FIXME: lots of unit tests missing, including...
     - getFileChecksum
   */
  
}


runtest(new TestEtdFile());
?>
