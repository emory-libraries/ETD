<?php
require_once("../bootstrap.php");

class TestSolrIndexXslt extends UnitTestCase {
    private $xsl;
    private $tmpDir='/tmp/fedora/get';

    function setUp() {
        //Load the xslt file
        $xslpath = "../../src/public/xslt/etdFoxmlToSolr.xslt";
	$this->xsl = new XSLTProcessor();
	$xsldom = new DOMDocument();
	$xsldom->load($xslpath);
	$this->xsl->importStylesheet($xsldom);
        //change the REPOSITORY URL param so it does not depend on fedora
        //Instead, read from the local file system
	$this->xsl->setParameter('', 'REPOSITORYURL', '/tmp/fedora/');

        //Make the mock RELS-EXT and MODS files for test:etd1
        mkdir("{$this->tmpDir}/test:etd1", 0777, true); 
        copy('../fixtures/etd1.managed.RELS-EXT.xml', "{$this->tmpDir}/test:etd1/RELS-EXT");
        copy('../fixtures/etd1.managed.MODS.xml', "{$this->tmpDir}/test:etd1/MODS");

        //Make the mock RELS-EXT files for test:etdfile1
        mkdir("{$this->tmpDir}/test:etdfile1", 0777, true); 
        copy('../fixtures/etdfile.managed.RELS-EXT.xml', "{$this->tmpDir}/test:etdfile1/RELS-EXT");


    }


    function tearDown() {
        //Remove temp files
        $this->delDir("/tmp/fedora/"); // trailing slash is required
    }

    function test_etdToFoxml() {
        $xml = new DOMDocument();
        $xml->load("../fixtures/etd1.managed.xml");
	$etd = new etd($xml);
	 // ignore php errors - "indirect modification of overloaded property
	$errlevel = error_reporting(E_ALL ^ E_NOTICE);
	error_reporting($errlevel);     // restore prior error reporting

        $result = $this->transformDom($etd->dom);
        //print "<pre>";
        //print htmlentities($result);
        //print "</pre>";
        $this->assertTrue($result, "xsl transform returns data");
	$this->assertPattern("|<add>.+</add>|s", $result, "xsl result is a solr add document");
	$this->assertPattern('|<field name="PID">test:etd1</field>|', $result,
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
        $xml = new DOMDocument();
        $xml->load("../fixtures/etdfile.managed.xml");

        $result = $this->transformDom($xml);
	$this->assertNoPattern("|<add>.+</add>|s", $result,
			       "xsl result for non-etd is NOT a solr add document");
    }
    
    function transformDom($dom) {
      return $this->xsl->transformToXml($dom);
    }

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

}

runtest(new TestSolrIndexXslt());

?>
