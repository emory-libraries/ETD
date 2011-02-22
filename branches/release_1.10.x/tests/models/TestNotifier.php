<?

require_once("../bootstrap.php");
require_once('models/etd_notifier.php');
require_once("fixtures/esd_data.php");

class NotifierTest extends UnitTestCase {
    private $esd_data;
    private $esd;
    private $user;
    private $_realconfig;
    private $_realschoolconfig;
    private $testConfig;
    private $testSchoolsConfig;
    private $advisor;
    private $member;
    private $admin;


function setUp() {
    
//ESD database fixtures
      $this->esd_data = new esd_test_data();
      $this->esd_data->loadAll();

      $this->esd = new esdPersonObject();
      $this->user = $this->esd->findByUsername("bschola");

    // etd object for tests
    $fname = '../fixtures/etd2.xml';
    $dom = new DOMDocument();
    $dom->load($fname);
    $this->etd = new etd($dom);
    $this->etd->owner = "bschola";
    $this->etd->mods->author->last = "Scholar";
    $this->etd->mods->author->first = "Brad";
    $this->etd->mods->author->id = "bschola";
    $this->etd->mods->author->id = "bschola";
        
    //Attach AuthorInfo
    $authorInfo = new authorInfo();
    $authorInfo->mads->initializeFromEsd($this->user);
    $authorInfo->label = $authorInfo->mads->name->first . " " . $authorInfo->mads->name->last;
    $authorInfo->owner = $authorInfo->mads->netid;
    $authorInfo->mads->permanent->email = "bsperm@somewhere.com";
    $this->etd->related_objects['authorInfo'] = $authorInfo;

    //remove nobody and nobodytoo (and addng other admins) because using them in esd data messes up other tests
    $this->etd->mods->setCommittee(array("pstaff"), "chair");
    $this->etd->mods->setCommittee(array("engrbs"), "committee");
    $this->etd->mods->removeCommittee("nobody");
    $this->etd->mods->removeCommittee("nobodytoo");


    //Get committee membes tht are in this fixture and admins that are in the schools config
    $this->advisor = $this->esd->findByUsername("pstaff");
    $this->member = $this->esd->findByUsername("engrbs");
    $this->admin = $this->esd->findByUsername("gadmin");

    //save original config
    $this->_realconfig = Zend_Registry::get('config');
    $this->_realschoolconfig = Zend_Registry::get('schools-config');

        //set test config
        $cfg = array("email" =>
                   array("etd" =>array("address" =>"etd-admin@listserve.com", "name" =>"ETD ADMIN"),
                         "ott" =>array("address" =>"ott@emory.com", "name" =>"OTT Office")

            ),       "contact" =>array("email" =>"contact@emory.com", "name" =>"ETD Help"));
        $this->testConfig = new Zend_Config($cfg);
        Zend_Registry::set('config', $this->testConfig);

        //set test schools config
        $schools = $this->_realschoolconfig;
      // override admin configurations for testing
      $schools->graduate_school->admin = array("netid" => 'gadmin',
                                                "department" => $schools->graduate_school->admin->department);

    }

      function tearDown() {
          // restore real config to registry
          Zend_Registry::set('config', $this->_realconfig);
          Zend_Registry::set('schools-config', $this->_realschoolconfig);

          $this->esd_data->cleanUp();
      }



    function testApproval(){
        $notifier = new NotifierForTest($this->etd);
        $to = $notifier->approval();
        
        $this->assertEqual($this->etd->authorInfo->mads->name, $to[$this->etd->authorInfo->mads->current->email], "current email of author is included on email");
        $this->assertEqual($this->etd->authorInfo->mads->name, $to[$this->etd->authorInfo->mads->permanent->email], "permanent email of author is included on email");
        $this->assertEqual($this->advisor->fullname, $to[$this->advisor->email], "email of advisor is included on email");
        $this->assertEqual($this->member->fullname, $to[$this->member->email], "email of committee is included on email");
    }


    function testPatentConcerns(){
        $notifier = new NotifierForTest($this->etd);
        $to = $notifier->patent_concerns();

        $this->assertEqual($this->testConfig->email->etd->name, $to[$this->testConfig->email->etd->address], "etd-admin is included on email");
        $this->assertEqual($this->testConfig->email->ott->name, $to[$this->testConfig->email->ott->address], "ott office is included on email");
    }

    //FIXME: Add more tests


