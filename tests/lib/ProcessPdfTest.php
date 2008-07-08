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

  function testProcessSignaturePage() {
    $content = "Dissertation title by Graduate Student";
    $lines = array("Jane Doe", "Advisor",
		   "George Jetson", "Reader",
		   "John Smith, Ph.D.", "Committee Member",
		   "Fred Jones, Committee Member");
    $this->processpdf->processSignaturePage($content, $lines);
    $this->assertEqual("Jane Doe", $this->processpdf->fields['advisor']);
    $this->assertEqual("George Jetson", $this->processpdf->fields['committee'][0]);
    $this->assertEqual("John Smith", $this->processpdf->fields['committee'][1]);
    $this->assertEqual("Fred Jones", $this->processpdf->fields['committee'][2]);
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


  /** test pulling information from sample html documents generated from real pdfs **/
  
  function testGetInformation_xu() {
    $this->processpdf->getInformation("../fixtures/xu_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Exploring the peptide nanotube formation from the self-assembly/", $fields['title']);
    $this->assertPattern("/peptide in the presence of Zinc ions/", $fields['title']);
    $this->assertEqual("David G. Lynn", $fields['advisor']);
    $this->assertEqual("Vincent P. Conticello", $fields['committee'][0]);
    $this->assertEqual("Stefan Lutz", $fields['committee'][1]);
    $this->assertEqual("Chemistry", $fields['department']);
    $this->assertPattern("/Peptide ribbons have been proposed to be/", $fields['abstract']);
    // from second page of abstract
    $this->assertPattern("/assembled peptide nanotubes, with such ordered and dense packed/",
			 $fields['abstract']);
    $this->assertPattern("/protein.*based.*nanomaterials/i", $fields['toc']);

  }
  
  function testGetInformation_li() {
    $this->processpdf->getInformation("../fixtures/li_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Influence of Arginine 355 and Glutamate 758/", $fields['title']);
    $this->assertPattern("/Properties of <i>C. acidovorans<\/i> Xanthine/", $fields['title']);
    $this->assertEqual("Dale E. Edmondson", $fields['advisor']);
    $this->assertEqual("Cora E. MacBeth", $fields['committee'][0]);
    $this->assertEqual("Kurt Warncke", $fields['committee'][1]);
    $this->assertEqual("Chemistry", $fields['department']);
    $this->assertPattern("/Xanthine dehydrogenase \(XDH\) was engineered/", $fields['abstract']);
    // formatting in abstract
    $this->assertPattern("/mutant expressed from <i>P. aeruginosa<\/i>/",
			 $fields['abstract']);
    // from second page of abstract
    $this->assertPattern("/catalytic mechanism of xanthine hydroxylation/",
			 $fields['abstract']);
    $this->assertPattern("/XOR cofactors/", $fields['toc']);

  }

  function testGetInformation_davidson() {
    $this->processpdf->getInformation("../fixtures/davidson_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Feet in the Fire/", $fields['title']);
    $this->assertPattern("/Social Change and Continuity among the Diola of Guinea-Bissau/",
			 $fields['title']);
    $this->assertEqual("Bruce M. Knauft", $fields['advisor']);
    $this->assertEqual("Ivan Karp", $fields['committee'][0]);
    $this->assertEqual("Donald L. Donham", $fields['committee'][1]);
    $this->assertEqual("Anthropology", $fields['department']);
    $this->assertPattern("/long been recognized for their capacity to grow/",
			 $fields['abstract']);
    $this->assertPattern("/Introduction: Rice, Rain, and Response/", $fields['toc']);

  }

  function testGetInformation_strickland() {
    $this->processpdf->getInformation("../fixtures/strickland_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Ambient Air Pollution and Cardiovascular Malformations in Atlanta, Georgia/", $fields['title']);
    $this->assertEqual("Paige E. Tolbert", $fields['advisor']);

    /*

    NOTE: this is how the committee members are listed on the page,
    but ProcessPDF cannot currently handle committee members listed on
    the same line.
    
    $this->assertEqual("Adolfo Correa", $fields['committee'][0]);
    $this->assertEqual("Mitchel Klein", $fields['committee'][1]);
    $this->assertEqual("W. Dana Flanders", $fields['committee'][2]);
    $this->assertEqual("Michel Marcus", $fields['committee'][3]);
    */

    $this->assertEqual("Mitchel Klein", $fields['committee'][0]);
    
    $this->assertEqual("Epidemiology", $fields['department']);
    $this->assertPattern("/temporal relationships between ambient air pollution levels/",
			 $fields['abstract']);
    $this->assertPattern("/The Importance of Nomenclature for Congenital Heart Disease/",
			 $fields['toc']);

  }

  function testGetInformation_tinker() {
    $this->processpdf->getInformation("../fixtures/tinker_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Drinking Water and Gastrointestinal Illness in Atlanta, 1993 ­ 2004/",
			 $fields['title']);
    $this->assertEqual("Paige E. Tolbert", $fields['advisor']);

    /*
     ** see note for strickland above regarding committee member detection
    $this->assertEqual("Christine L. Moe", $fields['committee'][0]);
    $this->assertEqual("Mitchel Klein", $fields['committee'][1]);
    $this->assertEqual("W. Dana Falnders", $fields['committee'][2]);
    $this->assertEqual("Appiah Amirtharajah", $fields['committee'][3]);
    */

    $this->assertEqual("Mitchel Klein", $fields['committee'][0]);
    $this->assertEqual("Appiah Amirtharajah", $fields['committee'][1]);
    $this->assertEqual("Epidemiology", $fields['department']);
    $this->assertPattern("/municipal drinking water may contribute to/", $fields['abstract']);
    $this->assertPattern("/Organisms.*Causing.*Waterborne Gastrointestinal Illness/",
			 $fields['toc']);
  }

}


runtest(new TestProcessPdf());

