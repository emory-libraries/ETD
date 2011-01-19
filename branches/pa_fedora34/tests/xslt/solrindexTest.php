<?php
require_once("../bootstrap.php");

class TestSolrIndexXslt extends UnitTestCase {
    private $xsl;
    private $tmpdir;

    function __construct() {
      $this->config = Zend_Registry::get("config");
      $this->saxonb = $this->config->saxonb_xslt;

      // one-time setup (not modified by tests)
      
      // make unique tmp dir based on time, trailing slash is required
      $this->tmpdir = "/tmp/fedora" . strftime("%G%m%d%H%M%S") . "/"; 

      // NOTE: using custom fixtures that allow mocking managed datastreams
      // that must be retrieved via xsl:document()
      // - fixture PIDs must not contain : because pid is used for directory name
      // Copy RELS-EXT and MODS files for testetd1 into mock repo url/directory structure
      $pid = "testetd1";
      mkdir("{$this->tmpdir}get/$pid", 0777, true); 
      copy('../fixtures/etd1.managed.RELS-EXT.xml', "{$this->tmpdir}get/$pid/RELS-EXT");
      copy('../fixtures/etd1.managed.MODS.xml', "{$this->tmpdir}get/$pid/MODS");
      
      // Copy RELS-EXT for testetdfile1 into mock repo url/directory structure
      $pid = "testetdfile1";
      mkdir("{$this->tmpdir}get//$pid", 0777, true); 
      copy('../fixtures/etdfile.managed.RELS-EXT.xml', "{$this->tmpdir}get/$pid/RELS-EXT");

      // test directory must be passed to xslt processor as repo url parameter
      $this->xsl_param = "REPOSITORYURL='" . $this->tmpdir . "' ";
      
      //change all files and directories to 777
      $this->chmodR($this->tmpdir, 0777);

      // path to xslt
      $this->xsl = "../../src/public/xslt/etdFoxmlToSolr.xslt";
    }

    function __destruct() {
      // Remove temp files
      $this->delDir($this->tmpdir);
    }
    

    function test_etdToFoxml() {
      $result = $this->transform("../fixtures/etd1.managed.xml");

        $this->assertTrue($result, "xsl transform returns data");
	$this->assertPattern("|<add>.+</add>|s", $result, "xsl result is a solr add document");
	$this->assertPattern('|<field name="PID">testetd1</field>|', $result,
			     "pid indexed in field PID");
	$this->assertPattern('|<field name="label">Why I Like Cheese</field>|', $result,
			     "object label indexed");
	$this->assertPattern('|<field name="ownerId">mmouse</field>|', $result,
			     "owner id indexed");
	$this->assertPattern('|<field name="title">Why I Like Cheese</field>|', $result,
			     "title indexed");
	$this->assertPattern('|<field name="document_type">Dissertation</field>|', $result,
			     "genre indexed as document_type");
	$this->assertPattern('|<field name="author">Mouse, Minnie</field>|', $result,
			     "author full name indexed");
	$this->assertPattern('|<field name="advisor"[^>]*>Duck, Donald</field>|', $result,
			     "advisor full name indexed");
	$this->assertPattern('|<field name="committee">Dog, Pluto</field>|', $result,
			     "committee full name indexed");
	$this->assertNoPattern('|<field name="committee"></field>|', $result,
			     "blank committee not indexed");
		
	$this->assertPattern('|<field name="program">Disney</field>|', $result,
			     "program name indexed");
	$this->assertPattern('|<field name="language">English</field>|', $result,
			     "language indexed");
	$this->assertPattern('|<field name="subject">Area studies</field>|', $result,
			     "subject indexed");
	$this->assertPattern('|<field name="keyword">keyword1</field>|', $result,
			     "keyword indexed");
	$this->assertNoPattern('|<field name="keyword"></field>|', $result,
			     "blank keyword is not indexed");
	$this->assertPattern('|<field name="abstract">Gouda or Cheddar\?</field>|', $result,
			     "abstract indexed");
	$this->assertPattern('|<field name="embargo_requested">no</field>|', $result,
			     "embargo request yes/no indexed");
	$this->assertPattern('|<field name="status">published</field>|', $result,
			     "etd status indexed");
	$this->assertPattern('|<field name="contentModel">emory-control:ETD-1.0</field>|', $result,
			     "content model indexed");
	$this->assertPattern('|<field name="collection">emory-control:ETD-GradSchool-collection</field>|', $result,
			     "collection pid indexed");

	$this->assertPattern('|<field name="dateIssued">20080530</field>|', $result,
			     "dateIssued indexed in solr date-range format");
	$this->assertPattern('|<field name="year">2008</field>|', $result,
			     "year from dateIssued indexed as year");

	$this->assertNoPattern('|<field name="issuance">|', $result,
			     "originInfo/issuance is not indexed");
	
    }


    // test new inactive behavior
    function test_etdToFoxml_inactiveEtd() {
        $xml = new DOMDocument();
        $xml->load("../fixtures/etd1.managed.xml");
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
      $cmd = $this->saxonb . " -xsl:" . $this->xsl . " -s:" . $xmlfile . " -warnings:silent "
	.  $this->xsl_param ;
      return shell_exec($cmd);
    }
    

    //function to recursivly delete a directory
    function delDir($dir){
	if ($handle = opendir($dir))
	{
	$array = array();
 
    	while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != "..") {
 
            if(is_dir($dir.$file))
            {
                if(!@rmdir($dir.$file)) // Empty directory? Remove it
                {
                $this->delDir($dir.$file.'/'); // Not empty? Delete the files inside it
                }
            }
            else
            {
               @unlink($dir.$file);
            }
        }
    }
    closedir($handle);
 
    @rmdir($dir);
}
 
}

    //function to recursively change permissons on a directory
   function chmodR($path, $filemode) {
 if ( !is_dir($path) ) {
  return chmod($path, $filemode);
 }
 $dh = opendir($path);
 while ( $file = readdir($dh) ) {
  if ( $file != '.' && $file != '..' ) {
   $fullpath = $path.'/'.$file;
   if( !is_dir($fullpath) ) {
    if ( !chmod($fullpath, $filemode) ){
     return false;
    }
   } else {
    if ( !$this->chmodR($fullpath, $filemode) ) {
     return false;
    }
   }
  }
 }
 
 closedir($dh);
 
 if ( chmod($path, $filemode) ) {
  return true;
 } else {
  return false;
 }
}




}

runtest(new TestSolrIndexXslt());

?>
