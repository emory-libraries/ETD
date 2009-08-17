<?php
require_once("../bootstrap.php");
require_once('models/etd.php');
require_once('models/etd_html.php');

class TestEtdHtml extends UnitTestCase {
    private $etd_html;

  function setUp() {
    $fname = '../fixtures/etd1.xml';
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
    $this->assertPattern("|\s*\"they're great! -\"|",
		       etd_html::cleanTags("&lt;b&gt;&ldquo;they&rsquo;re great!&nbsp;&ndash;&rdquo;"));
    //    $this->assertEqual("text",
    //		       etd_html::cleanTags("<p class='MSONORMAL'>text</p>"));

    $this->assertPattern("|first line\s*second line|",
			 etd_html::cleanTags("first line<br/> second line"));
    $this->assertPattern("|first line\s*<br/>\s*second line|",
			 etd_html::cleanTags("first line<br/> second line", true));

    $this->assertPattern("|first line\s*<br clear=['\"]all['\"]\s*/>\s*second line|",
			 etd_html::cleanTags("first line<br clear='all'/> second line", true));
    
    $this->assertEqual("some text",
		       etd_html::cleanTags("<strong/>some text", true));


    // remove microsoft junk formatting (Trac ticket #355)
    $this->assertPattern("|<p>text</p>|",
		       etd_html::cleanTags('<p class="MsoNormal">text</p>', true));
    $this->assertPattern("|<p>text</p>|",
		       etd_html::cleanTags('<p style="margin: 0cm 0cm 0pt; font-weight:bold;line-height:200%">text</p>', true));
    $this->assertPattern("|<p>text</p>|",
		       etd_html::cleanTags('<p><span lang="EN-US" xml:lang="EN-US">text</p>', true));

    // MS classes mixed with FCKeditor classes: remove only the unwanted MS classname
    $this->assertPattern("|<p class=\"Indent1\">text</p>|",
		       etd_html::cleanTags('<p class="Indent1 MsoNormal">text</p>', true));
    $this->assertPattern("|<p class=\"JustifyLeft\">text</p>|",
		       etd_html::cleanTags('<p class="MsoNormal JustifyLeft">text</p>', true));
    $this->assertPattern("|<p class=\"Indent2 JustifyRight\">text</p>|",
		       etd_html::cleanTags('<p class="Indent2 MsoNormal JustifyRight">text</p>', true));
  }

  function testRemoveTags() {
    $this->assertPattern("/^content in divs\s+$/",
		       etd_html::removeTags("<div>content in divs</div>", true));
    // no top-level wrapping element
    $this->assertPattern("/paragraph 1\s+paragraph 2/",
		       etd_html::removeTags("<p>paragraph 1</p> <p>paragraph 2</p>", true));
    $this->assertPattern("/^formatting\s+$/",
		       etd_html::removeTags("<p class='MsoNormal' font-color='black'>formatting</p>", true));

    // non-encoded special characters should be tidied, not lost
    // checking for unicode OR character entity, using utf-8 mode
    $this->assertPattern("/Development (\x{03c6}|&#x3C6;)(\x{03b3}|&#x3B3;)(\x{03c5}|&#x3C5;) (\x{03bb}|&#x3BB;)(\x{03bf}|&#x3BF;)(\x{03c6}|&#x3C6;)(\x{03b3}|&#x3B3;)(\x{03b2}|&#x3B2;)/u",
			 etd_html::removeTags("Development &phi;&gamma;&upsilon; &lambda;&omicron;&phi;&gamma;&beta;"), "html-style entities converted to unicode");

    // ampersand
    $this->assertEqual("Masquerading Politics: Power &amp; Transformation",
		       etd_html::removeTags("Masquerading Politics: Power & Transformation"));
    
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
    $this->assertPattern("|\s*<b>chapter 1</b>\s*<br/>\s*<i>chapter 2</i>|", $this->etd_html->contents);

    // one tag inside another 
    $this->etd_html->contents = "<p>chapter 1 <br/> chapter 2</p>";
    $this->assertPattern("|\s*<p>chapter 1\s*<br/>\s*chapter 2</p>|", $this->etd_html->contents);

    // tags nested two levels deep
    $this->etd_html->contents = "<p>chapter <font>1 <br/> ch</font>apter 2</p>";
    $this->assertPattern("|\s*<p>chapter 1\s*<br/>\s*chapter 2</p>|", $this->etd_html->contents);

    // bad xml: close tag without open tag (should be ignored)
    $this->etd_html->abstract = "<p>here is my </em> whoops</p>";
    $this->assertPattern("|<p>here is my\s+whoops</p>|", $this->etd_html->abstract);

    // node following directly after another one (ticket:102)
    $this->etd_html->abstract = '<div><b>Background:</b> lots of text here.</div>';
    $this->assertPattern('|<div>\s*<b>Background:</b> lots of text here.</div>|', $this->etd_html->abstract);
 
    // quote entities (see ticket:102)
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
    $this->assertPattern('/character (\x{03b2}|&#x3B2;) mid-sentence/u', $this->etd_html->abstract,
			 "mid-sentence named character entity converted to unicode");

    // named entities can't be saved - should be converted to numeric
    $this->etd_html->abstract = 'named entity &eacute; character';
    $this->assertPattern('/named entity (\x{00e9}|&#xE9;) character/u', $this->etd_html->abstract,
			 "named character entity converted to unicode");

    
    // indentation getting stripped out
    $this->etd_html->contents = "<p style='margin-left:40px;'>indented paragraph</p><p>second paragraph</p>";
    $this->assertPattern("|<p style=['\"]margin-left:40px;['\"]>indented paragraph</p>|", $this->etd_html->contents); 

    // ampersands causing problems - try both escaped and unescaped
    $this->etd_html->abstract = 'content with & ampersand';
    $this->assertEqual('content with &amp; ampersand', $this->etd_html->abstract);
    $this->etd_html->abstract = 'content with &amp; ampersand';
    $this->assertEqual('content with &amp; ampersand', $this->etd_html->abstract);

    // spaces getting added between superscripts and subscripts 
    $this->etd_html->abstract = "Cs<sub>3</sub>K<sub>2</sub>Na<sub>4</sub>[Cs<sub>2</sub>K(H<sub>2</sub>O)";
    $this->assertEqual("Cs<sub>3</sub>K<sub>2</sub>Na<sub>4</sub>[Cs<sub>2</sub>K(H<sub>2</sub>O)", $this->etd_html->abstract);


    // < character as entity
    $this->etd_html->abstract = "<p>1 &lt; 100</p>";
    $this->assertPattern("|<p>1 &lt; 100</p>|", $this->etd_html->abstract);
    // also fine unescaped
    $this->etd_html->abstract = "<p>2 < 50</p>";
    $this->assertPattern("|<p>2 &lt; 50</p>|", $this->etd_html->abstract);

    // shouldn't cause a fatal error if given bad xml...
    // FIXME: how to trigger an error that tidy can't clean up?
    
  }

  public function testSetContentsWithWhitespace() {
    $this->etd_html->setContentsWithWhitespace("Here is some text &nbsp;&nbsp; and some more");
    $this->assertPattern("/Here is some text (\x{00a0}|&#xA0;)(\x{00a0}|&#xA0;) and some more/u",
			 $this->etd_html->contents,
			 "non-breaking spaces converted to unicode");
  }
  
}


runtest(new TestEtdHtml());
?>