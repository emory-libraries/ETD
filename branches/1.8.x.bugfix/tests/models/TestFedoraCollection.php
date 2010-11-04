<?php

require_once("../bootstrap.php");
require_once("FedoraCollection.php");

class TestFedoraCollection extends UnitTestCase {
  private $collection;

  function setUp() {
    $this->collection = new FedoraCollection();
  }

  function tearDown() {}

  function testBasicProperties() {
    $this->assertIsA($this->collection, "FedoraCollection");
    $this->assertIsA($this->collection, "FedoraCollection");

    //inherited datastreams
    $this->assertIsA($this->collection->dc, "dublin_core");
    $this->assertIsA($this->collection->rels_ext, "rels_ext");

    // content model
    $this->assertNotNull($this->collection->rels_ext->hasModel);
    $this->assertEqual("emory-control:Collection-1.0", $this->collection->rels_ext->hasModel);
  }

  function testSetOaiSetInfo() {
    $this->collection->setOAISetInfo("OAI:set", "my oai set");
    $this->assertEqual("OAI:set", $this->collection->rels_ext->oaiSetSpec);
    $this->assertEqual("my oai set", $this->collection->rels_ext->oaiSetName);

    $this->collection->setOAISetInfo("OAI:newset", "my other oai set");
    $this->assertEqual("OAI:newset", $this->collection->rels_ext->oaiSetSpec);
    $this->assertEqual("my other oai set", $this->collection->rels_ext->oaiSetName);
    $this->assertNoPattern("|<oai:setSpec>.*<oai:setSpec>|", $this->collection->rels_ext->saveXML(),
                           "setSpec should only appear once in rels-ext");
    $this->assertNoPattern("|<oai:setName>.*<oai:setName>|", $this->collection->rels_ext->saveXML(),
                           "setName should only appear once in rels-ext");
  }

}


runtest(new TestFedoraCollection());