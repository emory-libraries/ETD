<?
require_once("../bootstrap.php"); 
/**
 * unit tests for the Config Controller
 * - display configuration xml (used in various xforms)
 */


require_once('../ControllerTestCase.php');
require_once('controllers/FileController.php');

class FileControllerTest extends ControllerTestCase {

  private $filepid;
  private $etdpid;
  private $userpid;
  private $etdfile;

  private $mock_etdfile;
  
  function setUp() {
    $this->response = $this->makeResponse();
    $this->request  = $this->makeRequest();

    $this->test_user = new esdPerson();
    $this->test_user->role = "author";		// slight hack - test without etd roles logic
    $this->test_user->netid = "author";
    $this->test_user->name = "Author";
    $this->test_user->lastname = "Jones";
    Zend_Registry::set('current_user', $this->test_user);

    //    $this->filepid = fedora::ingest(file_get_contents('../fixtures/etdfile.xml'), "loading test etdFile");
    $this->etdpid = fedora::ingest(file_get_contents('../fixtures/etd2.xml'), "loading test etd");
    //    $this->userpid = fedora::ingest(file_get_contents('../fixtures/user.xml'), "loading test user object");

    // use mock etd object to simplify permissions/roles/etc
    $this->mock_etdfile = &new MockEtdFile();
    $this->mock_etdfile->setReturnValue('getResourceId', "draft file");

    $FileController = new FileControllerForTest($this->request,$this->response);
    $gff = $FileController->getHelper("GetFromFedora");
    $gff->setReturnObject($this->mock_etdfile);

  }
  
  function tearDown() {
    //    fedora::purge($this->filepid, "removing test etdFile");
    fedora::purge($this->etdpid, "removing test etd");
    //    fedora::purge($this->userpid, "removing test user object");

    $FileController = new FileControllerForTest($this->request,$this->response);
    $gff = $FileController->getHelper("GetFromFedora");
    $gff->clearReturnObject();
  }

  public function testViewAction() {
    $FileController = new FileControllerForTest($this->request,$this->response);

    $this->mock_etdfile->dc->mimetype = "application/pdf";
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
    $viewVars = $FileController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertTrue(isset($viewVars['pid']));
    $this->assertIsA($viewVars['etd'], "etd");
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

    // attempting to simulate file upload
    $_FILES['file'] = array("tmp_name" => "php123", "size" => 150,
			    "type" => "application/pdf", "error" => UPLOAD_ERR_OK, "name" => "original.pdf");
    // NOTE: too much actual file-handling logic is in the constructor; how to abstract/mock/test ?
    //    $FileController->newAction();
    //    $this->assertTrue($FileController->redirectRan);
  }

  // can't test update - same file-handling problems as new

  public function testEditAction() {
    $FileController = new FileControllerForTest($this->request,$this->response);

    // use mock etd object to simplify permissions/roles/etc
    $this->mock_etdfile->pid = $this->filepid;
    
    // etdfile is not in draft mode, adding new file should not be allowed
    $this->setUpGet(array('pid' => $this->filepid));
    $FileController->editAction();
    $viewVars = $FileController->view->getVars();
    $this->assertTrue(isset($viewVars['title']));
    $this->assertTrue(isset($viewVars['etdfile']));
    $this->assertTrue($viewVars['xforms']);
    $this->assertIsA($viewVars['namespaces'], "Array");
    $this->assertTrue(isset($viewVars['xforms_bind_script']));
    $this->assertTrue(isset($viewVars['xforms_model_uri']));
    $this->assertPattern("|view/dc|", $viewVars['xforms_model_uri']);
  }

  // not sure how to test saving... (only testing non-xml saves in testing edit controller)

  // not sure how to test removing... depends on etdfile relation to etd
  
}




class FileControllerForTest extends FileController {
  
  public $renderRan = false;
  public $redirectRan = false;
  
  public function initView() {
    $this->view = new Zend_View();
    Zend_Controller_Action_HelperBroker::addPrefix('TestEtd_Controller_Action_Helper');
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