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
    $this->processpdf->initialize_fields();
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
    $this->assertEqual("Doe, Jane", $this->processpdf->fields['advisor'][0]);
    $this->assertEqual("Jetson, George", $this->processpdf->fields['committee'][0]);
    $this->assertEqual("Smith, John", $this->processpdf->fields['committee'][1]);
    $this->assertEqual("Jones, Fred", $this->processpdf->fields['committee'][2]);

    $this->processpdf->initialize_fields();
    $dom->loadXML("<div>Yet Another Thesis (YAT)<br/>By<br/>A. Grad. Student<br/>
      Jane Smith, Advisor<br/>
      Marsha Brady, Reader<br/>
      Joe Smitt, Ph.D., Committee Member<br/>
      Freddie Prinze Jr., Committee Member<br/></div>");
    $this->processpdf->processSignaturePage($dom);
    $this->assertEqual("Yet Another Thesis (YAT)", $this->processpdf->fields['title']);
    $this->assertEqual("Smith, Jane", $this->processpdf->fields['advisor'][0]);
    $this->assertEqual("Brady, Marsha", $this->processpdf->fields['committee'][0]);
    $this->assertEqual("Smitt, Joe", $this->processpdf->fields['committee'][1]);
    $this->assertEqual("Freddie Prinze Jr.", $this->processpdf->fields['committee'][2]);
    
    // Test for Field/Faculty/Thesis Advisor
    $this->processpdf->initialize_fields();
    $dom->loadXML("<div>Da Da Dissertation<br/>By<br/>B. Grad. Student<br/>
      Donald Duck<br/>
      Advisor<br/>
      Daffy Duck<br/>
      Faculty Advisor<br/>
      David Duck<br/>
      Thesis Advisor<br/>
      Mickey Mouse<br/>
      Field Advisor<br/></div>");
    $this->processpdf->processSignaturePage($dom);
    $this->assertEqual("Da Da Dissertation", $this->processpdf->fields['title']);
    $this->assertEqual(4, count($this->processpdf->fields['advisor']));
    $this->assertEqual("Duck, Donald", $this->processpdf->fields['advisor'][0]);
    $this->assertEqual("Duck, Daffy", $this->processpdf->fields['advisor'][1]);
    $this->assertEqual("Duck, David", $this->processpdf->fields['advisor'][2]);        
    $this->assertEqual("Mouse, Mickey", $this->processpdf->fields['advisor'][3]);

    // title on same line as "by"
    $this->processpdf->initialize_fields();
    $dom = new DOMDocument();
    $dom->loadXML("<div>Properties and Synthesis of Red-Ox Active Proline Mimics By <br/>
      John H. Shugart <br/></div>");
    $this->processpdf->processSignaturePage($dom);
    $this->assertPattern("|Properties and Synthesis of Red-Ox Active Proline Mimics|", $this->processpdf->fields['title']);

    // FIXME: can we use tidy to clean up redundant formatting like this stuff?

    // by nested in formatting we keep
    $this->processpdf->initialize_fields();
    $dom = new DOMDocument();
    $dom->loadXML("<div><b>Social Networks and Adaptability to IT-Enabled Change: The Case of </b><br/>
      <b>Healthcare Information Technologies </b><br/>
      <b>                                                  </b><br/>
      <b>By</b><br/>
      <b> <br/> </b><br/>
      <b>Roopa Raman </b><br/>
      <b>Doctor of Philosophy </b><br/>
      </div>");
    $this->processpdf->processSignaturePage($dom);
    $this->assertPattern("|Social Networks and Adaptability to IT-Enabled Change: The Case of Healthcare Information Technologies|", $this->processpdf->fields['title']);

    // advisors and committee members - two names on the same line
    $this->processpdf->initialize_fields();
    $dom = new DOMDocument();
    $dom->loadXML("<div>
      <b> __________________________                              __________________________ <br/>
           Benn R. Konsynski, Ph.D.                                     Anandhi Bharadwaj, Ph.D.  <br/>
           Co-Adviser                                                   Co-Adviser <br/>
      <br/>_________________________                                  _________________________ <br/>
           Ramnath K. Chellappa, PhD.                                    Monica C. Worline, Ph.D. <br/>
           Committee Member                                              Committee Member <br/></b>
      </div>");
    $this->processpdf->processSignaturePage($dom);
    // FIXME: advisor not yet multiple field here?
    $this->assertEqual("Konsynski, Benn R.", $this->processpdf->fields['advisor'][0]);
    $this->assertEqual("Bharadwaj, Anandhi", $this->processpdf->fields['advisor'][1]);
    $this->assertEqual("Chellappa, Ramnath K.", $this->processpdf->fields['committee'][0]);
    $this->assertEqual("Worline, Monica C.", $this->processpdf->fields['committee'][1]);
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
    $this->assertEqual("Lynn, David G.", $fields['advisor'][0], "advisor");
    $this->assertEqual("Conticello, Vincent P.", $fields['committee'][0],
           "first committee member");
    $this->assertEqual("Lutz, Stefan", $fields['committee'][1], "second committee member");
    $this->assertEqual("Chemistry", $fields['department']);
    $this->assertPattern("/Peptide\s+ribbons\s+have\s+been\s+proposed\s+to\s+be/", $fields['abstract'], "abstract");
    // from second page of abstract
    $this->assertPattern("/assembled\s+peptide\s+nanotubes,\s+with\s+such\s+ordered\s+and\s+dense\s+packed/",
       $fields['abstract'], "second page of abstract");
    $this->assertPattern("/ZINC EFFECT IN AMYLOID\s+FORMATION/m", $fields['toc'], "table of contents");
    $this->assertTrue($fields["distribution_agreement"]);
  }
  
  function testGetInformation_li() {
    $this->processpdf->getInformation("../fixtures/li_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Influence of Arginine 355 and Glutamate 758/", $fields['title']);
    $this->assertPattern("/Properties of <i>C. acidovorans<\/i> Xanthine/", $fields['title']);
    $this->assertEqual("Edmondson, Dale E.", $fields['advisor'][0]);
    $this->assertEqual("MacBeth, Cora E.", $fields['committee'][0]);
    $this->assertEqual("Warncke, Kurt", $fields['committee'][1]);
    $this->assertEqual("Chemistry", $fields['department']);
    $this->assertPattern("/Xanthine dehydrogenase \(XDH\) was engineered/", $fields['abstract']);
    // formatting in abstract
    $this->assertPattern("/mutant expressed from\s+<i>P. aeruginosa<\/i>/",
       $fields['abstract']);
    // from second page of abstract
    $this->assertPattern("/catalytic mechanism of xanthine hydroxylation/",
       $fields['abstract']);
    $this->assertPattern("/XOR cofactors/", $fields['toc']);
    $this->assertTrue($fields["distribution_agreement"]);
  }

  function testGetInformation_davidson() {
    $this->processpdf->getInformation("../fixtures/davidson_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Feet in the Fire/", $fields['title']);
    $this->assertPattern("/Social Change and Continuity among the Diola of Guinea-Bissau/",
       $fields['title']);
    $this->assertEqual("Knauft, Bruce M.", $fields['advisor'][0]);
    $this->assertEqual("Karp, Ivan", $fields['committee'][0]);
    $this->assertEqual("Donham, Donald L.", $fields['committee'][1]);
    $this->assertEqual("Anthropology", $fields['department']);
    $this->assertPattern("/long\s+been\s+recognized\s+for\s+their\s+capacity\s+to\s+grow/",
       $fields['abstract'], "abstract");
    $this->assertPattern("/Introduction: Rice, Rain, and Response/", $fields['toc']);
    $this->assertTrue($fields["distribution_agreement"]);
    }
  function testGetInformation_strickland() {
    $this->processpdf->getInformation("../fixtures/strickland_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Ambient Air Pollution and Cardiovascular Malformations in Atlanta, Georgia/", $fields['title']);
    $this->assertEqual("Tolbert, Paige E.", $fields['advisor'][0]);
  /***

    NOTE: this is how the committee members are listed on the page,
    but ProcessPDF cannot currently handle committee members listed on
    the same line.
    
    $this->assertEqual("Adolfo Correa", $fields['committee'][0]);
    $this->assertEqual("Mitchel Klein", $fields['committee'][1]);
    $this->assertEqual("W. Dana Flanders", $fields['committee'][2]);
    $this->assertEqual("Michel Marcus", $fields['committee'][3]);
    */
    $this->assertEqual("Klein, Mitchel", $fields['committee'][0]);
    
    $this->assertEqual("Epidemiology", $fields['department']);
    $this->assertPattern("/temporal\s+relationships\s+between\s+ambient\s+air\s+pollution\s+levels/",
       $fields['abstract']);
    $this->assertPattern("/The\s+Importance\s+of\s+Nomenclature\s+for\s+Congenital\s+Heart\s+Disease/",
       $fields['toc']);
    $this->assertTrue($fields["distribution_agreement"]);
  }
  function testGetInformation_tinker() {
    $this->processpdf->getInformation("../fixtures/tinker_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Drinking\s+Water\s+and\s+Gastrointestinal\s+Illness\s+in\s+Atlanta,\s+1993\s+.*\s+2004/",  
       $fields['title']);
    // NOTE: dash in the year is coming through as character entity
    
    $this->assertEqual("Tolbert, Paige E.", $fields['advisor'][0]);
    /*
     ** see note for strickland above regarding committee member detection
    $this->assertEqual("Christine L. Moe", $fields['committee'][0]);
    $this->assertEqual("Mitchel Klein", $fields['committee'][1]);
    $this->assertEqual("W. Dana Falnders", $fields['committee'][2]);
    $this->assertEqual("Appiah Amirtharajah", $fields['committee'][3]);
    */
    $this->assertEqual("Klein, Mitchel", $fields['committee'][0]);
    $this->assertEqual("Amirtharajah, Appiah", $fields['committee'][1]);
    $this->assertEqual("Epidemiology", $fields['department']);
    $this->assertPattern("/municipal\s+drinking\s+water\s+may\s+contribute\s+to/", $fields['abstract']);
    $this->assertPattern("/Causes of Gastrointestinal Illness/", $fields['toc']);
    // TOC continues to second page
    $this->assertPattern("/7 DRINKING WATER TREATMENT PLANTS AND EMERGENCY/", $fields['toc']);
    // TOC continues to third page
    $this->assertPattern("/ADDITIONAL ANALYSIS OF DRINKING WATER TURBIDITY/", $fields['toc']);
    $this->assertTrue($fields["distribution_agreement"]);
  }

  function testGetInformation_nodistagreement() {
    $this->processpdf->getInformation("../fixtures/nocirc_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertFalse($fields["distribution_agreement"]);
  }

  function testDistributionAgreement() {
    // formatting and white space should not keep the distribution
    // agreement from being detected when the text is present
    
    $dom = new DOMDocument();
    // fake sample with messed up formatting in the middle of the text
    $dom->loadXML("<div><p>I hereby grant to Emory University</p> <br/>
      <p>non-exclusive license <hr/> to do stuff with my etd.</p></div>");
    $this->processpdf->process_page($dom);
    $this->assertTrue($this->processpdf->fields["distribution_agreement"]);

    // newer version of the distribution agreement:
    
    $dom->loadXML("<div> <br/>In presenting this thesis or dissertation
       as a partial fulfillment of the requirements for an advanced <br/>
      degree from Emory University, I hereby grant to Emory University and its agents 
      the non-<br/>
      exclusive license to archive, make accessible, and display my thesis or disserta
      tion in whole or in <br/> ...</div>");
    $this->processpdf->process_page($dom);
    $this->assertTrue($this->processpdf->fields["distribution_agreement"]);
  }

  function testGetInformation_nonbreakingspaces() {
    $this->processpdf->getInformation("../fixtures/kramer_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertTrue($fields["distribution_agreement"]);

    // NOTE: removed tests for non-breaking spaces in process_page because they are now handled by tidy
  }
  
}

runtest(new TestProcessPdf());
