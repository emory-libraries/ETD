<?php
require_once("../bootstrap.php");

class TestSolrIndexXslt extends UnitTestCase {
  
  function __construct() {
    
  /* How this test works:
   * The etdFoxmlToSolr.xslt stylesheet is tested using saxonb.
   * The saxonb applies the etdFoxmlToSolr.xslt stylesheet to the file 
   * of the exported xml object.  The ingested etd pid must exist because 
   * the xslt will query RIsearch to determine content models and pull 
   * managed MODS and RELS-EXT datastreams from Fedora.
   */
  /* NOTE: In order for this test to work, the fedora test etd policy 
   * permit-etd-mods-relsext-from-localhost.xml needs to be updated to 
   * include the testing server IP address in the condition clause.  
   */  
  
  /* If you want to manually run saxonb, (fedora test settings in fedora.xml), 
   * 1. Comment out the code in _destruct, save this file.
   * 2. Run this test from the browser: 
   *    http://{hostname}/tests/xslt/solrindexTest.php
   * 3. When finished, open a command prompt:
   *    cd /tests/xslt
   *    /usr/local/bin/saxonb-xslt -xsl:../../src/public/xslt/etdFoxmlToSolr.xslt -s:/tmp/etd/fedora-etd-xslt-test.xml -warnings:silent REPOSITORYURL='http://dev11.library.emory.edu:8380/fedora/'
   */
    $this->config = Zend_Registry::get("config");
    $this->saxonb = $this->config->saxonb_xslt;
    $this->fedora = Zend_Registry::get("fedora");
    $this->fedora_cfg = Zend_Registry::get('fedora-config'); 
    $this->school_cfg = Zend_Registry::get('schools-config');         
    
    // test directory must be passed to xslt processor as repo url parameter
    $repository_url = "http://" . $this->fedora_cfg->server . ":" . $this->fedora_cfg->nonssl_port . "/fedora/"; 
    $this->xsl_param = "REPOSITORYURL='" . $repository_url . "' "; 
    
    // path to xslt
    $this->xsl = "../../src/public/xslt/etdFoxmlToSolr.xslt";    
    
    // Dynamically create a temporary pid.
    $this->etdpid = $this->fedora->getNextPid($this->fedora_cfg->pidspace, 1); ;
        
    // Create the temporary fixture file name
    $this->tmpfile = $this->config->tmpdir . "/fedora-etd-xslt-test.xml";  
  }
  
