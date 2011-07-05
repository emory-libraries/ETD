<?
require_once("../bootstrap.php"); 
/**
 * unit tests for the File Controller
 * etdFile handling (view, edit, etc.)
 */


require_once('../ControllerTestCase.php');
require_once('controllers/FileController.php');

class FileControllerTest extends ControllerTestCase {

  private $etdfile;
  private $filepid;
  private $mock_etdfile;

  // fedoraConnection
  private $fedora;

  function __construct() {
    $this->fedora = Zend_Registry::get("fedora");
    $fedora_cfg = Zend_Registry::get('fedora-config');

    // get test pid
    $this->etdpid = $this->fedora->getNextPid($fedora_cfg->pidspace);
  }


  
  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();

    $ep = new esdPerson();
    $this->test_user = $ep->getTestPerson();
    $this->test_user->role = "author";    // slight hack - test without etd roles logic
    $this->test_user->netid = "author";
    $this->test_user->firstname = "Author";
    $this->test_user->lastname = "Jones";
    Zend_Registry::set('current_user', $this->test_user);

    $dom = new DOMDocument();
    // load etd & set pid & author relation
    $dom->loadXML(file_get_contents('../fixtures/etd2.xml'));
    $foxml = new foxml($dom);
    $foxml->pid = $this->etdpid;
    $foxml->ingest("loading test etd object");

    // use mock etd object to simplify permissions/roles/etc
    $this->mock_etdfile = &new MockEtdFile();
    $this->mock_etdfile->setReturnValue('getResourceId', "draft file");

