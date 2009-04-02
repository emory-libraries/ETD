<?php
require_once("../bootstrap.php");
require_once('models/ProQuestSubmission.php');
require_once('simpletest/mock_objects.php');
require_once("models/etdfile.php");
Mock::generate('etd_file');

class TestPQSubmission extends UnitTestCase {
  private $pq;
  
  function setUp() {
    /*    $fname = 'fixtures/pq.xml';
    $dom = new DOMDocument();
    $dom->load($fname);*/
    //    $this->pq = new ProQuestSubmission($dom);
    $this->pq = new ProQuestSubmission();
  }
  
  function tearDown() {
  }

  function testBasicProperties() {
    // test that the main parts of the xml document are mapped correctly
    $this->assertIsA($this->pq, "ProQuestSubmission");
    $this->assertIsA($this->pq->author_info, "DISS_authorship");
    $this->assertIsA($this->pq->author_info->name, "DISS_name");
    $this->assertIsA($this->pq->author_info->current_contact, "DISS_contact");
    $this->assertIsA($this->pq->author_info->current_contact->address, "DISS_address");
    $this->assertIsA($this->pq->author_info->permanent_contact, "DISS_contact");
    $this->assertIsA($this->pq->description, "DISS_description");
    $this->assertIsA($this->pq->description->advisor, "DISS_name");
    $this->assertIsA($this->pq->description->committee, "Array");
    $this->assertIsA($this->pq->description->committee[0], "DISS_name");
    $this->assertIsA($this->pq->description->categories, "Array");
    $this->assertIsA($this->pq->description->categories[0], "DISS_category");
    $this->assertIsA($this->pq->description->keywords, "DOMElementArray");
    $this->assertIsA($this->pq->abstract, "DISS_abstract");
    $this->assertIsA($this->pq->pdfs, "DOMElementArray");
    $this->assertIsA($this->pq->supplements, "Array");
    $this->assertIsA($this->pq->supplements[0], "DISS_attachment");
  }

  function testInitializeFromEtd() {
    $this->etdxml = array("etd2" => "test:etd2", "user" => "test:user1");


    $fedora = Zend_Registry::get("fedora");
    
    // note: needs to be in repository so authorInfo relation will work
    
    // load test objects to repository
    // NOTE: for risearch queries to work, syncupdates must be turned on for test fedora instance
    foreach (array_keys($this->etdxml) as $etdfile) {
	$pid = $fedora->ingest(file_get_contents('../fixtures/' . $etdfile . '.xml'), "loading test object");
    }
    
    $etd = new etd('test:etd2');

    $this->pq->initializeFromEtd($etd);
    //    print "<pre>" . htmlentities($this->pq->saveXML()) . "</pre>";
    $this->assertEqual("0", $this->pq->embargo_code);
    // author name & contact information
    $this->assertEqual($etd->mods->author->last, $this->pq->author_info->name->last);
    $this->assertEqual($etd->mods->author->first, $this->pq->author_info->name->first);
    $this->assertEqual($etd->authorInfo->mads->current->email, $this->pq->author_info->current_contact->email);
    $this->assertEqual("01/01/2001", $this->pq->author_info->current_contact->date);
    $this->assertEqual($etd->authorInfo->mads->permanent->email, $this->pq->author_info->permanent_contact->email);
    $this->assertEqual("12/15/2009", $this->pq->author_info->permanent_contact->date);
    $this->assertEqual($etd->authorInfo->mads->permanent->address->street[0],
		       $this->pq->author_info->permanent_contact->address->street[0]);
    $this->assertEqual($etd->authorInfo->mads->permanent->address->city,
		       $this->pq->author_info->permanent_contact->address->city);
    $this->assertEqual($etd->authorInfo->mads->permanent->address->state,
		       $this->pq->author_info->permanent_contact->address->state);
    $this->assertEqual($etd->authorInfo->mads->permanent->address->postcode,
		       $this->pq->author_info->permanent_contact->address->zipcode);
    // country code
    $this->assertEqual("US", $this->pq->author_info->permanent_contact->address->country);

    $this->assertEqual($etd->mods->pages, $this->pq->description->page_count);
    $this->assertEqual("test:etd2", $this->pq->description->external_id);
    $this->assertEqual("doctoral", $this->pq->description->type);
    $this->assertEqual($etd->mods->copyright, $this->pq->description->copyright);
    $this->assertEqual("2007", $this->pq->description->date_completed);
    $this->assertPattern("/[0-9]{4}/", $this->pq->description->date_completed);
    $this->assertEqual("12/30/2007", $this->pq->description->date_accepted);
    $this->assertPattern("|[0-1][0-9]/[0-3][0-9]/[0-9]{4}|", $this->pq->description->date_accepted);
    $this->assertEqual("Ph.D.", $this->pq->description->degree);	// PQ format for degree
    $this->assertEqual($etd->mods->department, $this->pq->description->department);

    // advisor & committee
    $this->assertEqual($etd->mods->advisor->first, $this->pq->description->advisor->first);
    $this->assertEqual($etd->mods->advisor->last, $this->pq->description->advisor->last);
    $this->assertEqual($etd->mods->committee[0]->first, $this->pq->description->committee[0]->first);
    $this->assertEqual($etd->mods->committee[0]->last, $this->pq->description->committee[0]->last);

    // categories, keywords
    $this->assertEqual($etd->mods->researchfields[0]->id, $this->pq->description->categories[0]->code);
    $this->assertEqual($etd->mods->researchfields[0]->topic, $this->pq->description->categories[0]->text);
    $this->assertEqual($etd->mods->keywords[0]->topic, $this->pq->description->keywords[0]);
    $this->assertEqual("EN", $this->pq->description->language); 	// PQ 2-letter code


    // abstract tested more thoroughly separately
    $this->assertPattern("|<DISS_para>milk\s+<em>curdles</em> and goes\s+<em>sour</em></DISS_para>|",
			 $this->pq->abstract->saveXML());

    // files tested separately

    // test validation
    $this->assertTrue($this->pq->isValid());
  }

