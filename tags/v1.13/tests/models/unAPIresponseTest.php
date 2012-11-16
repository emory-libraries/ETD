<?php
require_once("../bootstrap.php");
require_once('models/unAPIresponse.php');

class Test_unAPIresponse extends UnitTestCase {
  private $unapi;

  function setUp() {
    $this->unapi = new unAPIresponse();
  }

  function tearDown() {}

  function testBasicProperties() {
    // object types & subtypes, correctly mapping & can read values from xml
    $this->assertIsA($this->unapi, "unAPIresponse", "object has type 'unAPIresponse'");
    $this->assertFalse(isset($this->unapi->id));
    $this->assertIsA($this->unapi->format, "Array");
    $this->assertEqual(0, count($this->unapi->format));
  }

  function testAddId() {
    // test adding id
    $this->unapi->setId("URI");
    $this->assertEqual("URI", $this->unapi->id);
    $this->assertPattern('/id="URI"/', $this->unapi->saveXML());
  }

  function testAddFormat() {
    // test adding format with doc
    $this->unapi->addFormat("oai_dc", "text/xml",
			    "oai_dc.xsd");
    $this->assertEqual(1, count($this->unapi->format));
    $this->assertIsA($this->unapi->format[0], "unAPIformat");
    $this->assertEqual("oai_dc", $this->unapi->format[0]->name);
    $this->assertEqual("text/xml", $this->unapi->format[0]->type);
    $this->assertEqual("oai_dc.xsd", $this->unapi->format[0]->docs);
    $this->assertPattern('|<format name="oai_dc" type="text/xml" docs="oai_dc.xsd"/>|',
			 $this->unapi->saveXML());

    // test adding format without doc
    $this->unapi->addFormat("mods", "text/xml");   
    $this->assertEqual(2, count($this->unapi->format));
    $this->assertIsA($this->unapi->format[1], "unAPIformat");
    $this->assertEqual("mods", $this->unapi->format[1]->name);
    $this->assertEqual("text/xml", $this->unapi->format[1]->type);
    $this->assertFalse(isset($this->unapi->format[1]->doc));
    $this->assertPattern('|<format name="mods" type="text/xml"/>|',
			 $this->unapi->saveXML());
  }
}


runtest(new Test_unAPIresponse());
?>

