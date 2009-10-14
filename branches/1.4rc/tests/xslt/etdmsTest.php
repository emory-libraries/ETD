<?php
require_once("../bootstrap.php");
require_once('models/etd_mods.php');

class TestEtdmsXslt extends UnitTestCase {
    private $config;
    private $saxonb;
    private $xsl;
    private $schema;

    function TestCleanModsXslt() {
        $this->config = Zend_Registry::get("config");
        $this->saxonb = $this->config->saxonb_xslt;        
        $this->xsl = "../../src/scripts/etdms.xsl";
	$this->schema = "http://www.ndltd.org/standards/metadata/etdms/1.0/etdms.xsd";
        if ($this->saxonb == '' || ! is_file($this->saxonb))
            trigger_error("saxonb-xslt is not configured properly, tests will fail");
    }

    function setUp() {}

    function tearDown() {}

    function test_etdms() {
        $xml = new DOMDocument();
        $xml->load("../fixtures/mods.xml");

	$mods = new etd_mods($xml);
        // ignore php errors - "indirect modification of overloaded property
        $errlevel = error_reporting(E_ALL ^ E_NOTICE);        
        // initialize a few fields before transforming
        $mods->degree->level = "doctoral (PhD)";
	$mods->rights  = "rights statement";
	$mods->tableOfContents  = "chapter 1 -- chapter 2";
        error_reporting($errlevel);	    // restore prior error reporting


        $result = $this->transformDom($mods);
        $this->assertTrue($result, "xsl transform returns data");

        if ($result) {
            $dom = new DOMDocument();
            $dom->loadXML($result);
	    // validate against etd-ms schema...
	    $this->assertTrue($dom->schemaValidate($this->schema),
			      "ETD-MS output is schema-valid according to ETD-MS schema");

	    // any other specific fields that should be tested?
        }
    }

    function test_StillEmbargoed() {
      $xml = new DOMDocument();
      $xml->loadXML(file_get_contents("models/mods.xml", FILE_USE_INCLUDE_PATH));
      $mods = new etd_mods($xml);

      $mods->embargo = "1 year";
      $today = date("Y-m-d");
      // unix timestamp representation of "actual" publication date
      $datetime = strtotime($today, 0);
      // set an embargo end date one year in the future
      $mods->embargo_end = date("Y-m-d", strtotime("1 year", $datetime));
      $result = $this->transformDom($mods);
      $this->assertTrue($result, "xsl transform returns data");
        
      $this->assertPattern('|<rights>Access to PDF restricted until ' . $mods->embargo_end . '</rights>|', $result,
			   "unexpired embargo included as rights");
    }


    function transformDom($dom) {
        $tmpfile = tempnam($this->config->tmpdir, "etdmsXsl-");
        file_put_contents($tmpfile, $dom->saveXML());
        $output = $this->transform($tmpfile);
        unlink($tmpfile);
        return $output;
    }

    function transform($xmlfile) {
        return shell_exec($this->saxonb . " -xsl:" . $this->xsl . " -s:" . $xmlfile . " -warnings:silent");
    }

}

runtest(new TestEtdmsXslt());

?>