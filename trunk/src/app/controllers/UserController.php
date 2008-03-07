<?php

require_once("models/user.php");

class UserController extends Etd_Controller_Action {
  protected $requires_fedora = true;
  
  public function viewAction() {
    if ($this->_hasParam("pid")) {
      $user = new user($this->_getParam("pid"));
      if (!$this->_helper->access->allowedOnUser("view", $user)) return;
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
    if (!$this->_helper->access->allowedOnUser("create")) return;
    $this->_forward("edit");
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
    if (is_null($pid))	{ // if pid is null, action is actually create (user is not author on an object yet)
      if (!$this->_helper->access->allowedOnUser("create")) return;
    } else { 		// if pid is defined, action is editing an existing object
      if (!$this->_helper->access->allowedOnUser("edit", $user)) return;
    }
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
    // should only be set for new records 
    $etd_pid = $this->_getParam("etd", null);
    
    $user = new user($pid);
    if (is_null($pid))	{ // if pid is null, action is actually create (user is not author on an object yet)
      if (!$this->_helper->access->allowedOnUser("create")) return;
    } else { 		// if pid is defined, action is editing an existing object
      if (!$this->_helper->access->allowedOnUser("edit", $user)) return;
    }

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
	$user->rels_ext->addRelationToResource("rel:authorInfoFor", $etd_pid);
      }
      
      $user->mads->updateXML($xml);

      // if no date for current, set to today
      if (!$user->mads->current->date) $user->mads->current->date = date("Y-m-d");	
      // normalize date format
      $user->normalizeDates();
      
      $resource = "contact information";
      if ($user->mads->hasChanged()) {
	$save_result = $user->save("edited user information");
	if ($save_result)
	  $this->_helper->flashMessenger->addMessage("Saved changes to $resource");
	else
	  $this->_helper->flashMessenger->addMessage("Error: could not save changes to $resource");
      } else {
	$this->_helper->flashMessenger->addMessage("No changes made to $resource");
      }

    }

    if ($etd_pid) {
      $etd = new etd($etd_pid);
      $etd->rels_ext->addRelationToResource("rel:hasAuthorInfo", $user->pid);
      $save_result = $etd->save("associated user object with etd");
      // only display a message if there is a problem
      if (!$save_result)  // record changed but save failed for some reason
	$this->_helper->flashMessenger->addMessage("Error: problem associating contact information with your ETD record");
    }

    //redirect to user view
    $this->_helper->redirector->gotoRoute(array("controller" => "user",
    						"action" => "view", "pid" => $user->pid), "", true);

    
    $this->view->pid = $user->pid;
    $this->view->xml = $xml;
    $this->view->title = "save user information";
   }

  // show mads xml - referenced as model for xform
   public function madsAction() {
    // if pid is null, display template xml with netid set to id for current user
     $pid = $this->_getParam("pid", null);
     
     $user = new user($pid);
     if (is_null($pid))	{ // if pid is null, action is actually create (user is not author on an object yet)
       if (!$this->_helper->access->allowedOnUser("create")) return;
     } else { 		// if pid is defined, action is viewing an existing object
       if (!$this->_helper->access->allowedOnUser("view", $user)) return;
     }
     $this->view->user = $user;

     if (is_null($pid)) {
       // empty template - set a few values
       $user->mads->initializeFromEsd($this->view->current_user);
     }

     $this->_helper->displayXml($user->mads->saveXML());
   }

  
}
