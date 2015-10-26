<?
require_once("../bootstrap.php"); 
/**
 * unit tests for the Documents Controller
 * - these are basically static pages with content in the templates
 * just checking that titles are set correctly and printable is true
 * 
 */


require_once('../ControllerTestCase.php');
require_once('controllers/HelpController.php');
      
class HelpControllerTest extends ControllerTestCase {

  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();
  }
  
  function tearDown() {
  }

  function testIndexAction() {
    $HelpController = new HelpControllerForTest($this->request,$this->response);
    $HelpController->init();


    // on GET, should display form
    $HelpController->indexAction();
    $this->assertTrue(isset($HelpController->view->title),
                      'title should be set in view');
    $this->assertTrue(isset($HelpController->view->extra_scripts),
                      'extra js scripts array should be set in view');
    $this->assertTrue(isset($HelpController->view->extra_css),
                      'extra CSS array should be set in view');
    $this->assertFalse($HelpController->view->printable);

    // not logged in, no pre-populated fields
    $this->assertFalse(isset($HelpController->view->fullname),
                       'full name should not be preset when user is not logged in');
    $this->assertFalse(isset($HelpController->view->email),
                       'email should not be preset when user is not logged in');
    $this->assertFalse(isset($HelpController->view->etd_link),
                       'etd link should not be preset when user is not logged in');

    // test prepopulated fields when user is logged in
    $test_user = new MockEsdPerson();
    $test_user->netid = "author";
    $test_user->firstname = "Author";
    $test_user->lastname = "Jones";
    // simulate user with no etds
    $test_user->returns('getEtds', array()); 
    $HelpController->current_user = $test_user;
    
    $HelpController->indexAction();
    $this->assertEqual($test_user->fullname, $HelpController->view->fullname,
                       'full name should be preset when user is logged in '
                       . '(expected ' . $test_user->fullname . ', got '
                       . $HelpController->view->fullname .')');
    $this->assertEqual($test_user->netid . '@emory.edu', $HelpController->view->email,
                       'email should be preset when user is logged in');
    $this->assertFalse(isset($HelpController->view->etd_link),
                       'etd link should not be preset when logged in user has no etd');
    // NOTE: not testing case where user has an etd because mocking getEtds
    // returning data does not work

    // on POST, process submitted data
    // - invalid; missing required fields
    $test_data = array('email' => 'me@somewhere.co', 'message' => 'help!');
    $this->setUpPost($test_data);
    $HelpController->indexAction();
    $this->assertTrue(isset($HelpController->view->error),
                      'error message is set in view when required fields are missing');
    $this->assertEqual($test_data['email'], $HelpController->view->email,
                       'email should be set from posted value');
    $this->assertEqual($test_data['message'], $HelpController->view->message,
                       'message should be set from posted value');

    // - all fields present
    $test_data = array('email' => 'me@elsewhere.org', 'username' => 'Me Who',
                       'subject' => 'need help', 'message' => 'submission error');
    $this->setUpPost($test_data);
    $HelpController->indexAction();
    $this->assertTrue($HelpController->view->email_sent);
    
  }

  function test_valid_submission(){
    $HelpController = new HelpControllerForTest($this->request,$this->response);
    $data = array('email' => 'me@elsewhere.org', 'username' => 'Me Who',
                  'subject' => 'need help', 'message' => 'submission error',
                  'grad_date' => '05/2010');
    $this->setupPost($data);
    $this->assertTrue($HelpController->test_valid_submission());

    // check each required field individually
    $test_data = $data;
    $test_data['email'] = '';
    $this->setupPost($test_data);
    $this->assertFalse($HelpController->test_valid_submission());

    $test_data = $data;
    $test_data['username'] = '';
    $this->setupPost($test_data);
    $this->assertFalse($HelpController->test_valid_submission());
    
    $test_data = $data;
    $test_data['subject'] = '';
    $this->setupPost($test_data);
    $this->assertFalse($HelpController->test_valid_submission());
    
    $test_data = $data;
    $test_data['message'] = '';
    $this->setupPost($test_data);
    $this->assertFalse($HelpController->test_valid_submission());

    // grad date is optional, should be valid
    $test_data = $data;
    $test_data['grad_date'] = '';
    $this->setupPost($test_data);
    $this->assertTrue($HelpController->test_valid_submission());
  }
  

  function test_send_email() {
    $HelpController = new HelpControllerForTest($this->request,$this->response);
    $data = array('email' => 'me@elsewhere.org', 'username' => 'Me Who',
                  'subject' => 'need help', 'message' => 'submission error',
                  'grad_date' => '05/2010', 'to_email' => 'etd@support.us');

    $this->setUpPost($data);
    // send_email returns the Zend_Mail object
    $notify = $HelpController->test_send_email();
    $this->assertIsA($notify, "Zend_Mail");
    $this->assertEqual($data['email'], $notify->getFrom(),
                       "From address should be set from submitted email");
    $this->assertEqual("ETD Help: " . $data['subject'], $notify->getSubject(),
                       "Subject should be set from submitted value (expected"
                       . $data['subject'] . ", got" . $notify->getSubject() . ")");
    $this->assertEqual(array($data['to_email']), $notify->getRecipients(),
                       'only recipient should be "to" address');

    $data['copy_me'] = True;
    $this->setUpPost($data);
    $notify = $HelpController->test_send_email();
    $recipients = $notify->getRecipients();
    $this->assertTrue(in_array($data['to_email'], $recipients), 
                      '"to" address should be included as a recipient');
    $this->assertTrue(in_array($data['email'], $recipients), 
                      'user email address should be set as a recipient when copy requested');
    

    // zend_mail doesn't provide access to body text, so can't test message contents
        
    
  }
}

class HelpControllerForTest extends HelpController {
  public $renderRan = false;
  public $redirectRan = false;


  public function initView() {
    $this->view = new Zend_View();
    Zend_Controller_Action_HelperBroker::addPrefix('Test_Controller_Action_Helper');
  }
  
  public function render() {
    $this->renderRan = true;
  }
  
  public function _redirect() {
    $this->redirectRan = true;
  }

  // expose protected functions for testing
  public function test_valid_submission() {
    return $this->valid_submission();
  }
  public function test_send_email() {
    return $this->send_email();
  }
} 	

runtest(new HelpControllerTest());

?>