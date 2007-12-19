<?php

require_once("models/user.php");

class UserController extends Zend_Controller_Action {

  public function postDispatch() {
    $this->view->messages = $this->_helper->flashMessenger->getCurrentMessages();
    
    $env = Zend_Registry::get('env-config');
    $this->view->site_mode = $env->mode;	// better name for this? (test/dev/prod)
  }

  public function viewAction() {
    $user = new user($this->_getParam("pid"));

    $this->view->user = $user;
  }

  // create a new user record
  public function newAction() {
    $this->_forward("edit");
  }

  public function findAction() {
    $user = user::find_by_username($this->_getParam("id"));
    $this->view->user = $user;
    $this->_helper->viewRenderer->setScriptAction("view");
  }


  // fixme: if pid is blank, create a new record?
  public function editAction() {
    // default to null pid - create an empty user object
    $pid = $this->_getParam("pid", null);
    $user = new user($pid);

    $this->view->title = "Edit User Information";
    $this->view->user = $user;

    // xforms setting - so layout can include needed code in the header
    $this->view->xforms = true;
    $this->view->xforms_bind_script = "user/_mads_bind.phtml";
    $this->view->namespaces = array("mads" => "http://www.loc.gov/mads/");
    //$this->view->xforms_model_xml = $user->mads->saveXML();
    // link to xml rather than embedding directly in the page
    $this->view->xforms_model_uri = $this->view->url(array("controller" => "user",
							   "action" => "mads", "pid" => $pid), '', true);
  }


  public function saveAction() {
    $pid = $this->_getParam("pid", null);
    $user = new user($pid);

    // fixme: if pid is null, probably should set a few other things (label, dc:title?)
    
    global $HTTP_RAW_POST_DATA;
    $xml = $HTTP_RAW_POST_DATA;
    if ($xml == "") {
      // if no xml is submitted, don't modify 
      // forward to a different view?
      $this->view->noxml = true;
    } else {

      if (is_null($pid)) {
	// new record - set object label & dc:title, object owner
	$dom = new DOMDocument();
	$dom->loadXML($xml);
	$mads = new mads($dom);
	$user->label = $mads->name->first . " " . $mads->name->last;
	$user->owner = $mads->netid;
      }
      
      $user->mads->updateXML($xml);
      print "foxml: <br/>\n<pre>" . htmlentities($user->saveXML()) . "</pre>";
      $this->view->save_result = $user->save("edited user information");
      $this->_helper->flashMessenger->addMessage("Saved changes");
    }

    $this->view->pid = $user->pid;
    $this->view->xml = $xml;
    $this->view->title = "save user information";
   }

  // show mads xml - referenced as model for xform
   public function madsAction() {
    // if pid is null, display template xml with netid set to id for current user
     $pid = $this->_getParam("pid", null);
     $user = new user($pid);

     if (is_null($pid)) {
       // empty template - set a few values
       $auth = Zend_Auth::getInstance();
       if ($auth->hasIdentity()) { $identity = $auth->getIdentity(); }
       $user->mads->netid = $identity;
       $user->mads->date = date("Y-m-d");
     }

     $xml = $user->mads->saveXML();
     //$xml = $user->saveXML();

     // disable layouts and view script rendering in order to set content-type header as xml
     $this->getHelper('layoutManager')->disableLayouts();
     $this->_helper->viewRenderer->setNoRender(true);
       
     $this->getResponse()->setHeader('Content-Type', "text/xml")->setBody($xml);
   }

  
}
