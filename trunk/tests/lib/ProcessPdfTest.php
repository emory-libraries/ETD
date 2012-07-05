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
    // advisor & committee no longer pre-populated by pdf extractor
    $this->assertEqual(array(), $this->processpdf->fields['advisor']);
    $this->assertEqual(array(), $this->processpdf->fields['committee']);
    $this->processpdf->initialize_fields();
    $dom->loadXML("<div>Yet Another Thesis (YAT)<br/>By<br/>A. Grad. Student<br/>
      Jane Smith, Advisor<br/>
      Marsha Brady, Reader<br/>
      Joe Smitt, Ph.D., Committee Member<br/>
      Freddie Prinze Jr., Committee Member<br/></div>");
    $this->processpdf->processSignaturePage($dom);
    $this->assertEqual("Yet Another Thesis (YAT)", $this->processpdf->fields['title']);
    
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
    $this->assertEqual(0, count($this->processpdf->fields['advisor']));
    
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

  }

  /** test pulling information from sample html documents generated from real pdfs **/
  function testGetInformation_xu() {
    $this->processpdf->getInformation("../fixtures/xu_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Exploring the peptide nanotube formation from the self-assembly/", $fields['title'], "first line of title");
    $this->assertPattern("/peptide in the presence of Zinc ions/", $fields['title'], "second line of title");
    // department detection disabled when committee member detection was removed
    //$this->assertEqual("Chemistry", $fields['department']);
    $this->assertPattern("/Peptide\s+ribbons\s+have\s+been\s+proposed\s+to\s+be/", $fields['abstract'], "abstract");
    // from second page of abstract
    $this->assertPattern("/assembled\s+peptide\s+nanotubes,\s+with\s+such\s+ordered\s+and\s+dense\s+packed/",
       $fields['abstract'], "second page of abstract");
    $this->assertTrue($fields["distribution_agreement"]);
  }
  
  function testGetInformation_li() {
    $this->processpdf->getInformation("../fixtures/li_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Influence of Arginine 355 and Glutamate 758/", $fields['title']);
    $this->assertPattern("/Properties of <i>C. acidovorans<\/i> Xanthine/", $fields['title']);
    //$this->assertEqual("Chemistry", $fields['department']);
    $this->assertPattern("/Xanthine dehydrogenase \(XDH\) was engineered/", $fields['abstract']);
    // formatting in abstract
    $this->assertPattern("/mutant expressed from\s+<i>P. aeruginosa<\/i>/",
       $fields['abstract']);
    // from second page of abstract
    $this->assertPattern("/catalytic mechanism of xanthine hydroxylation/",
       $fields['abstract']);
        $this->assertTrue($fields["distribution_agreement"]);
  }

  function testGetInformation_davidson() {
    $this->processpdf->getInformation("../fixtures/davidson_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Feet in the Fire/", $fields['title']);
    $this->assertPattern("/Social Change and Continuity among the Diola of Guinea-Bissau/",
       $fields['title']);
    //$this->assertEqual("Anthropology", $fields['department']);
    $this->assertPattern("/long\s+been\s+recognized\s+for\s+their\s+capacity\s+to\s+grow/",
       $fields['abstract'], "abstract");
    $this->assertTrue($fields["distribution_agreement"]);
    }
  function testGetInformation_strickland() {
    $this->processpdf->getInformation("../fixtures/strickland_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Ambient Air Pollution and Cardiovascular Malformations in Atlanta, Georgia/", $fields['title']);
          
    //$this->assertEqual("Epidemiology", $fields['department']);
    $this->assertPattern("/temporal\s+relationships\s+between\s+ambient\s+air\s+pollution\s+levels/",
       $fields['abstract']);
    $this->assertTrue($fields["distribution_agreement"]);
  }
  function testGetInformation_tinker() {
    $this->processpdf->getInformation("../fixtures/tinker_sample.html");
    $fields = $this->processpdf->fields;
    $this->assertPattern("/Drinking\s+Water\s+and\s+Gastrointestinal\s+Illness\s+in\s+Atlanta,\s+1993\s+.*\s+2004/",  
       $fields['title']);
    // NOTE: dash in the year is coming through as character entity
    
    //$this->assertEqual("Epidemiology", $fields['department']);
    $this->assertPattern("/municipal\s+drinking\s+water\s+may\s+contribute\s+to/", $fields['abstract']);
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
  
  function testPositionMatch() {
    $needle = "SUMMATIVE EVALUATION OF A WORKSHOP IN COLLABORATIVE COMMUNICATION";
    $haystack = "SUMMATIVE EVALUATION 
                <br/>OF A WORKSHOP IN  
                <br/>COLLABORATIVE COMMUNICATION<br/>A Collaborative Communication workshop designed<br/>
                Space Inc, and associates, 
                <br/>was evaluated for effectiveness in furthering targeted
                skills, intentions, behaviors and outcomes. 
                <br/>Rooted in the Nonviolent Communicationsm (NVC) model
                developed<br/>Rosenberg, the workshop fosters intra- and interpersonal
                relationships of compassion, 
                <br/>connection, collaboration and caring. As such it seeks to
                enhance individual and relational 
                <br/>wellbeing. Recommendations are made 
                <br/>regarding potential target audiences, marketing, course
                emphasis and further study.  
                <br/>";
    $position = $this->processpdf->positionMatch($haystack, $needle);
    $this->assertEqual($position, 110);
    
    $needle = "SUMMATIVE EVALUATION OF A WORKSHOP IN COLLABORATIVE COMMUNICATION";
    $haystack = "SUMMATIVE EVALUATION 
                <br/>OF A WORKSHOP IN  
                <br/>COLLABORATIVE COMMUNICATION<br/>A Collaborative Communication workshop designed<br/>
                Space Inc, and associates, 
                <br/>was evaluated for effectiveness in furthering targeted
                skills, intentions, behaviors and outcomes.  
                <br/>";
    $position = $this->processpdf->positionMatch($haystack, $needle);
    $this->assertEqual($position, 110); 
    
    $haystack = "XSUMMATIVE EVALUATION 
                <br/>OF A WORKSHOP IN  
                <br/>COLLABORATIVE COMMUNICATION<br/>A Collaborative Communication workshop designed<br/>
                Space Inc, and associates, 
                <br/>was evaluated for effectiveness in furthering targeted
                skills, intentions, behaviors and outcomes.  
                <br/>";
    $position = $this->processpdf->positionMatch($haystack, $needle);
    $this->assertEqual($position, 0);       

    // NOTE: removed tests for non-breaking spaces in process_page because they are now handled by tidy
  }  
}

runtest(new TestProcessPdf());
