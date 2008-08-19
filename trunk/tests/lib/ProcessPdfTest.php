<?php
require_once("../bootstrap.php");

class TestProcessPdf extends UnitTestCase {
    private $processpdf;

  function setUp() {
    $this->processpdf = new Etd_Controller_Action_Helper_ProcessPDF();
  }
  
  function tearDown() {
  }

  function testProcessPage() {
    // find circulation agreement - by label
    $dom = new DOMDocument();
    $dom->loadXML("<div>Circulation Agreement</div>");
    $this->processpdf->process_page($dom);
    $this->assertEqual("signature", $this->processpdf->next);
    $this->processpdf->next = "";

    // find circulation agreement - by boilerplate content
    $dom = new DOMDocument();
    $dom->loadXML("<div>I agree ... that the library shall make it
 	available for inspection and circulation ...</div>");
    $this->processpdf->process_page($dom);
    $this->assertEqual("signature", $this->processpdf->next);

    // find table of contents - by label
    $this->processpdf->next = "";
    $dom = new DOMDocument();
    $dom->loadXML("<div>Table of Contents
1. chapter
2. another chapter
3. <i>conclusion</i></div>");
    $this->processpdf->process_page($dom);
    $this->assertPattern("/2. another chapter/", $this->processpdf->fields['toc']);
    // formatting preserved
    $this->assertPattern("/<i>conclusion<\/i>/", $this->processpdf->fields['toc']);
    
  }

  function testProcessSignaturePage() {
    $dom = new DOMDocument();
    // title is expected to be followed with "by" or "By"
    // advisor/committee names expected on line before label (one allowed pattern)
    $dom->loadXML("<div><br/>Dissertation Title<br/>by<br/>Graduate Student<br/>
	Jane Doe<br/>Advisor<br/>
	George Jetson<br/> Reader<br/>
	John Smith, Ph.D.<br/> Committee Member<br/>
	Fred Jones<br/>  Committee Member</div>");
    $this->processpdf->processSignaturePage($dom);
    $this->assertEqual("Dissertation Title", $this->processpdf->fields['title']);
    $this->assertEqual("Jane Doe", $this->processpdf->fields['advisor']);
    $this->assertEqual("George Jetson", $this->processpdf->fields['committee'][0]);
    $this->assertEqual("John Smith", $this->processpdf->fields['committee'][1]);
    $this->assertEqual("Fred Jones", $this->processpdf->fields['committee'][2]);

    $this->processpdf->initialize_fields();
    $dom->loadXML("<div>Yet Another Thesis (YAT)<br/>By<br/>A. Grad. Student<br/>
	Jane Smith, Advisor<br/>
	Marsha Brady, Reader<br/>
	Joe Smitt, Ph.D., Committee Member<br/>
	Freddie Prinze Jr., Committee Member<br/></div>");
    $this->processpdf->processSignaturePage($dom);
    $this->assertEqual("Yet Another Thesis (YAT)", $this->processpdf->fields['title']);
    $this->assertEqual("Jane Smith", $this->processpdf->fields['advisor']);
    $this->assertEqual("Marsha Brady", $this->processpdf->fields['committee'][0]);
    $this->assertEqual("Joe Smitt", $this->processpdf->fields['committee'][1]);
    $this->assertEqual("Freddie Prinze Jr.", $this->processpdf->fields['committee'][2]);

    // title on same line as "by"
    $this->processpdf->initialize_fields();
    $dom = new DOMDocument();
    $dom->loadXML("<div>Properties and Synthesis of Red-Ox Active Proline Mimics By <br/>
John H. Shugart <br/></div>");
    $this->processpdf->processSignaturePage($dom);
    $this->assertPattern("|Properties and Synthesis of Red-Ox Active Proline Mimics|", $this->processpdf->fields['title']);
    
  }

  function testDepartment() {
    $line = "Department of English";
    $this->processpdf->department($line);
    $this->assertEqual("English", $this->processpdf->fields['department']);

    $line = "Chemistry Department";
    $this->processpdf->department($line);
    $this->assertEqual("Chemistry", $this->processpdf->fields['department']);

    // test unique department names that need special rules:
    $line = "Graduate Institute of the Liberal Arts";
    $this->processpdf->department($line);
    $this->assertEqual("Graduate Institute of the Liberal Arts", $this->processpdf->fields['department']);
  }


  /** test pulling information from sample html documents generated from real pdfs **/
  function testGetInformation_xu() {
    $this->processpdf->getInformation("../fixtures/xu_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Exploring the peptide nanotube formation from the self-assembly/", $fields['title'], "first line of title");
    $this->assertPattern("/peptide in the presence of Zinc ions/", $fields['title'], "second line of title");
    $this->assertEqual("David G. Lynn", $fields['advisor'], "advisor");
    $this->assertEqual("Vincent P. Conticello", $fields['committee'][0],
		       "first committee member");
    $this->assertEqual("Stefan Lutz", $fields['committee'][1], "second committee member");
    $this->assertEqual("Chemistry", $fields['department']);
    $this->assertPattern("/Peptide\s+ribbons\s+have\s+been\s+proposed\s+to\s+be/", $fields['abstract'], "abstract");
    // from second page of abstract
    $this->assertPattern("/assembled\s+peptide\s+nanotubes,\s+with\s+such\s+ordered\s+and\s+dense\s+packed/",
			 $fields['abstract'], "second page of abstract");
    $this->assertPattern("/ZINC EFFECT IN AMYLOID\s+FORMATION/m", $fields['toc'], "table of contents");

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
    $this->assertPattern("/mutant expressed from\s+<i>P. aeruginosa<\/i>/",
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
    $this->assertPattern("/long\s+been\s+recognized\s+for\s+their\s+capacity\s+to\s+grow/",
			 $fields['abstract'], "abstract");
    $this->assertPattern("/Introduction: Rice, Rain, and Response/", $fields['toc']);

    }
  function testGetInformation_strickland() {
    $this->processpdf->getInformation("../fixtures/strickland_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Ambient Air Pollution and Cardiovascular Malformations in Atlanta, Georgia/", $fields['title']);
    $this->assertEqual("Paige E. Tolbert", $fields['advisor']);
  /***

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
    $this->assertPattern("/temporal\s+relationships\s+between\s+ambient\s+air\s+pollution\s+levels/",
			 $fields['abstract']);
    $this->assertPattern("/The\s+Importance\s+of\s+Nomenclature\s+for\s+Congenital\s+Heart\s+Disease/",
			 $fields['toc']);

  }
  function testGetInformation_tinker() {
    $this->processpdf->getInformation("../fixtures/tinker_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Drinking\s+Water\s+and\s+Gastrointestinal\s+Illness\s+in\s+Atlanta,\s+1993\s+.*\s+2004/",	
			 $fields['title']);
    // NOTE: dash in the year is coming through as character entity
    
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
    $this->assertPattern("/municipal\s+drinking\s+water\s+may\s+contribute\s+to/", $fields['abstract']);
    $this->assertPattern("/Causes of Gastrointestinal Illness/", $fields['toc']);
  }

}


runtest(new TestProcessPdf());

