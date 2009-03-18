<?php

require_once("models/user.php");
require_once("models/honors_user.php");

class UserController extends Etd_Controller_Action {
  protected $requires_fedora = true;
  
  public function viewAction() {
    $user = $this->_helper->getFromFedora("pid", "user");
    if (!$this->_helper->access->allowedOnUser("view", $user)) return false;

    $this->view->title = "View User Information";
    $this->view->user = $user;
  }

  // create a new user record
  public function newAction() {
    if (!$this->_helper->access->allowedOnUser("create")) return false;
    $this->_forward("edit");
  }

  public function findAction() {
    // FIXME: still needed? may not make sense anymore..
    $user = user::find_by_username($this->_getParam("id"));
    $this->view->user = $user;
    //    print "user pid is " . $user->pid . "\n";
    $this->_helper->viewRenderer->setScriptAction("view");
  }


  // edit or create (if pid is not specified, creates a new recodr);
  public function editAction() {
    // if pid is null, create an empty user object
    $pid = $this->_getParam("pid", null);

    // etd record the user object should belong to (required if pid is null)
    if (!$this->_hasParam("etd") && $pid == null)
      throw new Exception("Required parameter 'etd' is not set");
    $this->view->etd_pid = $this->_getParam("etd");	
    
    if (is_null($pid))	{
      // if pid is null, action is actually create (user is not author on an object yet)
      if (!$this->_helper->access->allowedOnUser("create")) return false;

      // create new empty user object
      if (preg_match("/^honors student/", $this->current_user->role)) {
	// if user is an honors student, create new record as honors user
	$user = new honors_user();
      } else {
	// for all other users, use default user object
	$user = new user();
      }
      // need access to etd in the view for displaying correct set of instructions
      $this->view->etd = EtdFactory::etdByPid($this->view->etd_pid);
    } else { 		// if pid is defined, action is editing a particular, existing object

      // FIXME:  need to initialize as honors user here too...
      $user = $this->_helper->getFromFedora("pid", "user");	  
      if (!$this->_helper->access->allowedOnUser("edit", $user)) return false;

      // configure view so etd object will be available in the same
      // place when editing new or existing user info
      $this->view->etd = $user->etd;
    }
    $this->view->user = $user;

    $this->view->title = "Edit User Information";

    // xforms setting - so layout can include needed code in the header
    $this->view->xforms = true;
    $this->view->xforms_bind_script = "user/_mads_bind.phtml";
    $this->view->namespaces = array("mads" => "http://www.loc.gov/mads/");
    // link to xml rather than embedding directly in the page

    $this->view->xforms_model_uri = $this->view->url(array("controller" => "user",
							   "action" => "mads", "pid" => $pid), '', true, false);
  }


  public function saveAction() {
    $pid = $this->_getParam("pid", null);
    // should only be set for new records 
    $etd_pid = $this->_getParam("etd", null);
    
    $user = new user($pid);
    if (is_null($pid))	{ // if pid is null, action is actually create (user is not author on an object yet)
      if (!$this->_helper->access->allowedOnUser("create")) return false;
    } else { 		// if pid is defined, action is editing an existing object
      if (!$this->_helper->access->allowedOnUser("edit", $user)) return false;
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
	try {
	  $save_result = $user->save("edited user information");
	} catch (PersisServiceUnavailable $e) {
	  // this can only happen when saving a new record (ingest)
	  $this->_helper->flashMessenger->addMessage("Error: could not create new record because Persistent Identifier Service is not available");
	  // redirect to an error page
	  $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "unavailable"));
	}
	if ($save_result) {
	  $this->_helper->flashMessenger->addMessage("Saved changes to $resource");
	  $this->logger->info("Saved user " . $user->pid . " at $save_result - changed to $resource");
	} else {
	  $this->_helper->flashMessenger->addMessage("Error: could not save changes to $resource");
	  $this->logger->info("Could not save user " . $user->pid . " - changing $resource");
	}
      } else {
	$this->_helper->flashMessenger->addMessage("No changes made to $resource");
      }

    }

    if ($etd_pid) {
      $etd = EtdFactory::etdByPid($etd_pid);
      $etd->rels_ext->addRelationToResource("rel:hasAuthorInfo", $user->pid);
      $save_result = $etd->save("associated user object with etd");
      // only display a message if there is a problem
      if ($save_result) {
	$this->logger->info("Added user " . $user->pid . " to etd " . $etd->pid . " at $save_result");
      } else {  // record changed but save failed for some reason
	$this->_helper->flashMessenger->addMessage("Error: problem associating contact information with your ETD record");
	$this->logger->err("Problem adding user " . $user->pid . " to etd " . $etd->pid);
      }
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

     if (is_null($pid))	{ // if pid is null, action is actually create (user is not author on an object yet)
       $user = new user($pid);	// create new object from template
       if (!$this->_helper->access->allowedOnUser("create")) return false; 
       
       // empty template - set a few values
       $user->mads->initializeFromEsd($this->view->current_user);
     } else { 		// if pid is defined, action is viewing an existing object
       $user = $this->_helper->getFromFedora("pid", "user");	  
       if (!$this->_helper->access->allowedOnUser("view", $user)) return false;
     }
     $this->view->user = $user;

     $this->_helper->displayXml($user->mads->saveXML());
   }
  
}