  function setUp() { // delete tmp file prior to test if it exists.
    if (file_exists($this->tmpfile)) unlink($this->tmpfile);
        
    // Purge test pid and/or temporary fixture if it already exists.
    try { $this->fedora->purge($this->etdpid, "removing test etd");  } catch (Exception $e) {}      
    
    // Create an ETD   
    $this->etd = new etd($this->school_cfg->graduate_school);
    $this->etd->pid = $this->etdpid; 
    $this->etd->title = "Why I Like Cheese"; 
    $this->etd->owner = "mmouse";    
    $this->etd->updateEmbargo("2018-05-30", "embargo message"); 
    $this->etd->mods->title = "MODS Xslt Test Title"; 
    $this->etd->mods->embargo_end = "2016-05-30";
    $this->etd->mods->genre = "Dissertation";   
    $this->etd->mods->embargo = "6 months";
    $this->etd->mods->addCommittee("Dog", "Pluto", "nonemory_chair", "notEmory");        
    $this->etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_NONE);
    $this->etd->mods->addResearchField("Area Studies", "7025");   
    $this->etd->mods->addKeyword("keyword1");
    $this->etd->mods->abstract = "Gouda or Cheddar?";
    $this->etd->mods->addPartneringAgency("Georgia state or local health department");  
    $this->etd->rels_ext->program = "Disney";
  }
  
  function tearDown() { 
    if (file_exists($this->tmpfile)) unlink($this->tmpfile);
    try { $this->fedora->purge($this->etdpid, "removing test etd");  } catch (Exception $e) {}     
  }
  
  
  function test_one() {
    $this->assertIsA($this->etd, "etd");
  } 
     
  function test_activeEtdToFoxml() {
    
    // Ingest the Active pid into fedora
    $this->etd->state = "Active";    
    $this->etd->ingest("test etd xslt");
    
    // Get the XML of this object, and save to a file.     
    $this->create_tmp_fixture($this->tmpfile);
    
    $result = $this->transform($this->tmpfile);
    
    $this->assertTrue($result, "xsl transform returns data");
    $this->assertPattern("|<add>.+</add>|s", $result, "xsl result is a solr add document");
    $this->assertPattern('|<field name="PID">' . $this->etdpid . '</field>|', $result,
         "pid indexed in field PID");
    $this->assertPattern('|<field name="state">Active</field>|', $result,
         "state should be active");          
    $this->assertPattern('|<field name="label">Why I Like Cheese</field>|', $result,
         "object label indexed");
    $this->assertPattern('|<field name="ownerId">mmouse</field>|', $result,
         "owner id indexed");
    $this->assertPattern('|<field name="title">MODS Xslt Test Title</field>|', $result,
         "title indexed");
    $this->assertPattern('|<field name="document_type">Dissertation</field>|', $result,
         "genre indexed as document_type");
    $this->assertPattern('|<field name="advisor"[^>]*>Dog, Pluto</field>|', $result,
         "advisor full name indexed");
    $this->assertNoPattern('|<field name="committee"></field>|', $result,
         "blank committee not indexed");
    $this->assertPattern('|<field name="program_id">Disney</field>|', $result,
         "program name indexed");
    $this->assertPattern('|<field name="language">English</field>|', $result,
         "language indexed");
    $this->assertPattern('|<field name="subject">Area Studies</field>|', $result,
         "subject indexed");
    $this->assertPattern('|<field name="keyword">keyword1</field>|', $result,
         "keyword indexed");
    $this->assertPattern('|<field name="abstract">Gouda or Cheddar\?</field>|', $result,
         "abstract indexed");
    $this->assertPattern('|<field name="embargo_duration">6 months</field>|', $result,
         "embargo request yes/no indexed");
    $this->assertPattern('|<field name="status">draft</field>|', $result,
         "etd status indexed");
    $this->assertPattern('|<field name="contentModel">emory-control:ETD-1.0</field>|', $result,
         "content model indexed");
    $this->assertPattern('|<field name="collection">emory-control:ETD-GradSchool-collection</field>|', $result,
         "collection pid indexed");
    $this->assertNoPattern('|<field name="dateIssued">20080530</field>|', $result,
           "dateIssued indexed in solr date-range format");         
    $this->assertPattern('|<field name="partneringagencies">Georgia state or local health department</field>|', $result,
         "partneringagencies indexed");
    $this->assertNoPattern('|<field name="issuance">|', $result,
         "originInfo/issuance is not indexed");
  }
  
  function test_inactiveEtdToFoxml() {
    
    // Ingest the Active pid into fedora
    $this->etd->state = "Inactive";    
    $this->etd->ingest("test etd xslt");
    
    // Get the XML of this object, and save to a file.     
    $this->create_tmp_fixture($this->tmpfile);
    
    $result = $this->transform($this->tmpfile);
    
    $this->assertTrue($result, "xsl transform returns data");
    $this->assertPattern("|<add>.+</add>|s", $result, "xsl result is a solr add document");
    $this->assertPattern('|<field name="PID">' . $this->etdpid . '</field>|', $result,
         "pid indexed in field PID");
    $this->assertPattern('|<field name="state">Inactive</field>|', $result,
         "state should be active");          
    $this->assertPattern('|<field name="label">Why I Like Cheese</field>|', $result,
         "object label indexed");
    $this->assertPattern('|<field name="ownerId">mmouse</field>|', $result,
         "owner id indexed");
    $this->assertPattern('|<field name="title">MODS Xslt Test Title</field>|', $result,
         "title indexed");
    $this->assertPattern('|<field name="document_type">Dissertation</field>|', $result,
         "genre indexed as document_type");
  }  

  
  function transform($xmlfile) {
    $cmd = $this->saxonb . " -xsl:" . $this->xsl . " -s:" . $xmlfile . " -warnings:silent " .  $this->xsl_param;
    return shell_exec($cmd);
  }
 
  function create_tmp_fixture($filename) {
    // Create a tmp file that contains the xml content of the etd 
    // fedora object that was created in the construct.
    $handle = fopen($filename, "w");
    fwrite($handle, $this->fedora->getXml($this->etdpid));
    fclose($handle); // Close the file.     
    chmod($filename, 0744);
  }
}

runtest(new TestSolrIndexXslt());

?>