  function testSetAbstract() {
    // note: need a new submission object so abstract starts blank
    $pq = new ProQuestSubmission();
    $nopara = "The sessile nature of plants...";
    $pq->abstract->set($nopara);
    $this->assertEqual(1, count($pq->abstract->p));
    $this->assertEqual($nopara, $pq->abstract->p[0]);
    
    $singlepara = "<p>The discovery of the peptide nanotube has...</p>";
    $pq = new ProQuestSubmission();
    $pq->abstract->set($singlepara);
    $this->assertEqual("The discovery of the peptide nanotube has...", $pq->abstract->p[0]);    

    $multipara = "<p>Diola villagers in Guinea-Bissau...</p>
		<p>Based on two years of ethnographic research...</p>
		<p>First, I consider</p>";
    $pq = new ProQuestSubmission();
    $pq->abstract->set($multipara);
    $this->assertEqual(3, count($pq->abstract->p));
    $this->assertEqual("Diola villagers in Guinea-Bissau...", $pq->abstract->p[0]);
    $this->assertEqual("Based on two years of ethnographic research...", $pq->abstract->p[1]);
    $this->assertEqual("First, I consider", $pq->abstract->p[2]);

    $multidiv = "<div>Understanding the structural...</div>
		<div>In the first part of my thesis...</div>";
    $pq = new ProQuestSubmission();
    $pq->abstract->set($multidiv);
    $this->assertEqual(2, count($pq->abstract->p));
    $this->assertEqual("Understanding the structural...", $pq->abstract->p[0]);
    $this->assertEqual("In the first part of my thesis...", $pq->abstract->p[1]);

    $emptydiv = "<div>Understanding the structural...</div>
		<div/>
		<div>In the first part of my thesis...</div>";
    $pq = new ProQuestSubmission();
    $pq->abstract->set($emptydiv);
    $this->assertEqual(2, count($pq->abstract->p));
    $this->assertEqual("Understanding the structural...", $pq->abstract->p[0]);
    $this->assertEqual("In the first part of my thesis...", $pq->abstract->p[1]);

    // test empty divs?
    
    $fonts = "<p> <font>  Produced in the scriptorium... </font></p>";
    $pq = new ProQuestSubmission();
    $pq->abstract->set($fonts);
    $this->assertEqual(1, count($pq->abstract->p));
    $this->assertEqual("Produced in the scriptorium...", $pq->abstract->p[0]);

    
    $formatting = "<p><i>Chapter 1.</i> Biomimetic ... and <u>ent</u>-Abudinol  <b>B</b></p>";
    $pq = new ProQuestSubmission();
    $pq->abstract->set($formatting);
    $this->assertEqual(1, count($pq->abstract->p));
    // need to use regexp to check tags
    $this->assertPattern("|<DISS_para>\s+<em>Chapter 1.</em>\s+Biomimetic ... and\s+<em>ent</em>-Abudinol\s+<strong>B</strong></DISS_para>|",
			 $pq->abstract->saveXML());
    
    $badtags = "Here is a list <ul><li>item 1</li><li>item 2</li></ul> in the middle of my text";
    $pq = new ProQuestSubmission();
    $pq->abstract->set($badtags);
    $this->assertEqual(1, count($pq->abstract->p));
    $this->assertPattern("/Here is a list\s+item 1\s+item 2\s+in the middle of my text/", $pq->abstract->p[0]);
    
  }

  function testSetFiles() {
    $pdfs = array();
    $pdfs[] = &new Mocketd_file();
    $pdfs[0]->setReturnValue('prettyFilename', 'smith_dissertation.pdf');

    $supplements = array();
    $supplements[] = &new Mocketd_file();
    $supplements[0]->setReturnValue('prettyFilename', 'smith_supplement1.pdf');
    $supplements[0]->setReturnValue('description', 'appendix');
    $supplements[] = &new Mocketd_file();
    $supplements[1]->setReturnValue('prettyFilename', 'smith_supplement2.pdf');
    $supplements[1]->setReturnValue('description', 'diagrams and charts');

    $this->pq->setFiles($pdfs, $supplements);
    $this->assertEqual(count($pdfs), count($this->pq->pdfs));
    $this->assertEqual($pdfs[0]->prettyFilename(), $this->pq->pdfs[0]);
    $this->assertEqual(count($supplements), count($this->pq->supplements));
    $this->assertEqual($supplements[0]->prettyFilename(), $this->pq->supplements[0]->filename);
    $this->assertEqual($supplements[0]->description(), $this->pq->supplements[0]->description);
    $this->assertEqual($supplements[1]->prettyFilename(), $this->pq->supplements[1]->filename);
    $this->assertEqual($supplements[1]->description(), $this->pq->supplements[1]->description);

  }

