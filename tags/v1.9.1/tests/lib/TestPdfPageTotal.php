<?php
require_once("../bootstrap.php");

class TestPdfPageTotal extends UnitTestCase {
  private $helper;

  function setUp() {
    $this->helper = new Etd_Controller_Action_Helper_PdfPageTotal();
  }
  
  function tearDown() {}

  function testPageTotal(){
    $this->assertEqual(8, $this->helper->pagetotal("../fixtures/tinker_sample.pdf"));
    
  }

}


runtest(new TestPdfPageTotal());

