<?php
require_once("../bootstrap.php");
require_once('models/etd.php');
require_once('models/esdPerson.php');
require_once("fixtures/esd_data.php");

class TestEtd extends UnitTestCase {
    private $etd;
    private $honors_etd;
    private $person;

    // fedoraConnection
    private $fedora;

    private $etdpid;
    private $gradpid;
    private $non_etdpid;

    private $school_cfg;
    
    function __construct() {
      $this->fedora = Zend_Registry::get("fedora");
      $this->fedora_cfg = Zend_Registry::get('fedora-config');

      $this->school_cfg = Zend_Registry::get("schools-config");
      
      // get test pids for fedora objects
      list($this->etdpid, $this->gradpid,
     $this->non_etdpid) = $this->fedora->getNextPid($this->fedora_cfg->pidspace, 3);
    }

    
  function setUp() {
    $fname = '../fixtures/etd1.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $this->etd = new etd($dom);
    $this->etd->setSchoolConfig($this->school_cfg->graduate_school);

    $this->honors_etd = new etd($dom);
    $this->honors_etd->setSchoolConfig($this->school_cfg->emory_college);

    // attach etd files for testing
    $fname = '../fixtures/etdfile.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $etdfile = new etd_file($dom);
    $etdfile->policy->addRule("view");
    $this->etd->pdfs[] = $etdfile;
    // separate copy - mock original file
    $dom2 = new DOMDocument();
    $dom2->load($fname);
    $etdfile2 = new etd_file($dom2);
    $etdfile2->pid = "test:etdfile2";
    $this->etd->originals[] = $etdfile2;
    // separate copy - mock original
    $dom3 = new DOMDocument();
    $dom3->load($fname);
    $etdfile = new etd_file($dom3);
    $etdfile->policy->addRule("view");
    $this->etd->addSupplement($etdfile);

    $this->etd->policy->addRule("view");
    $this->etd->policy->addRule("draft");

    $person = new esdPerson();
    $this->person = $person->getTestPerson();

    $this->data = new esd_test_data();
    $this->data->loadAll();
  }
  
  function tearDown() {
    $this->data->cleanUp();
  }
  
  function testBasicProperties() {
    // test that foxml properties are accessible
    $this->assertIsA($this->etd, "etd");
    $this->assertIsA($this->etd->dc, "dublin_core");
    $this->assertIsA($this->etd->rels_ext, "rels_ext");
    $this->assertIsA($this->etd->mods, "etd_mods");
    $this->assertIsA($this->etd->html, "etd_html");
    $this->assertIsA($this->etd->premis, "premis");
    $this->assertIsA($this->etd->policy, "XacmlPolicy");
    
    $this->assertEqual("test:etd1", $this->etd->pid);
    $this->assertEqual("Why I Like Cheese", $this->etd->label);
    // FIXME: should work for multiple cmodels
    $this->assertEqual("ETD", $this->etd->contentModelName());
    $this->assertEqual("mmouse", $this->etd->owner);
  }

  function testSpecialProperties() {
    /* special properties that set multiple values
     formatting is preserved in html & removed for dc/mods */

    $this->etd->title = "<i>Cheesy</i>ness";
    $this->assertEqual("<i>Cheesy</i>ness", $this->etd->html->title);
    $this->assertEqual("Cheesyness", $this->etd->mods->title);
    $this->assertEqual("Cheesyness", $this->etd->dc->title);

    $this->etd->abstract = "<b>cheese</b> explained";
    $this->assertEqual("<b>cheese</b> explained", $this->etd->html->abstract);
    $this->assertEqual("cheese explained", $this->etd->mods->abstract);
    $this->assertEqual("cheese explained", $this->etd->dc->description);

    $this->etd->contents = "<p>chapter 1 <br/> chapter 2</p>";
    $this->assertPattern("|<p>chapter 1\s*<br/>\s*chapter 2</p>|", $this->etd->html->contents);
    $this->assertEqual("chapter 1 -- chapter 2", $this->etd->mods->tableOfContents);

    // xacml
    $this->etd->pid = "newpid:1";
    $this->assertEqual("newpid:1", $this->etd->policy->pid);
    $this->assertEqual("newpid-1", $this->etd->policy->policyid);

    
    $this->etd->owner = "dduck";
    $this->assertEqual("dduck", $this->etd->owner);
    $this->assertEqual("dduck", $this->etd->policy->draft->condition->user);

    // department - mods & view policy

    // test that department is also set on etdFile view policy
    $this->etd->department = "Chemistry";
    $this->assertEqual("Chemistry", $this->etd->mods->department);
    $this->assertEqual("Chemistry", $this->etd->policy->view->condition->department);
    // check that department was also set on etdfile
    $this->assertEqual("Chemistry", $this->etd->supplements[0]->policy->view->condition->department);
  }

  function testSpecialProperties_embargoed() {
    /* special properties - abstract & ToC may be restricted--
      set in html but plaintext version not copied to mods */


    $this->etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_ABSTRACT);
    // set embargo end to tomorrow - unexpired
    $this->etd->mods->embargo_end = date("Y-m-d", time() + (24*60*60));

