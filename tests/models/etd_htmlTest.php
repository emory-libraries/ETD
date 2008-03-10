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

    $this->assertEqual("first line<br/> second line",
		       etd_html::cleanTags("first line<br clear='all'/> second line", true));

    
    $this->assertEqual("some text",
		       etd_html::cleanTags("<strong/>some text", true));
    
  }

  function testRemoveTags() {
    $this->assertEqual("content in divs",
		       etd_html::removeTags("<div>content in divs</div>", true));
    
  }

  function testFormattedTOCtoText() {
    // convert html-formatted table of contents into text delimited toc for mods/marc
    
    // paragraphs with breaks
    $this->assertEqual("chapter 1 -- chapter 2",
		       etd_html::formattedTOCtoText("<p>chapter 1 <br/> chapter 2</p>"));

    // html list 
    $this->assertEqual("chapter 3 -- chapter 4",
		       etd_html::formattedTOCtoText("<ul><li>chapter 3</li> <li>chapter 4</li></ul>"));

    // html list with divs
    $this->assertEqual("chapter 5 -- chapter 6",
	       etd_html::formattedTOCtoText("<ul><li>chapter 5</li> <li><div>chapter 6</div></li></ul>"));

    // divs only
    $this->assertEqual("chapter 7 -- chapter 8",
	       etd_html::formattedTOCtoText("<div>chapter 7</div> <div>chapter 8</div>"));

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

    // bad xml: close tag without open tag (should be ignored)
    $this->etd_html->abstract = "<p>here is my </em> whoops</p>";
    $this->assertEqual("<p>here is my  whoops</p>", $this->etd_html->abstract);

    // node following directly after another one (ticket:102)
    $this->etd_html->abstract = '<div><b>Background:</b> lots of text here.</div>';
    $this->assertEqual('<div><b>Background:</b> lots of text here.</div>', $this->etd_html->abstract);
 
    // quote entities (also ticket:102)
    $this->etd_html->abstract = 'I have some &quot;text&quot; in quotes.';
    $this->assertEqual('I have some "text" in quotes.', $this->etd_html->abstract);
 
    // remove unwanted empty tags
    $this->etd_html->abstract = '<strong/>runaway bold';
    $this->assertEqual('runaway bold', $this->etd_html->abstract);

    // unwanted tag generates empty tag
    $this->etd_html->abstract = '<strong><o:p></o:p></strong>runaway bold';
    $this->assertEqual('runaway bold', $this->etd_html->abstract);


    // losing Greek characters
    $this->etd_html->abstract = 'character &beta; mid-sentence';
    $this->assertEqual('character &#x3B2; mid-sentence', $this->etd_html->abstract);
  }

  public function testSetContentsWithWhitespace() {
    $this->etd_html->setContentsWithWhitespace("Here is some text &nbsp;&nbsp; and some more");
    $this->assertEqual("Here is some text &#xA0;&#xA0; and some more", $this->etd_html->contents);
  }
  
}
?>