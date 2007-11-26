<?php

require_once('models/etd.php');
require_once('models/etd_html.php');

class TestEtdHtml extends UnitTestCase {
    private $etd_html;

  function setUp() {
    $fname = 'fixtures/etd1.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $etd = new etd($dom);
    $this->etd_html = $etd->html;
  }

  function tearDown() {
  }
  
  function testBasicProperties() {
    // test that foxml properties are accessible
    $this->assertIsA($this->etd_html, "etd_html");
    $this->assertEqual("Why <i>I</i> like cheese", $this->etd_html->title);
    $this->assertEqual("<b>gouda</b> or <i>cheddar</i>?", $this->etd_html->abstract);
    $this->assertEqual("Chapter 1: Gouda<br/> Chapter 2: Brie", $this->etd_html->contents);
  }

  function testCleanTags() {
    // unnecessary entities
    $this->assertEqual("\"they're great! -\"",
		       etd_html::cleanTags("&lt;b&gt;&ldquo;they&rsquo;re great!&nbsp;&ndash;&rdquo;"));
    $this->assertEqual("text",
		       etd_html::cleanTags("<p class='MSONORMAL'>text</p>"));

    $this->assertEqual("first line second line",
		       etd_html::cleanTags("first line<br/> second line"));
    $this->assertEqual("first line<br/> second line",
		       etd_html::cleanTags("first line<br/> second line", true));
  }

  function testTagsToNodes() {
    // no tags
    $this->etd_html->title = "unformatted title";
    $this->assertEqual("unformatted title", $this->etd_html->title);

    // tags all at same level
    $this->etd_html->contents = "<b>chapter 1</b> <br/> <i>chapter 2</i>";
    $this->assertEqual("<b>chapter 1</b> <br/> <i>chapter 2</i>", $this->etd_html->contents);

    // one tag inside another 
    $this->etd_html->contents = "<p>chapter 1 <br/> chapter 2</p>";
    $this->assertEqual("<p>chapter 1 <br/> chapter 2</p>", $this->etd_html->contents);

    // tags nested two levels deep
    $this->etd_html->contents = "<p>chapter <font>1 <br/> ch</font>apter 2</p>";
    $this->assertEqual("<p>chapter <font>1 <br/> ch</font>apter 2</p>", $this->etd_html->contents);

  }
  
}
?>