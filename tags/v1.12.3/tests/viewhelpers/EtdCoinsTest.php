<?php
require_once("../bootstrap.php");
require_once('views/helpers/EtdCoins.php');

class TestEtdCoins extends UnitTestCase {
    private $helper;
    private $etd;

    function setUp() {
        $this->helper = new Zend_View_Helper_EtdCoins();
        
        $dom = new DOMDocument();
        $dom->load('../fixtures/etd2.xml');
        $this->etd = new etd($dom);        
    }

    function tearDown() {}

    function test_etdCoins() {
        $coins = $this->helper->EtdCoins($this->etd);
        
        $this->assertPattern('|^<span class="Z3988"[^>]*>\s*</span>$|s', $coins,
                    "coin is a valid span");
        $this->assertPattern('|title=".*ctx_ver=Z39.88-2004.*"|', $coins,
                    "ctx version is present");
        $this->assertPattern('|title=".*rft_val_fmt=info:ofi/fmt:kev:mtx:dissertation.*"|', $coins,
                    "format is dissertation");        
        $this->assertPattern('|title=".*rft.title=' . str_replace('+', '\+', urlencode($this->etd->mods->title)) . '.*"|s', $coins,
                    "citation includes url-encoded title from etd");
        $this->assertPattern('|title=".*rft.au=' . str_replace('+', '\+', urlencode($this->etd->mods->author->full)) . '.*"|s', $coins,
                    "citation includes url-encoded author from etd");
        $this->assertPattern('|title=".*rft.aulast=' . $this->etd->mods->author->last . '.*"|s', $coins,
                    "citation includes author lastname from etd");
        $this->assertPattern('|title=".*rft.aufirst=' . $this->etd->mods->author->first. '.*"|s', $coins,
                    "citation includes author firstname from etd");
        $this->assertPattern('|title=".*rft.advisor=' . str_replace('+', '\+', urlencode($this->etd->mods->chair[0]->full)) . '.*"|s', $coins,
                    "citation includes url-encoded advisor name from etd");
        $this->assertPattern('|title=".*rft.inst=' . str_replace('+', '\+', urlencode($this->etd->mods->degree_grantor->namePart)) . '.*"|s', $coins,
                    "citation includes url-encoded degree grantor as institution");
        $this->assertPattern('|title=".*rft.degree=' . $this->etd->mods->degree . '.*"|s', $coins,
                    "citation includes degree from etd");
        $this->assertPattern('|title=".*rft.identifier=' . urlencode($this->etd->mods->identifier) . '.*"|s', $coins,
                    "citation identifier is full ark from etd");
        $this->assertPattern('|title=".*rft.tpages=' . $this->etd->mods->pages . '.*"|s', $coins,
                    "citation total pages is page count from etd record");
        $this->assertPattern('|title=".*rft.language=' . $this->etd->mods->language->text . '.*"|s', $coins,
                    "language from etd record present in citation");
        $this->assertPattern('|title=".*rft.rights=' . str_replace('+', '\+', urlencode($this->etd->mods->rights)) . '.*"|s', $coins,
                    "rights statement from etd record present in citation");
        $this->assertPattern('|title=".*rft.description=' . str_replace('+', '\+', urlencode($this->etd->mods->abstract)) . '.*"|s', $coins,
                    "abstract from etd record present in citation");


    }


}

runtest(new TestEtdCoins());

?>