  function testAddSupplement() {
    $count = count($this->pq->supplements);
    $this->pq->addSupplement("file.pdf", "appendix");
    $this->assertEqual($count + 1, count($this->pq->supplements));
    $this->assertEqual("file.pdf", $this->pq->supplements[$count]->filename);
    $this->assertEqual("appendix", $this->pq->supplements[$count]->description);
    $this->assertPattern("|<DISS_attachment>\s*<DISS_file_name>file.pdf</DISS_file_name>\s*<DISS_file_descr>appendix</DISS_file_descr>\s*</DISS_attachment>|", $this->pq->saveXML());
  }

  function testAddCommittee() {
    $namexml = '<mods:name  xmlns:mods="http://www.loc.gov/mods/v3"  type="personal">
    <mods:namePart type="given">Joe G.</mods:namePart>
    <mods:namePart type="family">Schmoe</mods:namePart>
    <mods:affiliation>School of Hard Knocks</mods:affiliation>
  </mods:name>';
    $dom = new DOMDocument();
    $dom->loadXML($namexml);
    $xpath = new DOMXpath($dom);
    $name = new mods_name($dom, $xpath);

    $count = count($this->pq->description->committee);
    $this->pq->description->addCommitteeMember($name);

    $this->assertEqual($count + 1, count($this->pq->description->committee));
    $this->assertEqual("Joe", $this->pq->description->committee[$count]->first);
    // middle name should be split out from given name
    $this->assertEqual("G.", $this->pq->description->committee[$count]->middle);	
    $this->assertEqual("Schmoe", $this->pq->description->committee[$count]->last);
    // affiliation should be set if there is one
    $this->assertEqual("School of Hard Knocks", $this->pq->description->committee[$count]->affiliation);
  }

  public function testAddCategory() {
    $namexml = '<mods:subject  xmlns:mods="http://www.loc.gov/mods/v3" ID="0545">
    <mods:topic>Unit Testing</mods:topic>
  </mods:subject>';
    $dom = new DOMDocument();
    $dom->loadXML($namexml);
    $xpath = new DOMXpath($dom);
    $subject = new mods_subject($dom, $xpath);

    $count = count($this->pq->description->categories);
    $this->pq->description->addCategory($subject);

    //    print "<pre>" . htmlentities($this->pq->saveXML()) . "</pre>";
    $this->assertEqual($count + 1, count($this->pq->description->categories));
    $this->assertEqual("0545", $this->pq->description->categories[$count]->code);
    $this->assertEqual("Unit Testing", $this->pq->description->categories[$count]->text);
  }

  public function testPhoneParsing() {
    $phonexml = '<DISS_phone_fax type="P">
	  <DISS_cntry_cd/><DISS_area_code/><DISS_phone_num/><DISS_phone_ext/>
	</DISS_phone_fax>';
    $dom = new DOMDocument();
    $dom->loadXML($phonexml);
    $xpath = new DOMXpath($dom);
    $phone = new DISS_phone($dom, $xpath);


    $phone->set("404-123-4567");
    $this->assertEqual("404", $phone->area_code);
    $this->assertEqual("123-4567", $phone->number);
    // leading space
    $phone->set(" 404-123-4567");
    $this->assertEqual("404", $phone->area_code);
    $this->assertEqual("123-4567", $phone->number);

    $phone->set("(981) 565-4267 x293");
    $this->assertEqual("981", $phone->area_code);
    $this->assertEqual("565-4267", $phone->number);
    $this->assertEqual("293", $phone->extension);

    $phone->set("+61 (0) 20 1234 5678");	// international # from website where I got the initial regexp
    // no idea if this is actually being parsed correctly
    $this->assertEqual("+61 (0) 20", $phone->country_code);
    $this->assertEqual("1234-5678", $phone->number);


    // can only definitely parse US numbers for now
    // here are a couple of international ones that don't work
    
    $this->expectError("Cannot parse phone number 86-21-50902078");
    // Chinese phone number from an actual student
    $phone->set("86-21-50902078");
    $this->assertEqual("86-21-50902078", $phone->number);

    // Korean phone # from an actual student
    //$phone->set("82-10-7227-40904");
  }


  public function testValidation() {
    // test validation error handling on blank, invalid record
    $this->assertFalse($this->pq->isValid());
    $this->assertEqual(6, count($this->pq->dtdValidationErrors()));
    $this->assertEqual(9, count($this->pq->schemaValidationErrors())); 
  }
 
}

runtest(new TestPQSubmission());
?>