    $FileController = new FileControllerForTest($this->request,$this->response);
    $gff = $FileController->getHelper("GetFromFedora");
    $gff->setReturnObject($this->mock_etdfile);

  }
  
  function tearDown() {
    $this->fedora->purge($this->etdpid, "removing test etd");

    $FileController = new FileControllerForTest($this->request,$this->response);
    $gff = $FileController->getHelper("GetFromFedora");
    $gff->clearReturnObject();

    $this->mock_etdfile->type = null;
    $this->mock_etdfile->etd = null;    

    Zend_Registry::set('solr', null);
    Zend_Registry::set('current_user', null);
  }

  public function testViewAction() {
    $FileController = new FileControllerForTest($this->request,$this->response);

    $this->mock_etdfile->file->mimetype = "application/pdf";
    $this->mock_etdfile->setReturnValue('prettyFilename', "author_dissertation.pdf");

    $FileController->viewAction();
    $layout = $FileController->getHelper("layout");
    $this->assertFalse($layout->enabled);
    $headers = $FileController->getResponse()->getHeaders();
    $this->assertTrue(in_array(array("name" => "Content-Disposition",
             "value" => 'attachment; filename="author_dissertation.pdf"',
             "replace" => ''), $headers));

    $this->assertTrue(in_array(array("name" => "Content-Type",
             "value" => 'application/pdf',
             "replace" => ''), $headers));


    // should not be allowed to view
    $this->test_user->role = "guest";
    Zend_Registry::set('current_user', $this->test_user);
    $FileController = new FileControllerForTest($this->request,$this->response);
    $this->assertFalse($FileController->viewAction());
  }

  public function testAddAction() {
    $FileController = new FileControllerForTest($this->request,$this->response);

    // clear mock etdfile so etd can be pulled from fedora
    $gff = $FileController->getHelper("GetFromFedora");
    $gff->clearReturnObject();

    // etd is not in draft mode, add should not be allowed
    $this->setUpGet(array('etd' => $this->etdpid));
    $this->assertFalse($FileController->addAction());

    // set to draft mode and try again
    $etd = new etd($this->etdpid);
    $etd->rels_ext->status = "draft";
    $etd->save("status -> draft to test editing");
    
    $FileController->addAction();
    $this->assertTrue(isset($FileController->view->title));
    $this->assertTrue(isset($FileController->view->pid));
    $this->assertIsA($FileController->view->etd, "etd");
  }

  public function testNewAction() {
    $FileController = new FileControllerForTest($this->request,$this->response);

    // clear mock etdfile so etd can be pulled from fedora
    $gff = $FileController->getHelper("GetFromFedora");
    $gff->clearReturnObject();

    // etd is not in draft mode, adding new file should not be allowed
    $this->setUpGet(array('etd' => $this->etdpid, "filetype" => "pdf"));
    $this->assertFalse($FileController->newAction());

    // set to draft mode and try again
    $etd = new etd($this->etdpid);
    $etd->rels_ext->status = "draft";
    $etd->save("status -> draft to test editing");

    $tmpfile = tempnam("/tmp", "etdtest-");
  
    // uploading a non-pdf for pdf file should fail
    $_FILES['file'] = array("tmp_name" => $tmpfile, "size" => 150,
          "type" => "text/plain", "error" => UPLOAD_ERR_OK,
          "name" => "original.txt");
    $this->setUpGet(array('etd' => $this->etdpid, "filetype" => "pdf"));
    $this->assertFalse($FileController->newAction());
    $this->assertFalse(isset($FileController->view->file_pid));
    $messages = $FileController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/file is not an allowed type./", $messages[0]);

    // NOTE: too much actual file-handling logic is in the constructor;
    // how to abstract/mock/test this?

    /*** actual ingest of test etd_file fails; not sure why
    file_put_contents($tmpfile, "test file");
    $_FILES['file']["type"] = "application/pdf";

    $FileController->newAction();
    $messages = $FileController->getHelper('FlashMessenger')->getMessages();
    print "<pre>"; print_r($messages); print "</pre>";
    */
    unlink($tmpfile);
  }

  public function testUpdateAction() {
    $this->mock_etdfile->type = "pdf";
    $this->mock_etdfile->etd = new Etd($this->etdpid);

    $FileController = new FileControllerForTest($this->request, $this->response);
    $tmpfile = tempnam("/tmp", "etdtest-");
  
    // uploading a non-pdf to update pdf object should fail
    $_FILES['file'] = array("tmp_name" => $tmpfile, "size" => 150,
          "type" => "text/plain", "error" => UPLOAD_ERR_OK,
          "name" => "original.txt");        
    $this->setUpGet(array('pid' => "mock_etdfilepid"));   
    $this->assertFalse($FileController->updateAction());   
    $this->assertFalse(isset($FileController->view->file_pid));
    $messages = $FileController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/file is not an allowed type./", $messages[0]);

    // updating mock etdfile object
    # copy pdf fixture file into tmp dir for this test.
    $srcfile = "../fixtures/tinker_sample.pdf";
    $testfile = "/tmp/tinker_sample.pdf";
    copy($srcfile, $testfile);
    
    $_FILES['file'] = array("tmp_name" => $testfile,
         "size" => filesize($testfile),
          "type" => "application/pdf", "error" => UPLOAD_ERR_OK,
          "name" => "diss.pdf");
    $allowed_types = array("text/plain"); 
    $this->assertFalse($FileController->updateAction());    
    $messages = $FileController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/Successfully updated file/", $messages[0]); 
  }


  public function testEditAction() {
    $FileController = new FileControllerForTest($this->request,$this->response);

    // use mock etd object to simplify permissions/roles/etc
    $this->mock_etdfile->pid = $this->filepid;
    
    // etdfile is not in draft mode, adding new file should not be allowed
    $this->setUpGet(array('pid' => $this->filepid));
    $FileController->editAction();
    $this->assertTrue(isset($FileController->view->title));
    $this->assertTrue(isset($FileController->view->etdfile));
    $this->assertTrue($FileController->view->xforms);
    $this->assertIsA($FileController->view->namespaces, "Array");
    $this->assertTrue(isset($FileController->view->xforms_bind_script));
    $this->assertTrue(isset($FileController->view->xforms_model_uri));
    $this->assertPattern("|view/dc|", $FileController->view->xforms_model_uri);
  }

  // not sure how to test saving... (only testing non-xml saves in testing edit controller)

  public function testRemoveAction() {
    $FileController = new FileControllerForTest($this->request,$this->response);
    $this->setUpGet(array('pid' => $this->filepid));
  
    // guest should not be allowed to remove
    $this->test_user->role = "guest";
    $this->assertFalse($FileController->removeAction());
    $this->assertFalse($FileController->redirectRan);

    // remove depends on relation to etd
    $this->mock_etdfile->etd = new MockEtd();
    $this->mock_etdfile->setReturnValue('delete', 'timestamp');
    // set role to author and status to draft so delete will be allowed
    $this->mock_etdfile->etd->status = "draft";
    $this->mock_etdfile->etd->user_role = "author";
    $FileController->current_user = $this->test_user;
    $this->setUpGet(array('pid' => $this->filepid));
    $FileController->removeAction();
    $this->assertTrue($FileController->redirectRan);
    $messages = $FileController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/Successfully removed file/", $messages[0]);


  }
  
}




class FileControllerForTest extends FileController {
  
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
}   

runtest(new FileControllerTest());
?>
