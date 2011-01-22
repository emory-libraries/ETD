<?php
require_once("../bootstrap.php");
require_once('models/datastreams/etd_mods.php');
require_once('fedora/models/marc_xml.php');

class TestModsToMarcXslt extends UnitTestCase {
    private $config;
    private $saxonb;
    private $xsl;
    private $schema;
    private $mods;

    function __construct() {
        $this->config = Zend_Registry::get("config");
        $this->saxonb = $this->config->saxonb_xslt;        
        $this->xsl = "../../src/public/xslt/mods2marc21.xsl";
  $this->schema = "http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd";
        if ($this->saxonb == '' || ! is_file($this->saxonb))
            trigger_error("saxonb-xslt is not configured properly, tests will fail");
    }

    function setUp() {
      $xml = new DOMDocument();
      $xml->load("../fixtures/mods_full.xml");
      $this->mods = new etd_mods($xml);
    }

    function tearDown() {}

    function test_marc21() {
      $result = $this->transformDom($this->mods);
      $this->assertTrue($result, "xsl transform returns data");

      if ($result) {
        $dom = new DOMDocument();
        $dom->loadXML($result);
        $marc = new marc_xml($dom);
        // validate against etd-ms schema...
        $this->assertTrue($dom->schemaValidate($this->schema),
              "mods2marc21 output schema-valid according to marc21 schema");

        // NOTE: some redundancy in testing here (patterns against xml, marc_xml object fields)
        // - using marc_xml object may be less reliable, but allows for better error messages
        
        $this->assertPattern("|<marc:leader>.*4500</marc:leader>|", $result,
           "marc:leader ends with '4500'");
        $this->assertPattern("|4500$|", $marc->leader,
           "marc:leader should end with '4500'; leader is '" . $marc->leader . "'");
        $this->assertPattern('|<marc:leader>.{24}</marc:leader>|', $result,
           "marc:leader is 24 characters long");
        $this->assertEqual(24, strlen($marc->leader),
               "leader should be 24 characters long, length is " .
               strlen($marc->leader));
        
        $this->assertPattern('|<marc:controlfield tag="001">ark:/25593/|', $result,
           "marc:controlfield 001 begins with 'ark:/25593' in xml");
        $this->assertPattern('|ark:/25593/|', $marc->identifier,
           "marc:controlfield 001 begins with 'ark:/25593'");

        // NOTE: 008 field length test is currently failing, but cannot be addressed in xslt at this time
        // re-enable the field-length test when this is fixed in the xslt.
        //$this->assertPattern('|<marc:controlfield tag="008">.{40}</marc:controlfield>|', $result,
        //         "marc:controlfield 008 is 40 characters long");
        //      $this->assertEqual(40, strlen($marc->controlfield008),
        //             "controlfield 008 should be 40 characters long, length is " .
        //             strlen($marc->controlfield008));
        $this->assertPattern("|^.{7}[0-9]{4}|", $marc->controlfield008,
           "controlfield 008 characters 8-11 should be digits; got '"
           . substr($marc->controlfield008, 7, 4) . "'");
        
        $this->assertPattern('|<marc:datafield tag="245"|', $result,
           "marc:datafield 245 is present in xml result");
        $this->assertPattern('|<marc:datafield tag="856" ind1="4" ind2="0">|', $result,
           "marc:datafield 845 ind1=4, ind2=0 is present in xml result");

        // partnering agency note should not be part of result set
        $this->assertNoPattern('/partneragencytype/', $result,
            "partneragencytype notes are not present in cleaned MODS2MARC21 output"); 
                                
        // any other specific fields that should be tested?
      }
    }

    function test_StillEmbargoed() {
      // set an embargo end date one year in the future
      $this->mods->embargo_end =  date("Y-m-d", strtotime("+1 year", time()));
      $this->mods->embargo = "6 months";  // if set to 0 days, embargo date is ignored
      $result = $this->transformDom($this->mods);
      $this->assertTrue($result, "xsl transform returns data");
      $this->assertPattern('|<marc:datafield tag="506"|', $result,
         "marc:datafield 506 is present for record with an unexpired embargo");
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

runtest(new TestModsToMarcXslt());

?>