    //This will text most of the casae
    function testSetRecipients(){
        //"all" perm addrss, current address and committee chair and members
        $notifier = new NotifierForTest($this->etd);
        $notifier->setRecipientsTest("all"); //author perm addrss, current address and committee chair and members
        $to = array_merge($notifier->to, $notifier->cc);
        $this->assertEqual($this->etd->authorInfo->mads->name, $to[$this->etd->authorInfo->mads->current->email], "current email of author is included on email");
        $this->assertEqual($this->etd->authorInfo->mads->name, $to[$this->etd->authorInfo->mads->permanent->email], "permanent email of author is included on email");
        $this->assertEqual($this->advisor->fullname, $to[$this->advisor->email], "email of advisor is included on email");
        $this->assertEqual($this->member->fullname, $to[$this->member->email], "email of committee is included on email");

        //no param should work the same as  "all" perm addrss, current address and committee chair and members
        $notifier = new NotifierForTest($this->etd);
        $notifier->setRecipientsTest();
        $to = array_merge($notifier->to, $notifier->cc);
        $this->assertEqual($this->etd->authorInfo->mads->name, $to[$this->etd->authorInfo->mads->current->email], "current email of author is included on email");
        $this->assertEqual($this->etd->authorInfo->mads->name, $to[$this->etd->authorInfo->mads->permanent->email], "permanent email of author is included on email");
        $this->assertEqual($this->advisor->fullname, $to[$this->advisor->email], "email of advisor is included on email");
        $this->assertEqual($this->member->fullname, $to[$this->member->email], "email of committee is included on email");

        //"author" =  perm addrss, current address
        $notifier = new NotifierForTest($this->etd);
        $notifier->setRecipientsTest("author");
        $to = array_merge($notifier->to, $notifier->cc);
        $this->assertEqual($this->etd->authorInfo->mads->name, $to[$this->etd->authorInfo->mads->current->email], "current email of author is included on email");
        $this->assertEqual($this->etd->authorInfo->mads->name, $to[$this->etd->authorInfo->mads->permanent->email], "permanent email of author is included on email");

        //"author-permanent" =  perm addrss
        $notifier = new NotifierForTest($this->etd);
        $notifier->setRecipientsTest("author-permanent");
        $to = array_merge($notifier->to, $notifier->cc);
        $this->assertEqual($this->etd->authorInfo->mads->name, $to[$this->etd->authorInfo->mads->permanent->email], "permanent email of author is included on email");

        //"permanent" =  perm addrss and committee chair and members
        $notifier = new NotifierForTest($this->etd);
        $notifier->setRecipientsTest("permanent");
        $to = array_merge($notifier->to, $notifier->cc);
        $this->assertEqual($this->etd->authorInfo->mads->name, $to[$this->etd->authorInfo->mads->permanent->email], "permanent email of author is included on email");
        $this->assertEqual($this->advisor->fullname, $to[$this->advisor->email], "email of advisor is included on email");
        $this->assertEqual($this->member->fullname, $to[$this->member->email], "email of committee is included on email");

        //"patent_info" =  OTT office and ETD ADMIN
        $notifier = new NotifierForTest($this->etd);
        $notifier->setRecipientsTest("patent_info");
        $to = array_merge($notifier->to, $notifier->cc);
        $this->assertEqual($this->testConfig->email->etd->name, $to[$this->testConfig->email->etd->address], "etd-admin is included on email");
        $this->assertEqual($this->testConfig->email->ott->name, $to[$this->testConfig->email->ott->address], "ott office is included on email");

        //"author-school" =  author perm and current email and school admins
        $notifier = new NotifierForTest($this->etd);
        $notifier->setRecipientsTest("author-school");
        $to = array_merge($notifier->to, $notifier->cc);
        $this->assertEqual($this->etd->authorInfo->mads->name, $to[$this->etd->authorInfo->mads->current->email], "current email of author is included on email");
        $this->assertEqual($this->etd->authorInfo->mads->name, $to[$this->etd->authorInfo->mads->permanent->email], "permanent email of author is included on email");
        $this->assertEqual($this->admin->fullname, $to[$this->admin->email], "school admin is included on email");
        

    }


}

class NotifierForTest extends etd_notifier {
public $to = array();
public $cc= array();
public $send_to = array();

public function getMail(){
    return $this->mail;
}

public function setRecipientsTest($who="all"){
    $this->setRecipients($who);
}



}


runtest(new NotifierTest());
?>
