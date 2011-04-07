<?php
require_once("../bootstrap.php");

class TestSolrIndexXslt extends UnitTestCase {
  
  function __construct() {
    
    $this->config = Zend_Registry::get("config");
    $this->saxonb = $this->config->saxonb_xslt;
    $this->fedora = Zend_Registry::get("fedora");
    $fedora_cfg = Zend_Registry::get('fedora-config'); 
    $school_cfg = Zend_Registry::get('schools-config');         
    
    // Clean up any old fedora temp directories
    //$this->deleteAllTmpFedoraDirs(); 
        
    // make unique tmp dir based on time, trailing slash is required
    $this->tmpdir = "/tmp/fedora" . strftime("%G%m%d%H%M%S") . "/";
    mkdir($this->tmpdir, 0777, true);
    // Create the temporary fixture filename.
    $this->etdbasefilename = "testetd-xsltetd";    
    $this->tmpfile = $this->tmpdir . $this->etdbasefilename . "-tmp.xml";    
    
    // test directory must be passed to xslt processor as repo url parameter
    $repository_url = "http://" . $fedora_cfg->server . ":" . $fedora_cfg->nonssl_port . "/fedora/"; 
    $this->xsl_param = "REPOSITORYURL='" . $repository_url . "' "; 
    
    // path to xslt
    $this->xsl = "../../src/public/xslt/etdFoxmlToSolr.xslt";    
    
    $this->etdpid = $this->fedora->getNextPid($fedora_cfg->pidspace, 1);               
    $this->create_tmp_fixture($this->etdpid, $this->etdbasefilename);      

    // Purge test pids if they already exist
    try { $this->fedora->purge($this->etdpid, "removing test etd");  } catch (Exception $e) {}    
    
    // Create an ETD   
    $this->etd = new etd($school_cfg->graduate_school);
    $this->etd->pid = $this->etdpid;
    $this->etd->title = "Xslt Test Title"; 
    $this->etd->owner = "mmouse";    
    $this->etd->updateEmbargo("2018-05-30", "embargo message");  
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
    $this->etd->ingest("test etd xslt");
    // To manually test this pid
    // /usr/local/bin/saxonb-xslt -xsl:../../src/public/xslt/etdFoxmlToSolr.xslt -s:../fixtures/testetd-42.xml REPOSITORYURL='http://dev11.library.emory.edu:8380/fedora/'
  }
  
  function __destruct() {
    // Delete the temporary fixture file
    $this->delDir($this->tmpdir); 
       
    // Purge test pids       
    try { $this->fedora->purge($this->etdpid, "removing test etd");  } catch (Exception $e) {}     
  }
  
  function test_one() {
    $this->assertIsA($this->etd, "etd");
  } 
     
  function test_etdToFoxml() {
    /* NOTE: In order for this test to work, the fedora test etd policy 
     * permit-etd-mods-relsext-from-localhost.xml needs to be updated to 
     * include the testing server IP address in the condition clause.  
     */

    $result = $this->transform($this->tmpfile);

    $this->assertTrue($result, "xsl transform returns data");
    $this->assertPattern("|<add>.+</add>|s", $result, "xsl result is a solr add document");
    $this->assertPattern('|<field name="PID">' . $this->etdpid . '</field>|', $result,
         "pid indexed in field PID");
    $this->assertPattern('|<field name="label">Why I Like Cheese</field>|', $result,
         "object label indexed");
    $this->assertPattern('|<field name="ownerId">mmouse</field>|', $result,
         "owner id indexed");
    $this->assertPattern('|<field name="title">Xslt Test Title</field>|', $result,
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

  // test new inactive behavior
  function test_etdToFoxml_inactiveEtd() {
    $xml = new DOMDocument();
    $xml->load($this->tmpfile);
    $foxml = new foxml($xml);
    $foxml->state = "Inactive";

    $result = $this->transformDom($foxml->dom);
    $this->assertPattern("|<add>.+</add>|s", $result,
           "xsl result for inactive etd is a solr add document");
  }
  
  // test non-etd - should not be indexed
  function test_etdToFoxml_nonEtd() {
    $result = $this->transform("../fixtures/etdfile.managed.xml");
    $this->assertNoPattern("|<add>.+</add>|s", $result,
         "xsl result for non-etd is NOT a solr add document");
  }
  
  function transformDom($dom) {
    $tmpfile = tempnam($this->config->tmpdir, "solrXsl-");
    file_put_contents($tmpfile, $dom->saveXML());
    $output = $this->transform($tmpfile);
    unlink($tmpfile);
    return $output;
  }

  function transform($xmlfile) {
    $cmd = $this->saxonb . " -xsl:" . $this->xsl . " -s:" . $xmlfile . " -warnings:silent " .  $this->xsl_param;
    return shell_exec($cmd);
  }
 
   function create_tmp_fixture($pid_tmp, $filename) {
    $fh_orig_name = "../fixtures/" . $filename . ".xml";    
    $fh_orig = fopen($fh_orig_name, "r");
    $fh_tmp = fopen($this->tmpfile, "w");
        
    $pid_orig = "testetd:TMP";    

    if ($fh_orig) {
      while (!feof($fh_orig)) // Loop til end of file.
      {
        $buffer = fgets($fh_orig, 4096); // Read a line.
        $text = str_replace($pid_orig, $pid_tmp, $buffer);
        fwrite($fh_tmp, $text);
      }
    }
    fclose($fh_orig); // Close the file.
    fclose($fh_tmp); // Close the file.     
    chmod($this->tmpfile, 0777);
  }
  
  function deleteAllTmpFedoraDirs () 
  {
    $handler = opendir("/tmp/");
    // open directory and walk through the filenames    
    while ($file = readdir($handler)) {
      // if file isn't this directory or its parent, add it to the results
      if ($file != "." && $file != "..") {
        $pos = strpos($file, "fedora");        
        if ($pos === false) {}
        elseif ($pos == 0) {
          $this->delDir("/tmp/" . $file . "/");       
        }
      }
    }
    closedir($handler);
    return;
  }
  
  //function to recursivly delete a directory
  function delDir($dir){    
    if ($handle = opendir($dir))
    { 
      while (false !== ($file = readdir($handle))) {        
        if ($file != "." && $file != "..") {
          if(is_dir($dir.$file))
          {             
            if(!@rmdir($dir.$file)) // Empty directory? Remove it
            {
              $this->delDir($dir.$file.'/'); // Not empty? Delete the files inside it
            }
          }
          else @unlink($dir.$file);
        }
      }
      closedir($handle);
      @rmdir($dir);
    }
  }
}

runtest(new TestSolrIndexXslt());

?>