    $this->etd->abstract = "<b>cheese</b> explained";
    $this->assertEqual("<b>cheese</b> explained", $this->etd->html->abstract,
           "html abstract set normally when embargo level is abstract");
    $this->assertEqual("", $this->etd->mods->abstract,
           "mods abstract is blank after setting etd abstract when embargo level = abstract");
    $this->assertEqual("", $this->etd->dc->description,
           "dc description is blank after setting etd abstract when embargo level = abstract");
    $this->assertPattern("|<mods:abstract></mods:abstract>|", $this->etd->mods->saveXML(),
       "mods xml has an empty abstract");
    $this->assertPattern("|<dc:description></dc:description>|", $this->etd->dc->saveXML(),
       "DC xml has an empty description");
    $this->etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_TOC);
    $this->etd->contents = "<p>chapter 1 <br/> chapter 2</p>";
    $this->assertPattern("|<p>chapter 1\s*<br/>\s*chapter 2</p>|",
       $this->etd->html->contents,
       "html ToC set normally when embargo level is TOC");
    $this->assertEqual("", $this->etd->mods->tableOfContents,
           "mods ToC is blank after setting etd contents when embargo level is TOC");
    $this->assertPattern("|<mods:tableOfContents></mods:tableOfContents>|",
       $this->etd->mods->saveXML(),
       "mods xml has an empty tableOfContents");


    // set embargo end to yesterday - expired -- contents should be set in mods/dc
    $this->etd->mods->embargo_end = date("Y-m-d", time() - (24*60*60));
    $this->etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_ABSTRACT);

    $this->etd->abstract = "<b>cheese</b> explained";
    $this->assertEqual("cheese explained", $this->etd->mods->abstract,
           "mods abstract is set normally when embargo level = abstract but embargo has expired");
    $this->assertEqual("cheese explained", $this->etd->dc->description,
           "dc description is set normally when embargo level = abstract but embargo has expired");

    $this->etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_TOC);
    $this->etd->contents = "<p>chapter 1 <br/> chapter 2</p>";
    $this->assertEqual("chapter 1 -- chapter 2", $this->etd->mods->tableOfContents,
           "mods ToC is set normally when embargo level is TOC but embargo has expired");

  }

  function testGetUserRole() {
    // netid matches the author rel in rels-ext 
    $this->person->netid = "mmouse";
    $this->assertEqual("author", $this->etd->getUserRole($this->person));
    // netid matches one of the committee rels 
    $this->person->netid = "dduck";
    $this->assertEqual("committee", $this->etd->getUserRole($this->person));

    // department matches author's department & user is staff
    $this->person->netid = "someuser";
    $this->person->grad_coord = "Disney";
    $this->assertEqual("program coordinator", $this->etd->getUserRole($this->person));

    // grad coordinator field not set
    $this->person->role = "student";
    $this->person->grad_coord = null;
    $this->assertNotEqual("program coordinator", $this->etd->getUserRole($this->person));
    
    // nothing matches - user's base role should be returned
    $this->person->department = "Warner Brothers";
    $this->person->role = "default role";
    $this->assertEqual("default role", $this->etd->getUserRole($this->person));

    // school-specific admin
    $this->person->role = "grad admin";
    $this->assertEqual("admin", $this->etd->getUserRole($this->person),
           "grad admin has admin role on grad etd");
    $this->person->role = "honors admin";
    $this->assertEqual("honors admin", $this->etd->getUserRole($this->person),
           "honors admin is honors admin on grad etd");
    $this->person->role = "rollins admin";
    $this->assertEqual("rollins admin", $this->etd->getUserRole($this->person),
           "rollins admin has rollins admin role on grad etd");

    $this->etd->setSchoolConfig($this->school_cfg->emory_college);
    $this->person->role = "grad admin";
    $this->assertEqual("grad admin", $this->etd->getUserRole($this->person),
           "grad admin is grad admin on honors etd");
    $this->person->role = "honors admin";
    $this->assertEqual("admin", $this->etd->getUserRole($this->person),
           "honors admin is admin on honors etd");
    $this->person->role = "rollins admin";
    $this->assertEqual("rollins admin", $this->etd->getUserRole($this->person),
           "rollins admin has rollins admin role on honors etd");

    $this->etd->setSchoolConfig($this->school_cfg->rollins);
    $this->person->role = "grad admin";
    $this->assertEqual("grad admin", $this->etd->getUserRole($this->person),
           "grad admin is grad admin on rollins etd");
    $this->person->role = "honors admin";
    $this->assertEqual("honors admin", $this->etd->getUserRole($this->person),
           "honors admin is honors admin on rollins etd");
    $this->person->role = "rollins admin";
    $this->assertEqual("admin", $this->etd->getUserRole($this->person),
           "rollins admin has admin role on rollins etd");

    
  }

  // run through the various statuses in order, check everything is set correctly
  
  function testSetStatus_draft() {
    // draft - draft rule is added, published removed (because object had published status before)
    $this->etd->setStatus("draft");
    $this->assertEqual("draft", $this->etd->status());
    $this->assertIsA($this->etd->policy->draft, "PolicyRule");
    $this->assertEqual($this->etd->policy->draft->condition->user, "mmouse"); // owner from etd
    $this->assertFalse(isset($this->etd->policy->published));
    // draft rule should also be added to related etdfile objects
    $this->assertTrue(isset($this->etd->pdfs[0]->policy->draft),
        "draft policy should be present on pdf etdFile");
    $this->assertIsA($this->etd->pdfs[0]->policy->draft, "PolicyRule",
        "pdf etdFile draft policy should be type 'PolicyRule'");
    $this->assertFalse(isset($this->etd->pdfs[0]->policy->published));
    $this->assertIsA($this->etd->originals[0]->policy->draft, "PolicyRule",
        "original etdFile draft policy should be type 'PolicyRule'");
    $this->assertFalse(isset($this->etd->originals[0]->policy->published));

  }

  function testSetStatus_submitted() {
    // submitted - draft rule should be removed (if present), no new rules
    $this->etd->setStatus("draft");
    $this->etd->setStatus("submitted");
    $this->assertEqual("submitted", $this->etd->status());
    $this->assertFalse(isset($this->etd->policy->draft));
    $this->assertFalse(isset($this->etd->pdfs[0]->policy->draft));
    $this->assertFalse(isset($this->etd->originals[0]->policy->draft));
  }

  function testSetStatus_reviewed() {
    // reviewed - no rules change
    $etd_rulecount = count($this->etd->policy->rules);
    $this->etd->setStatus("reviewed");
    $this->assertEqual("reviewed", $this->etd->status());
    $this->assertEqual($etd_rulecount, count($this->etd->policy->rules));
  }

  function testSetStatus_approved() {
    // approved - no rules change
    $etd_rulecount = count($this->etd->policy->rules);
    $this->etd->setStatus("approved");
    $this->assertEqual("approved", $this->etd->status());
    $this->assertEqual($etd_rulecount, count($this->etd->policy->rules));
  }

  function testSetStatus_published_noembargo() {
    // published - publish rule is added
    $this->etd->setStatus("published");
    $this->assertEqual("published", $this->etd->status());
    $this->assertIsA($this->etd->policy->published, "PolicyRule");
    $this->assertTrue(isset($this->etd->policy->published));
    // etd object publish rule logic now includes a condition
    $this->assertTrue(isset($this->etd->policy->published->condition),
           "etd publish policy has condition");
    $pub_condition = $this->etd->policy->published->condition;
    $this->assertEqual(date("Y-m-d"), $pub_condition->embargo_end, "embargo end date on published rule condition should default to today");
    // no embargo - html disseminator methods should not be restricted
    $this->assertTrue($pub_condition->methods->includes("title"));
    $this->assertTrue($pub_condition->methods->includes("abstract"));
    $this->assertTrue($pub_condition->methods->includes("tableofcontents"));    
    
    // setting to published should add OAI id
    $this->assertTrue(isset($this->etd->rels_ext->oaiID));
    $this->assertEqual(FedoraConnection::STATE_ACTIVE, $this->etd->next_object_state);

    // etdfile published rule should also be added to related etdfile objects 
    $this->assertTrue(isset($this->etd->pdfs[0]->policy->published),
        "pdf etdFile published policy should be present"  );
    $this->assertIsA($this->etd->pdfs[0]->policy->published, "PolicyRule",
        "pdf etdFile published policy should be type 'PolicyRule'");
    $this->assertTrue(isset($this->etd->pdfs[0]->policy->published->condition),
        "pdf etdFile published policy should have condition");
    // embargo end should be set to today by default
    $this->assertTrue(isset($this->etd->pdfs[0]->policy->published->condition->embargo_end),
        "pdf etdFile published policy condition should have an embargo end");
    $this->assertEqual($this->etd->pdfs[0]->policy->published->condition->embargo_end, date("Y-m-d"),
        "pdf etdFile published policy embargo end should be '" . date("Y-m-d") . "', got '" .
        $this->etd->pdfs[0]->policy->published->condition->embargo_end . "'");
    // published rule should NOT be added to original
    $this->assertFalse(isset($this->etd->originals[0]->policy->published));
  }

  function testSetStatus_published_embargo_files() {
    // publish with embargo
    $embargo_end =  date("Y-m-d", time() + (365*24*60*60));   // today + 1 year
    
    $this->etd->mods->embargo_end = $embargo_end;
    $this->etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_FILES);
    $this->etd->setStatus("published");
    $pub_condition = $this->etd->policy->published->condition;

    $this->assertEqual($embargo_end, $this->etd->policy->published->condition->embargo_end, "embargo end date on published rule condition should match embargo end date in MODS");
    $this->assertEqual($this->etd->pdfs[0]->policy->published->condition->embargo_end,
           $embargo_end,
        "pdf etdFile published policy embargo end should be '$embargo_end', got '" .
        $this->etd->pdfs[0]->policy->published->condition->embargo_end . "'");

    // html disseminator methods should not be restricted
    $this->assertTrue($pub_condition->methods->includes("title"),
          "title access allowed when embargo level is FILES");
    $this->assertTrue($pub_condition->methods->includes("abstract"),
          "abstract access allowed when embargo level is FILES");
    $this->assertTrue($pub_condition->methods->includes("tableofcontents"),
          "ToC access allowed when embargo level is FILES");  
    $this->assertFalse($pub_condition->embargoed_methods->includes("title"),
           "title access not restricted when embargo level is FILES");
    $this->assertFalse($pub_condition->embargoed_methods->includes("abstract"),
           "abstract access not restricted when embargo level is FILES");
    $this->assertFalse($pub_condition->embargoed_methods->includes("tableofcontents"),
           "ToC access not restricted when embargo level is FILES");    
  }
  
  function testSetStatus_published_embargo_toc() {
    // publish with embargo
    $embargo_end =  date("Y-m-d", time() + (365*24*60*60));   // today + 1 year
    $this->etd->mods->embargo_end = $embargo_end;
    $this->etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_TOC);
    // mods ToC may have been set *before* embargo level set - ensure it gets cleared
    $this->etd->mods->tableOfContents = "ch.1 - ch.2 - appendix";
    $this->etd->setStatus("published");
    $pub_condition = $this->etd->policy->published->condition;

    $this->assertEqual($embargo_end, $pub_condition->embargo_end,
       "embargo end date on published rule condition should match embargo end date in MODS");
    $this->assertEqual($embargo_end, $this->etd->pdfs[0]->policy->published->condition->embargo_end, 
        "pdf etdFile published policy embargo end should be '$embargo_end', got '" .
        $this->etd->pdfs[0]->policy->published->condition->embargo_end . "'");

    // title & abstract html disseminator methods should not be restricted
    $this->assertTrue($pub_condition->methods->includes("title"),
          "title access allowed when embargo level is TOC");
    $this->assertTrue($pub_condition->methods->includes("abstract"),
          "abstract access allowed when embargo level is TOC");  
    $this->assertFalse($pub_condition->embargoed_methods->includes("title"),
           "title access not restricted when embargo level is TOC");
    $this->assertFalse($pub_condition->embargoed_methods->includes("abstract"),
           "abstract access not restricted when embargo level is TOC");  
    // ToC html disseminator methods *should* be restricted
    $this->assertFalse($pub_condition->methods->includes("tableofcontents"),
           "toc access not allowed when embargo level is TOC");
    $this->assertTrue($pub_condition->embargoed_methods->includes("tableofcontents"),
          "toc access restricted when embargo level is TOC");

    $this->assertEqual("", $this->etd->mods->tableOfContents,
           "TOC in MODS blanked out when embargo level is TOC");
  }
  
  function testSetStatus_published_embargo_abstract() {
    $embargo_end =  date("Y-m-d", time() + (365*24*60*60));   // today + 1 year
    $this->etd->mods->embargo_end = $embargo_end;
    $this->etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_ABSTRACT);
    $this->etd->mods->tableOfContents = "ch.1 - ch.2 - appendix";
    $this->etd->setStatus("published");
    $pub_condition = $this->etd->policy->published->condition;
    // title html disseminator method should not be restricted
    $this->assertTrue($pub_condition->methods->includes("title"),
          "title access allowed when embargo level is ABSTRACT");
    // abstract & ToC html disseminator methods *should* be restricted
    $this->assertFalse($pub_condition->methods->includes("abstract"),
           "abstract access not allowed when embargo level is ABSTRACT");
    $this->assertTrue($pub_condition->embargoed_methods->includes("abstract"),
          "abstract access restricted when embargo level is ABSTRACT");  
    $this->assertFalse($pub_condition->methods->includes("tableofcontents"),
           "ToC access not allowed when embargo level is ABSTRACT");
    $this->assertTrue($pub_condition->embargoed_methods->includes("tableofcontents"),
          "ToC access restricted when embargo level is ABSTRACT");

    $this->assertEqual("", $this->etd->mods->tableOfContents,
           "ToC in mods blanked out when embargo level is ABSTRACT");
    $this->assertEqual("", $this->etd->mods->abstract,
           "abstract in mods blanked out when embargo level is ABSTRACT");
  }    

  function testSetStatus_published_embargo_abstract_embargoexpired() {
    $embargo_end = date("Y-m-d", strtotime("-1 year", time()));
    $this->etd->mods->embargo_end = $embargo_end;
    $this->etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_ABSTRACT);
    $this->etd->mods->tableOfContents = "ch.1 - ch.2 - appendix";
    $this->etd->setStatus("published");
    $pub_condition = $this->etd->policy->published->condition;
 
    $this->assertNotEqual("", $this->etd->mods->tableOfContents,
           "ToC in mods NOT blanked out on setStatus published when embargo level is ABSTRACT but embargo has expired");
    $this->assertNotEqual("", $this->etd->mods->abstract,
           "abstract in mods NOT blanked out on setStatus published when embargo level is ABSTRACT but embargo has expired");
  }    

  function testSetStatus_inactive() {
    $this->etd->setStatus("inactive");
    $this->assertEqual(FedoraConnection::STATE_INACTIVE, $this->etd->next_object_state);

  }

  function testInitbyTemplate() {
    $etd = new etd();
    $this->assertIsA($etd, "etd");
    $this->assertIsA($etd->dc, "dublin_core");
    $this->assertIsA($etd->rels_ext, "rels_ext");
    $this->assertIsA($etd->mods, "etd_mods");
    $this->assertIsA($etd->html, "etd_html");
    $this->assertIsA($etd->premis, "premis");
    $this->assertIsA($etd->policy, "XacmlPolicy");

    $this->assertEqual("ETD", $this->etd->contentModelName());
    // check for error found in ticket:150
    $this->assertEqual("draft", $etd->status());

    // all etds should be member of either the graduate_school, emory_college or candler and not a member of the ETD master collection. 
    $this->assertFalse($etd->rels_ext->isMemberOfCollections->includes($etd->rels_ext->pidToResource($this->school_cfg->all_schools->fedora_collection)),
        "template etd dos not have a  isMemberOfCollection relation to etd collection");

    $honors_etd = new etd($this->school_cfg->emory_college);
    // researchfields should not be present in mods if optional
    $this->assertFalse($honors_etd->isRequired("researchfields"));
    $this->assertEqual(0, count($honors_etd->mods->researchfields),
           "no researchfields present in honors etd");
    $this->assertNoPattern('|<mods:subject ID="" authority="proquestresearchfield">|',
         $honors_etd->mods->saveXML(), "researchfields not present in MODS");
  }


  function testInitByTemplate_withSchoolConfig() {
    $etd = new etd($this->school_cfg->graduate_school);
    $this->assertTrue($etd->rels_ext->isMemberOfCollections->includes($etd->rels_ext->pidToResource($this->school_cfg->graduate_school->fedora_collection)), "after init with school config for graduate school, new etd is member of grad school fedora collection object");
    $this->assertEqual($this->school_cfg->graduate_school->label, $etd->admin_agent,
           "new etd admin agent is set based on configured school label for grad");
          
    $honors_etd = new etd($this->school_cfg->emory_college);
    $this->assertTrue($honors_etd->rels_ext->isMemberOfCollections->includes($honors_etd->rels_ext->pidToResource($this->school_cfg->emory_college->fedora_collection)), "after init with school config for graduate school, new etd is member of college honors fedora collection object");
    $this->assertEqual($this->school_cfg->emory_college->label, $honors_etd->admin_agent,
           "new etd admin agent is set based on configured school label for college");
          
    $etd = new etd($this->school_cfg->candler);
    $this->assertTrue($etd->rels_ext->isMemberOfCollections->includes($etd->rels_ext->pidToResource($this->school_cfg->candler->fedora_collection)), "after init with school config for graduate school, new etd is member of candler fedora collection object");
    $this->assertEqual($this->school_cfg->candler->label, $etd->admin_agent,
           "new etd admin agent is set based on configured school label for candler");
           
    $etd = new etd($this->school_cfg->rollins);
    $this->assertTrue($etd->rels_ext->isMemberOfCollections->includes($etd->rels_ext->pidToResource($this->school_cfg->rollins->fedora_collection)), "after init with school config for graduate school, new etd is member of rollins fedora collection object");
    $this->assertEqual($this->school_cfg->rollins->label, $etd->admin_agent,
           "new etd admin agent is set based on configured school label for rollins");           
    
  }

  function testInitByPid_withSchoolConfig() {
    $e = new etd($this->school_cfg->graduate_school);
    $e->pid = $this->gradpid;
    $e->title = "test grad etd";
    $e->mods->ark = "ark:/123/bcd";
    $e->ingest("test init with school config");

    // initialize from fedora
    $etd = new etd($this->gradpid);
    $required = $etd->requiredFields();
    $this->assertIsA($required, "Array");
    $config_count = count($this->school_cfg->graduate_school->submission_fields->required->toArray());
    $this->assertEqual($config_count, count($required),
           "number of required fields matches config - should be $config_count, got " .
           count($required));
    
    $this->fedora->purge($this->gradpid, "removing test etd");
  }

  function testRequiredFields() {
    $cfg = new Zend_Config(array("submission_fields" =>
         array("required" => array("title", "author", "program"))));
    $this->etd->setSchoolConfig($cfg);

    $required = $this->etd->requiredFields();
    $this->assertIsA($required, "Array", "requiredFields returns an array");
    $this->assertEqual(3, count($required),
           "requiredFields returns 3 items as in test config (count is " .
           count($required) . ")");
    $this->assertEqual("title", $required[0]);
    $this->assertEqual("program", $required[2]);
  }

  // NOTE: bad cmodel test removed because contend model check was removed
  // from constructor for efficiency/response time reasons
  
  function testAddCommittee() { // committee chairs and members
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);
    
    // attach an etd file to test that chair/committee are set on etdFile view policy
    //    $fname = '../fixtures/etdfile.xml';
    //    $dom = new DOMDocument();
    //    $dom->load($fname);
    //    $etdfile = new etd_file($dom);
    //    $etdfile->policy->addRule("view");
    //    $this->etd->addSupplement($etdfile);

    
    $this->etd->setCommittee(array("mthink"), "chair");
    // should be set in mods, rels-ext, and in view policy rule
    $this->assertEqual("mthink", $this->etd->mods->chair[0]->id);
    $this->assertEqual("Thinker", $this->etd->mods->chair[0]->last);
    $this->assertEqual("mthink", $this->etd->rels_ext->committee[0]);
    $this->assertTrue($this->etd->policy->view->condition->users->includes("mthink"));
    $this->assertTrue($this->etd->supplements[0]->policy->view->condition->users->includes("mthink"));

    $this->etd->setCommittee(array("engrbs", "bschola"));
    $this->assertEqual("engrbs", $this->etd->mods->committee[0]->id);
    $this->assertEqual("bschola", $this->etd->mods->committee[1]->id);
    $this->assertEqual("engrbs", $this->etd->rels_ext->committee[0]);
    $this->assertEqual("bschola", $this->etd->rels_ext->committee[1]);
    $this->assertTrue($this->etd->policy->view->condition->users->includes("engrbs"));
    $this->assertTrue($this->etd->policy->view->condition->users->includes("bschola"));
    $this->assertTrue($this->etd->supplements[0]->policy->view->condition->users->includes("engrbs"));
    $this->assertTrue($this->etd->supplements[0]->policy->view->condition->users->includes("bschola"));

    error_reporting($errlevel);     // restore prior error reporting
  }


  function testConfirmGraduation() {
    $this->etd->confirm_graduation();
    $this->assertEqual("Graduation Confirmed by ETD system",
           $this->etd->premis->event[count($this->etd->premis->event) - 1]->detail);
  }

  function testPublish() {
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);
    
    $pubdate = "2008-01-01";
    $this->etd->mods->embargo = "6 months"; // specify an embargo duration
    $this->etd->mods->chair[0]->id = "nobody";  // set ids for error messages
    $this->etd->mods->committee[0]->id = "nobodytoo";

    // official pub date, 'actual' pub date
    $this->etd->publish($pubdate, $pubdate);    // if not specified, date defaults to today

    $fname = '../fixtures/authorInfo.xml';
    $dom = new DOMDocument();
    $dom->loadXML(file_get_contents($fname));
    $authorinfo = new authorInfo($dom);
    $this->etd->related_objects['authorInfo'] = $authorinfo;

    $this->assertEqual($pubdate, $this->etd->mods->originInfo->issued);
    $this->assertEqual("2008-07-01", $this->etd->mods->embargo_end);
    $this->assertEqual("published", $this->etd->status());
    $this->assertEqual("Published by ETD system",
           $this->etd->premis->event[count($this->etd->premis->event) - 1]->detail);
    // note: publication notification no longer part of publish() function


    // simulate bad data / incomplete record to test exception
    $this->etd->mods->embargo = ""; // empty duration - results in zero-time unix
    $this->expectException(new XmlObjectException("Calculated embargo date does not look correct (timestamp:, 1969-12-31)"));
    $this->etd->publish($pubdate, $pubdate);

    error_reporting($errlevel);     // restore prior error reporting
  }


  // test adding files to an etd
  function testAddFile() {
    $errlevel = error_reporting(E_ALL ^ E_NOTICE);
    
    $fname = '../fixtures/etdfile.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $etdfile = new etd_file($dom);
    $etdfile->policy->addRule("view");

    $this->etd->mods->chair[0]->id = "jsmith";
    $this->etd->mods->committee[0]->id = "kjones";
    $this->etd->addSupplement($etdfile);

    $this->assertTrue(isset($this->etd->supplements));
    $this->assertEqual(2, count($this->etd->supplements));
    $this->assertTrue(isset($this->etd->rels_ext->supplement));
    $this->assertEqual(2, count($this->etd->rels_ext->supplement));
    // relation to file was added to etd
    $this->assertPattern("|<rel:hasSupplement rdf:resource=\"info:fedora/" . $etdfile->pid . "\"/>|",
       $this->etd->rels_ext->saveXML());
    // NOTE: relation to etd is not currently added to file object by this function (should it be?)

    // any values already added to etd that are relevant to xacml policy should be set on newly added file
    $this->assertTrue($this->etd->supplements[1]->policy->view->condition->users->includes("jsmith"));
    $this->assertTrue($this->etd->supplements[1]->policy->view->condition->users->includes("kjones"));
    $this->assertEqual("Disney", $this->etd->supplements[1]->policy->view->condition->department);

    error_reporting($errlevel);     // restore prior error reporting
  }


  
  function testUpdateDC() {
    $fname = '../fixtures/etd2.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $etd = new etd($dom);

    // add non-emory committee member to test
    $etd->mods->addCommittee("Manhunter", "Martian", "nonemory_committee", "Mars Polytechnic");
    // simulate embargo ending in one year
    $etd->mods->embargo_end =  date("Y-m-d", strtotime("+1 year", time()));;
    
    // test that blank subject does not result in empty dc:subject
    $etd->mods->addKeyword("");
    $etd->updateDC();

    $this->assertEqual($etd->mods->title, $etd->dc->title);
    $this->assertEqual($etd->mods->abstract, $etd->dc->description);
    $this->assertEqual($etd->mods->identifier, $etd->dc->ark);  
    $this->assertEqual($etd->mods->author->full, $etd->dc->creator);
    $this->assertEqual($etd->mods->chair[0]->full, $etd->dc->contributor);
    $this->assertEqual($etd->mods->committee[0]->full, $etd->dc->contributors[1]);
    $this->assertEqual("Manhunter, Martian (Mars Polytechnic)", $etd->dc->contributors[2]);
    $this->assertEqual($etd->mods->language->text, $etd->dc->language);
    $this->assertEqual($etd->mods->researchfields[0]->topic, $etd->dc->subjects[0]);
    $this->assertEqual($etd->mods->keywords[0]->topic, $etd->dc->subjects[1]);
    $this->assertNoPattern("|<dc:subject></dc:subject>|", $etd->dc->saveXML());
    $this->assertEqual($etd->mods->date, $etd->dc->date);
    $this->assertEqual($etd->mods->genre, $etd->dc->type);
    $this->assertEqual("text", $etd->dc->types[1]);
    $this->assertEqual($etd->mods->degree_grantor->namePart, $etd->dc->publisher);
    $this->assertEqual($etd->mods->physicalDescription->mimetype, $etd->dc->format);
    $this->assertPattern("/^" . $etd->mods->rights . "/", $etd->dc->rights,
       "DC rights begins with mods rights statement");
    $this->assertPattern("/Access has been restricted until " . $etd->mods->embargo_end . "/",
       $etd->dc->rights,
       "DC rights includes embargo end date (got " . $etd->dc->rights . ")");

    // simulate an expired embargo
    $etd->mods->embargo_end =  date("Y-m-d", strtotime("-1 year", time()));;
    $etd->updateDC();
    $this->assertNoPattern("/Access has been restricted until " . $etd->mods->embargo_end . "/",
       $etd->dc->rights,
       "DC rights does not include access restricted text for an expired embargo (got "
       . $etd->dc->rights . ")");
  

    // need to test setting arks for related objects (pdf/supplement)


    // losing escaped & from mods to dc
    $etd->mods->abstract = "this &amp; that";
    $etd->updateDC();
    $this->assertEqual("this & that", $etd->dc->description);
    $this->assertPattern("|<dc:description>this &amp; that</dc:description>|",
       $etd->dc->saveXML());
  }


  function testEmbargoExpirationNotice() {
    $event_count = count($this->etd->premis->event);
    $this->etd->embargo_expiration_notice();
    // admin note in mods should be set
    $this->assertTrue(isset($this->etd->mods->embargo_notice));
    $this->assertEqual(date("Y-m-d"), $this->etd->mods->embargo_notice);
    // premis event for record history
    $this->assertEqual($event_count + 1, count($this->etd->premis->event), "additional event added");
    $this->assertEqual("Embargo Expiration 60-Day Notification sent by ETD system",
           $this->etd->premis->event[$event_count]->detail);  // text of last event
  }

  function testUndoEmbargoExpirationNotice() {
    // simulate embargo expiration notice so it can be undone
    $this->etd->embargo_expiration_notice();
  
    $this->etd->undoEmbargoExpirationNotice();
    $this->assertFalse(isset($this->etd->mods->embargo_notice));
    $this->assertNotEqual("Embargo Expiration 60-Day Notification sent by ETD system",
        $this->etd->premis->event[count($this->etd->premis->event) - 1]->detail);
  }


  function testTitleAbstractToc() {
    // title was not getting set in MODS
    $this->etd->title = '<p>Enantiomeric <span style="font-family: Symbol">p</span>-Complexes</p>';
    $this->assertPattern('|<p>Enantiomeric <span.*>p</span>-Complexes</p>|', $this->etd->html->title);
    $this->assertPattern("/^Enantiomeric p-Complexes\s*/", $this->etd->mods->title);
    $this->assertPattern("/^Enantiomeric p-Complexes\s*/", $this->etd->dc->title);

    $this->etd->abstract = '<p>Pure TpMo(CO)<sub>2</sub>(<span style="font-family: Symbol;">h</span><sup>3</sup>-pyranyl)...</p>';
    $this->assertPattern('|<p>Pure\s+TpMo\(CO\)<sub>2</sub>\(<span.*>h</span><sup>3</sup>-pyranyl\)...</p>|', $this->etd->html->abstract);
    $this->assertPattern("/Pure\s+TpMo\(CO\)2\(h3-pyranyl\)...\s*/", $this->etd->mods->abstract);
    $this->assertPattern("/Pure\s+TpMo\(CO\)2\(h3-pyranyl\)...\s*/", $this->etd->dc->description);


    $this->etd->contents = '<p style="margin: 0cm 0cm 0pt 21pt;">Introduction</p>
    <p style="margin: 0cm 0cm 0pt 21pt;">Zirconium Complexes</p>';
    $this->assertPattern('|<p>Introduction</p>\s*<p>Zirconium Complexes</p>|', $this->etd->html->contents);
    $this->assertEqual("Introduction -- Zirconium Complexes", $this->etd->mods->tableOfContents);

    
  }

  function testIsRequired() {
    // etd initliaized with grad school config; check required fields

    $this->assertTrue($this->etd->isRequired("title"), "title is required");
    $this->assertTrue($this->etd->isRequired("author"), "author is required");
    $this->assertTrue($this->etd->isRequired("program"), "program is required");
    $this->assertTrue($this->etd->isRequired("chair"), "chair is required");
    $this->assertTrue($this->etd->isRequired("committee members"),
          "committee members are required");
    $this->assertTrue($this->etd->isRequired("researchfields"), "researchfields are required");
    $this->assertTrue($this->etd->isRequired("keywords"), "keywords are required");
    $this->assertTrue($this->etd->isRequired("degree"), "degree is required");
    $this->assertTrue($this->etd->isRequired("language"), "language is required");
    $this->assertTrue($this->etd->isRequired("abstract"), "abstract is required");
    $this->assertTrue($this->etd->isRequired("table of contents"), "contents is required");
    $this->assertTrue($this->etd->isRequired("embargo request"), "embargo request is required");
    $this->assertTrue($this->etd->isRequired("submission agreement"),
          "submission agreement is required");
    $this->assertTrue($this->etd->isRequired("send to ProQuest"), "send to PQ is required");
    $this->assertTrue($this->etd->isRequired("copyright"), "copyright is required");

    $this->assertTrue($this->etd->isRequired("email"), "email is required");
    $this->assertTrue($this->etd->isRequired("permanent email"),
          "permanent email is required");
    $this->assertTrue($this->etd->isRequired("permanent address"),
           "permanent address is required");

    
    $this->assertFalse($this->honors_etd->isRequired("researchfields"),
           "research fields are not required for honrs");
    $this->assertFalse($this->honors_etd->isRequired("send to ProQuest"),
           "send to PQ info not required for honors");
    $this->assertFalse($this->honors_etd->isRequired("copyright"),
           "copyright info not required for honors");
    $this->assertFalse($this->honors_etd->isRequired("permanent address"),
           "permanent address not required for honors");

    $this->assertTrue(in_array("researchfields", $this->honors_etd->optionalFields()),
          "researchfields is optional for honors");
  }

  function testGetResourceId() {
    // resource id used for all ACL checks
    $this->assertEqual("published etd", $this->etd->getResourceId());
    $this->etd->rels_ext->status = "draft";
    $this->assertEqual("draft etd", $this->etd->getResourceId());
  }


  function testIsHonors() {
    $this->assertFalse($this->etd->isHonors(), "isHonors is false for grad etd");
    $this->assertTrue($this->honors_etd->isHonors(), "isHonors is true for honors etd");
  }

  function testSchoolId() {
    $this->assertEqual("grad", $this->etd->schoolId(), "schoolId for grad etd should be 'grad', got '"
           . $this->etd->schoolId() . "'");
    $this->assertEqual("honors", $this->honors_etd->schoolId(), "schoolId for honors etd should be 'honors', got '"
           . $this->honors_etd->schoolId() . "'");

    $candler_etd = new etd($this->school_cfg->candler);
    $this->assertEqual("candler", $candler_etd->schoolId(),
           "schoolId for CST etd should be 'candler', got '"
           . $candler_etd->schoolId() . "'");
           
    $rollins_etd = new etd($this->school_cfg->rollins);
    $this->assertEqual("rollins", $rollins_etd->schoolId(),
           "schoolId for CST etd should be 'rollins', got '"
           . $rollins_etd->schoolId() . "'");           
    
  }

  function testSetOAIidentifier() {
      $this->etd->mods->ark = "ark:/123/bcd";
      $this->etd->setOAIidentifier();
      $this->assertTrue(isset($this->etd->rels_ext->oaiID), "oai id is set in RELS-EXT");
      $this->assertEqual("oai:ark:/123/bcd", $this->etd->rels_ext->oaiID);

      // should get an error if ark has not been set in the mods
      $this->etd->mods->ark = "";
      $this->expectException(new Exception("Cannot set OAI identifier without ARK."));
      $this->etd->setOAIidentifier();
      
  }

  function testMagicMethods() {
      // ingest a minimal, published record to test fedora methods as object methods
      $etd = new etd($this->school_cfg->graduate_school);
      $etd->pid = $this->etdpid;
      $etd->title = "test etd";
      $etd->mods->ark = "ark:/123/bcd";
      $etd->setStatus("published");            
      $etd->ingest("test etd magic methods");

      $etd = new etd($this->etdpid);
      $marcxml = $etd->getMarcxml();
      $this->assertNotNull($marcxml, "getMarcxml() return response should not be empty");
      $this->assertPattern("|<marc:record|", $marcxml, "getMarcxml() result looks like marc xml");

      $etdms = $etd->getEtdms();
      $this->assertNotNull($etdms, "getEtdms() return response should not be empty");
      $this->assertPattern("|<thesis|", $etdms, "getEtdms() result looks like ETD-MS");

      $mods = $etd->getMods();
      $this->assertNotNull($mods, "getMods() return response should not be empty");
      $this->assertPattern("|<mods:mods|", $mods, "getMods () result looks like MODS");

      $this->fedora->purge($this->etdpid, "removing test etd");
  }


  function testIsEmbargoed() {
    $this->etd->mods->embargo_end = "";
    $this->assertFalse($this->etd->isEmbargoed(),
           "isEmbargoed returns false when no embargo end date is set");

    $this->etd->mods->embargo_end =  date("Y-m-d", strtotime("+1 year", time()));;
    $this->assertTrue($this->etd->isEmbargoed(),
          "isEmbargoed returns true for embargo end date one year in future");

    $this->etd->mods->embargo_end =  date("Y-m-d", strtotime("-1 year", time()));;
    $this->assertFalse($this->etd->isEmbargoed(),
          "isEmbargoed returns false for embargo end date one year in past");

    $this->etd->mods->embargo_end =  date("Y-m-d");;
    $this->assertFalse($this->etd->isEmbargoed(),
          "isEmbargoed returns false for embargo end date of today");
  }

  function testGetPreviousStatus() {
    // ingest a minimal, published record to test fedora methods as object methods
    $etd = new etd($this->school_cfg->emory_college);
    $etd->pid = $this->fedora->getNextPid($this->fedora_cfg->pidspace);
    $etd->title = "test etd";
    $etd->mods->ark = "ark:/123/bcd";
    $etd->rels_ext->addRelation("rel:author", "me");  
    $etd->setStatus("draft");            
    $pid = $etd->ingest("test previousStatus");
    $this->assertNotNull($pid);
    $this->assertEqual(null, $etd->previousStatus(),
           "previousStatus should return null when there is no previous status, got '"
           . $etd->previousStatus() . "'");
    

    
    // setting status to reviewed; previous status is draft
    $etd = new etd($pid);
    $etd->setStatus("reviewed");
    $etd->save("setting status to reviewed");

    $this->assertEqual("draft", $etd->previousStatus(),
           "previousStatus should return draft, got '"
           . $etd->previousStatus() . "'");


    // setting status to published; previous status is reviewed
    $etd = new etd($pid);
    $etd->setStatus("published");
    $etd->save("setting status to published");
    // make a non-status-related change to rels-ext; previous status is 2 versions ago, still reviewed
    $etd->rels_ext->author = "you";  
    $etd->save("making non-status change to rels-ext");
    
    $this->assertEqual("reviewed", $etd->previousStatus(),
           "previousStatus should return reviewed, got '"
           . $etd->previousStatus() . "'");

    $this->fedora->purge($pid, "removing test etd");
  }

  function testUpdateEmbargo() {
    // set a current embargo to be extended
    $this->etd->mods->embargo_end =  date("Y-m-d", strtotime("+1 year", time()));;
    // set status to published so policy rules will be updated
    $this->etd->setStatus("published");
    // simulate embargo notice having already been sent
    $this->etd->embargo_expiration_notice();
    $event_count = count($this->etd->premis->event);

    $new_embargodate = date("Y-m-d", strtotime("+2 year", time()));
    $this->etd->updateEmbargo($new_embargodate, "testing update embargo");
    
    // check that all the appropriate changes are made
    $this->assertEqual($new_embargodate, $this->etd->mods->embargo_end,
           "embargo end date should now be '$new_embargodate', is '"
           . $this->etd->mods->embargo_end . "'");
    $this->assertFalse(isset($this->etd->embargo_notice),
           "embargo notice admin note should NOT be present in MODS");

    // embargo condition date in xacml policies updated
    //   - etd
    $this->assertEqual($new_embargodate,  $this->etd->policy->published->condition->embargo_end,
           "embargo end in published policy should be '$new_embargodate', got '"
           . $this->etd->policy->published->condition->embargo_end . "'");
    //  - pdf
    $this->assertEqual($new_embargodate,  $this->etd->pdfs[0]->policy->published->condition->embargo_end,
           "embargo end in pdf published policy should be '$new_embargodate', got '"
           . $this->etd->pdfs[0]->policy->published->condition->embargo_end . "'");
    // - supplement
    $this->assertEqual($new_embargodate,  $this->etd->supplements[0]->policy->published->condition->embargo_end,
           "embargo end in published policy should be '$new_embargodate', got '"
           . $this->etd->supplements[0]->policy->published->condition->embargo_end . "'");
    
    // premis event for record history
    $this->assertEqual($event_count + 1, count($this->etd->premis->event),
           "additional event added to record history");
    $this->assertEqual("Access restriction ending date is updated to $new_embargodate - testing update embargo",
        $this->etd->premis->event[$event_count]->detail); // text of last event

  }
  
    // embargo condition date in xacml policies updated
  function testUpdateEmbargo_inactive() {
    // inactive record (e.g., previously published)
    $this->etd->mods->embargo_end =  date("Y-m-d", strtotime("+1 year", time()));;
    $this->etd->setStatus("inactive");

    $new_embargodate = date("Y-m-d", strtotime("+2 year", time()));
    // should cause no errors, even though expiration notice & publish policies are not present
    $this->etd->updateEmbargo($new_embargodate, "testing update embargo");

    // change status to published, and NEW embargo should be set in policies
    $this->etd->setStatus("published");
    // embargo condition date in xacml policies updated
    //   - etd
    $this->assertEqual($new_embargodate,  $this->etd->policy->published->condition->embargo_end,
           "embargo end in published policy should be '$new_embargodate', got '"
           . $this->etd->policy->published->condition->embargo_end . "'");
    //  - pdf
    $this->assertEqual($new_embargodate,  $this->etd->pdfs[0]->policy->published->condition->embargo_end,
           "embargo end in pdf published policy should be '$new_embargodate', got '"
           . $this->etd->pdfs[0]->policy->published->condition->embargo_end . "'");
    // - supplement
    $this->assertEqual($new_embargodate,  $this->etd->supplements[0]->policy->published->condition->embargo_end,
           "embargo end in published policy should be '$new_embargodate', got '"
           . $this->etd->supplements[0]->policy->published->condition->embargo_end . "'");


    // leading zeroes are NOT invalid dates
    $this->etd->updateEmbargo("2011-01-11", "testing update embargo");

    // leading zeroes are NOT invalid dates
    $this->etd->updateEmbargo("2011-11-01", "testing update embargo");
    
  }

  function testUpdateEmbargo_invalid_date() {
    try {
      // invalid format for new embargo end date
      $this->etd->updateEmbargo("2008/01/01", "testing update embargo");
    } catch (Exception $e) {
      $ex = $e;
    }
    $this->assertIsA($e, "Exception",
         "exception caught when passing invalid date format to UpdateEmbargo");
    if (isset($e)) {
      $this->assertPattern("/Invalid date format .* must be in YYYY-MM-DD format/", $e->getMessage(),
         "exception message explains invalid date format");
    }

    $ex = null;
    try {
      // date is in the past for new embargo end date
      $this->etd->updateEmbargo(date("Y-m-d", strtotime("-1 year", time())),
        "test updating embargo into the past");
    } catch (Exception $e) {
      $ex = $e;
    }
    $this->assertIsA($e, "Exception",
         "exception caught when passing date in the past to UpdateEmbargo");
    if (isset($e)) {
      $this->assertPattern("/New embargo date .* is in the past; cannot update embargo/",
         $e->getMessage(),
         "exception message about date in the past");
    }
    
  }


  function testReadyToSubmit(){
    $fname = '../fixtures/etd2.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $etd = new etd($dom);
    $etd->setSchoolConfig($this->school_cfg->graduate_school);
    $etd->mods->addNote("yes", "admin", "pq_submit");

    //Test increasing embarog levels
    $etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_NONE);
    $result = $etd->readyToSubmit("mods");
    $this->assertTrue($result);

    $etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_FILES);
    $result = $etd->readyToSubmit("mods");
    $this->assertTrue($result);

    $etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_TOC);
    $result = $etd->readyToSubmit("mods");
    $this->assertTrue($result);

    $etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_ABSTRACT);
    $result = $etd->readyToSubmit("mods");
    $this->assertTrue($result);

    //Test decreasing embargo levels

    $etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_TOC);
    $result = $etd->readyToSubmit("mods");
    $this->assertTrue($result);

    $etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_FILES);
    $result = $etd->readyToSubmit("mods");
    $this->assertTrue($result);

    $etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_NONE);
    $result = $etd->readyToSubmit("mods");
    $this->assertTrue($result);
}



}
runtest(new TestEtd());

?>

