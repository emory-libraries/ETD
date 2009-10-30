<?php
require_once("../bootstrap.php");

class TestSolrIndexXslt extends UnitTestCase {
    private $xsl;

    function TestCleanModsXslt() {
        $xslpath = "../../src/public/xslt/etdFoxmlToSolr.xslt";
	$this->xsl = new XSLTProcessor();
	$xsldom = new DOMDocument();
	$xsldom->load($xslpath);
	$this->xsl->importStylesheet($xsldom);
    }

    function setUp() {}

    function tearDown() {}

    function test_etdToFoxml() {
        $xml = new DOMDocument();
        $xml->load("../fixtures/etd1.xml");
	$etd = new etd($xml);
	$etd->mods->date = "2008-05-30";	// set a pub date
	$etd->mods->addKeyword("keyword1");	// keyword to be indexed
	$etd->mods->addKeyword("");		// empty keyword - should not be indexed
	 // ignore php errors - "indirect modification of overloaded property
	$errlevel = error_reporting(E_ALL ^ E_NOTICE);
	$etd->mods->chair[0]->full = "Duck, Donald";
	$etd->mods->committee[0]->full = "Dog, Pluto";
	$etd->mods->addCommittee("", "");	// blank committe name - should not be indexed
	error_reporting($errlevel);     // restore prior error reporting

        $result = $this->transformDom($etd->dom);
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
        $xml->load("../fixtures/etd1.xml");
	$foxml = new foxml($xml);
	$foxml->state = "Inactive";

        $result = $this->transformDom($foxml->dom);
	$this->assertNoPattern("|<add>.+</add>|s", $result,
			       "xsl result for inactive etd is NOT a solr add document");
    }
    
    // test non-etd - should not be indexed
    function test_etdToFoxml_nonEtd() {
        $xml = new DOMDocument();
        $xml->load("../fixtures/etdfile.xml");

        $result = $this->transformDom($xml);
	$this->assertNoPattern("|<add>.+</add>|s", $result,
			       "xsl result for non-etd is NOT a solr add document");
    }
    
    function transformDom($dom) {
      return $this->xsl->transformToXml($dom);
    }

}

runtest(new TestSolrIndexXslt());

?>