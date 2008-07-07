<?php
require_once("../bootstrap.php");
//require_once('Etd/Controller/Action/Helper/ProcessPDF.php');

class TestProcessPdf extends UnitTestCase {
    private $processpdf;

  function setUp() {
    $this->processpdf = new Etd_Controller_Action_Helper_ProcessPDF();
  }
  
  function tearDown() {
  }

  function testProcessPage() {
    // find circulation agreement - by label
    $content = "Circulation Agreement";
    $this->processpdf->process_page($content, 0);
    $this->assertEqual("signature", $this->processpdf->next);
    $this->processpdf->next = "";

    // find circulation agreement - by boilerplate content
    $content = "I agree ... that the library shall make it
 	available for inspection and circulation ...";
    $this->processpdf->process_page($content, 0);
    $this->assertEqual("signature", $this->processpdf->next);

    // find table of contents - by label
    $this->processpdf->next = "";
    $content = "Table of Contents
1. chapter
2. another chapter
3. conclusion";
    $this->processpdf->process_page($content, 0);
    $this->assertPattern("/2. another chapter/", $this->processpdf->fields['toc']);
    
  }

  function testFindDepartment() {
    $content = "Department of English";
    $this->processpdf->process_page($content, 0);
    $this->assertEqual("English", $this->processpdf->fields['department']);

    // unset department so it can be set again
    $this->processpdf->fields['department'] = "";
    $content = "Chemistry Department";
    $this->processpdf->process_page($content, 0);
    $this->assertEqual("Chemistry", $this->processpdf->fields['department']);

    // test unique department names that need special rules:
    $this->processpdf->fields['department'] = "";
    $content = "Graduate Institute of the Liberal Arts";
    $this->processpdf->process_page($content, 0);
    $this->assertEqual("Graduate Institute of the Liberal Arts", $this->processpdf->fields['department']);
  }

}


runtest(new TestProcessPdf());

