<?php
require_once("../bootstrap.php");
require_once('models/datastreams/etd_mods.php');

class TestCleanModsXslt extends UnitTestCase {
    private $config;
    private $saxonb;
    private $xsl;

    function TestCleanModsXslt() {
        $this->config = Zend_Registry::get("config");
        $this->saxonb = $this->config->saxonb_xslt;        
        $this->xsl = "../../src/public/xslt/cleanMods.xsl";
        if ($this->saxonb == '' || ! is_file($this->saxonb))
            trigger_error("saxonb-xslt is not configured properly, tests will fail");
    }

    function setUp() {}

    function tearDown() {}

    function test_cleanMods() {
        $xml = new DOMDocument();
        $xml->load("../fixtures/mods.xml");
        $mods = new etd_mods($xml);

        // ignore php errors - "indirect modification of overloaded property
        $errlevel = error_reporting(E_ALL ^ E_NOTICE);        
        // set missing name ids so output validity check will work
        $mods->chair[0]->id = "wdisney";
        $mods->committee[0]->id = "dduck";
        error_reporting($errlevel);	    // restore prior error reporting

        $result = $this->transformDom($mods);
        $this->assertTrue($result, "xsl transform returns data");

        if ($result) {
            $this->assertNoPattern('|<mods:note type="admin"|', $result,
                        "admin notes are not present in cleaned MODS output");

            $this->assertNoPattern('|<mods:name type="personal">.*<mods:namePart type="given"/>|',
                        "empty names removed from output");

	    $this->assertNoPattern('|<mods:name type="personal" ID=".*"|', $result,
				   "personal name IDS are not present in cleaned output");
			

            $dom = new DOMDocument();
            $dom->loadXML($result);
            $mods = new etd_mods($dom);
            $this->assertTrue($mods->isValid($err), "clean MODS is schema-valid");
        }
    }

    function test_NoEmbargo() {
        $xml = new DOMDocument();
        $xml->loadXML(file_get_contents("models/datastreams/etd_mods.xml", FILE_USE_INCLUDE_PATH));
        $mods = new etd_mods($xml);        
        
        $mods->embargo = "Embargoed for 0 days";
        $result = $this->transformDom($mods);
        $this->assertTrue($result, "xsl transform returns data");
        
        $this->assertNoPattern('|<mods:accessCondition type="restrictionOnAccess"|', $result,
                                "restrictionOnAccess not present when embargo duration is 0 days");
    }

    function test_StillEmbargoed() {
        $xml = new DOMDocument();
        $xml->loadXML(file_get_contents("models/datastreams/etd_mods.xml", FILE_USE_INCLUDE_PATH));
        $mods = new etd_mods($xml);

        $mods->embargo = "1 year";
        $today = date("Y-m-d");
        // unix timestamp representation of "actual" publication date
        $datetime = strtotime($today, 0);
        // set an embargo end date one year in the future
        $mods->embargo_end = date("Y-m-d", strtotime("1 year", $datetime));
        $result = $this->transformDom($mods);
        $this->assertTrue($result, "xsl transform returns data");
        
        $this->assertPattern('|<mods:accessCondition type="restrictionOnAccess">\s*Access to PDF is restricted until ' . $mods->embargo_end . '\s*</mods:accessCondition>|', $result,
                                "restrictionOnAccess-- access restricted until embargo end date ");
    }

   function test_EmbargoExpired() {
        $xml = new DOMDocument();
        $xml->loadXML(file_get_contents("models/datastreams/etd_mods.xml", FILE_USE_INCLUDE_PATH));
        $mods = new etd_mods($xml);

        $mods->embargo = "1 year";
        $today = date("Y-m-d");
        // unix timestamp representation of "actual" publication date
        $datetime = strtotime($today, 0);
        // set an embargo end date one year in the past
        $mods->embargo_end = date("Y-m-d", strtotime("-1 year", $datetime));
        $result = $this->transformDom($mods);
        $this->assertTrue($result, "xsl transform returns data");

        $this->assertPattern('|<mods:accessCondition type="restrictionOnAccess">PDF available</mods:accessCondition>|', $result,
                                "restrictionOnAccess-- PDF available since embargo is over");
    }



   function test_oldEmbargoDateFormat() {
     $xml = new DOMDocument();
     $xml->loadXML(file_get_contents("models/datastreams/etd_mods.xml", FILE_USE_INCLUDE_PATH));
     $mods = new etd_mods($xml);
     
     //     $mods->embargo = "1 year";
     $today = date("Y-m-d");
     // unix timestamp representation of "actual" publication date
     $datetime = strtotime($today, 0);
     // set an embargo end date one year in the future - use OLD style date format
     $mods->embargo_end = date(DATE_W3C, strtotime("+1 year", $datetime));
     // date format expected to be in output
     $ymd_embargo_end = date("Y-m-d", strtotime("+1 year", $datetime));
     $result = $this->transformDom($mods);
     $this->assertTrue($result, "xsl transform returns data when embargo date is old format");
     
     $this->assertPattern('|<mods:accessCondition type="restrictionOnAccess">\s*Access to PDF is restricted until '
			  . $ymd_embargo_end . '</mods:accessCondition>|', $result,
			  "old embargo date comes through correctly in access condition");
     
   }




    function transformDom($dom) {
        $tmpfile = tempnam($this->config->tmpdir, "cleanModsXsl-");
        file_put_contents($tmpfile, $dom->saveXML());
        $output = $this->transform($tmpfile);
        unlink($tmpfile);
        return $output;
    }

    function transform($xmlfile) {
        return shell_exec($this->saxonb . " -xsl:" . $this->xsl . " -s:" . $xmlfile . " -warnings:silent");
    }

}

runtest(new TestCleanModsXslt());

?>