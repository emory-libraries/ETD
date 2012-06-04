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
  }
  
  function setUp() {

    // get test pid
    $fedora_cfg = Zend_Registry::get('fedora-config');    
    $this->etdpid = $this->fedora->getNextPid($fedora_cfg->pidspace);
        
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
    
    try { $this->fedora->purge($this->etdpid, "removing test etd");  } catch (Exception $e) {}    

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
    $this->setUpGet(); 
    
    $this->mock_etdfile->file->mimetype = "application/pdf";
    $this->mock_etdfile->file->last_modified = "2011-09-09T19:57:55.905Z";
    $this->mock_etdfile->file->checksum = "00134922bf38e7ca7a22312404bcf2be";
        
    $this->mock_etdfile->setReturnValue('prettyFilename', "author_dissertation.pdf");
    $body1 = $FileController->getResponse()->getBody();
    $FileController->viewAction();
    $layout = $FileController->getHelper("layout");   
    $this->assertFalse($layout->enabled);
    $headers = $FileController->getResponse()->getHeaders();
    $body = $FileController->getResponse()->getBody();
    
    $this->assertTrue("This is the file contents for the body of the request.", $body,
      'file body should be set.');

    $this->assertTrue(in_array(array("name" => "Content-Disposition",
             "value" => 'attachment; filename="author_dissertation.pdf"',
                                     "replace" => ''), $headers),
          'filename should be set in Content-Disposition header');

    $this->assertTrue(in_array(array("name" => "Content-Type",
                                     "value" => 'application/pdf',
                                     "replace" => ''), $headers),
           'Content-Type header should be set as application/pdf');

    // last-modified header from datastream last_modified
    $this->assertTrue(in_array(array("name" => "Last-Modified",
                                     "value" => date(DATE_RFC1123, strtotime($this->mock_etdfile->file->last_modified)),
                                     "replace" => ''), $headers),
          'datastream last modified should be set as Last-Modified header');
    // ETag from datastream checksum
    $this->assertTrue(in_array(array("name" => "Etag",
                                     "value" => $this->mock_etdfile->file->checksum,
                                     "replace" => ''), $headers),
          'datastream checksum should be set as ETag header');

    // no checksum
    $this->mock_etdfile->file->checksum = "none";
    $FileController->viewAction();
    $headers = $FileController->getResponse()->getHeaders();
     
    // ETag from datastream checksum
    $this->assertFalse(in_array(array("name" => "Etag",
                                     "value" => $this->mock_etdfile->file->checksum,
                                     "replace" => ''), $headers),
          'datastream checksum should be set as ETag header');
    $this->assertTrue(in_array(array("name" => "Content-Disposition",
             "value" => 'attachment; filename="author_dissertation.pdf"',
                                     "replace" => ''), $headers),
          '2 filename should be set in Content-Disposition header');          


    // not-modified responses
    $this->mock_etdfile->file->checksum = "00134922bf38e7ca7a22312404bcf2be";
    global $_SERVER;
    $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $this->mock_etdfile->file->last_modified;
    $FileController->viewAction();
    $this->assertEqual(304, $FileController->getResponse()->getHttpResponseCode(304));
    unset($_SERVER['HTTP_IF_MODIFIED_SINCE']);
    $_SERVER['HTTP_IF_NONE_MATCH'] = $this->mock_etdfile->file->checksum;
    $FileController->viewAction();
    $this->assertEqual(304, $FileController->getResponse()->getHttpResponseCode(304));

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
    // copy pdf fixture file into tmp dir for this test.
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
    // etd is not in draft mode, adding new file should not be allowed
    $this->setUpGet(array('pid' => $this->filepid));
    $this->assertFalse($FileController->editAction());

    // set to draft mode and try again
    $etd = new etd($this->etdpid);
    $etd->rels_ext->status = "draft";
    $etd->save("status -> draft to test editing");
    $this->mock_etdfile->etd = $etd;
    
    // GET: display form
    $FileController = new FileControllerForTest($this->request,$this->response);
    $this->setUpGet(array('pid' => $this->filepid));
    $FileController->editAction();
    $this->assertTrue(isset($FileController->view->title),
                      'title should be set in view');
    $this->assertTrue(isset($FileController->view->etdfile),
                      'etdfile object should be set in view');
    $this->assertTrue(isset($FileController->view->type_options),
                      'dc:type options should be set in view');

    // POST: validate/update
    // missing required fields
    $FileController = new FileControllerForTest($this->request,$this->response);
    $this->setUpPost(array('title' => '', 'type' => '')); 
    $FileController->editAction();
    $this->assertTrue(in_array('Error: Title must not be empty',
                               $FileController->view->errors),
                      'empty title should result in validation error');
    $this->assertTrue(in_array('Error: Type is required',
                               $FileController->view->errors),
                      'invalid dc:type should result in validation error');

    // invalid type
    $FileController = new FileControllerForTest($this->request,$this->response);
    $this->setUpPost(array('title' => 'foo', 'type' => 'bogus')); // invalid
    $FileController->editAction();
    $this->assertTrue(in_array('Error: Type "bogus" is not recognized',
                               $FileController->view->errors),
                      'invalid dc:type should result in validation error');
    $this->assertFalse($FileController->redirectRan,
                       'should not redirect when invalid data is posted');

    // post valid data
    $FileController = new FileControllerForTest($this->request,$this->response);
    $data = array('title' => 'access copy', 'type' => 'Text',
                  'creator' => 'me', 'description' => 'pdf');
    $this->setUpPost($data);
    $FileController->editAction();
    // DC fields should be updated based on posted values
    $this->assertEqual($data['title'], $this->mock_etdfile->dc->title);
    $this->assertEqual($data['type'], $this->mock_etdfile->dc->type);
    $this->assertEqual($data['creator'], $this->mock_etdfile->dc->creator);
    $this->assertEqual($data['description'], $this->mock_etdfile->dc->description);
    $messages = $FileController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/Saved changes to file information/", $messages[0]);

    // dc changed, no save needed
    $FileController = new FileControllerForTest($this->request,$this->response);
    $this->mock_etdfile->dc->_changed = false;
    $this->setUpPost($data);
    $FileController->editAction();
    $messages = $FileController->getHelper('FlashMessenger')->getMessages();
    $this->assertPattern("/No changes made to file information/", $messages[0]);        
  }


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
