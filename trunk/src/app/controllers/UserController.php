<?php

require_once("models/user.php");

class UserController extends Zend_Controller_Action {

  public function init() {
    $this->initView();
	 
    $this->acl = Zend_Registry::get("acl");
    $this->user = $this->view->current_user;
  }


  private function isAllowed($action, user $user = null) {
    if (!is_null($user))
      $role = $user->getUserRole($this->user);
    else $role = $user->getRoleId();
    $allowed = $this->acl->isAllowed($role, "user", $action);
    if (!$allowed) $this->notAllowed($action, $role, "user");
    return $allowed;
  }

  // redirect to a generic access denied page, with minimal information why
  private function notAllowed($action, $role, $resource) {
    $this->_helper->flashMessenger->addMessage("Error: " . $this->user->netid . " (role=" . $role . 
					       ") is not authorized to $action $resource");
    $this->_helper->redirector->gotoRoute(array("controller" => "auth",
						"action" => "denied"), "", true);
  }


  
  public function postDispatch() {
    if ($this->_helper->flashMessenger->hasMessages())
      $this->view->messages = $this->_helper->flashMessenger->getMessages();
    elseif ($this->_helper->flashMessenger->hasCurrentMessages())
      $this->view->messages = $this->_helper->flashMessenger->getCurrentMessages();
    
    $env = Zend_Registry::get('env-config');
    $this->view->site_mode = $env->mode;	// better name for this? (test/dev/prod)
  }

  

  public function viewAction() {
    if ($this->_hasParam("pid")) {
      $user = new user($this->_getParam("pid"));
      if (!$this->isAllowed("view", $user)) return;
    } else {
      //default to currently logged in user
      // FIXME: still needed? may not make sense..
      $user = user::find_by_username(strtolower($this->view->current_user->netid));
    }

    $this->view->title = "View User Information";
    $this->view->user = $user;
  }

  // create a new user record
  public function newAction() {
    $this->_forward("edit");
    if (!$this->isAllowed("create")) return;
  }

  public function findAction() {
    // FIXME: still needed? may not make sense anymore..
    $user = user::find_by_username($this->_getParam("id"));
    $this->view->user = $user;
    print "user pid is " . $user->pid . "\n";
    $this->_helper->viewRenderer->setScriptAction("view");
  }


  // fixme: if pid is blank, create a new record?
  public function editAction() {
    // default to null pid - create an empty user object
    $pid = $this->_getParam("pid", null);

    // etd record the user should be added to   (fixme: what happens if this is not set?)
    $this->view->etd_pid = $this->_getParam("etd");	
    
    $user = new user($pid);
    if (!$this->isAllowed("edit", $user)) return;
    
    $this->view->user = $user;

    $this->view->title = "Edit User Information";

    // xforms setting - so layout can include needed code in the header
    $this->view->xforms = true;
    $this->view->xforms_bind_script = "user/_mads_bind.phtml";
    $this->view->namespaces = array("mads" => "http://www.loc.gov/mads/");
    // link to xml rather than embedding directly in the page

    $this->view->xforms_model_uri = $this->view->url(array("controller" => "user",
							   "action" => "mads", "pid" => $pid), '', true);
  }


  public function saveAction() {
    $pid = $this->_getParam("pid", null);
    
    $user = new user($pid);
    if (!$this->isAllowed("edit", $user)) return;

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

    // should only be set for new records 
    $etd_pid = $this->_getParam("etd", null);
    if ($etd_pid) {
      $etd = new etd($etd_pid);
      $etd->rels_ext->addRelationToResource("rel:hasAuthorInfo", $user->pid);
      $save_result = $etd->save("associated user object with etd");
      if ($save_result)
      	$this->_helper->flashMessenger->addMessage("Saved ETD (associated new user object)");
      else	// record changed but save failed for some reason
	$this->_helper->flashMessenger->addMessage("Could not save ETD (associated new user)");

      // FIXME: redundant - should be able to do above...
      $user->rels_ext->addRelationToResource("rel:AuthorInfoFor", $etd_pid);
      $save_result = $etd->save("associated etd with user object");
      if ($save_result)
      	$this->_helper->flashMessenger->addMessage("Saved user (associated etd object)");
      else	// record changed but save failed for some reason
	$this->_helper->flashMessenger->addMessage("Could not save user (associated etd)");
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
     if (!$this->isAllowed("view", $user)) return;
     
     $this->view->user = $user;

     if (is_null($pid)) {
       // empty template - set a few values
       $user->mads->initializeFromEsd($this->view->current_user);
     }

     $xml = $user->mads->saveXML();

     // disable layouts and view script rendering in order to set content-type header as xml
     $this->getHelper('layoutManager')->disableLayouts();
     $this->_helper->viewRenderer->setNoRender(true);
       
     $this->getResponse()->setHeader('Content-Type', "text/xml")->setBody($xml);
   }

  
}
