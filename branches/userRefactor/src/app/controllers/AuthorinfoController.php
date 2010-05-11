<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

require_once("models/authorInfo.php");
require_once("models/etd.php");

class AuthorInfoController extends Etd_Controller_Action {
  protected $requires_fedora = true;
  
  public function viewAction() {
    $authorInfo = $this->_helper->getFromFedora("pid", "authorInfo");
    if (!$this->_helper->access->allowedOnAuthorInfo("view", $authorInfo)) return false;

    $this->view->title = "View User Information";
    $this->view->authorInfo = $authorInfo;
  }

  // create a new authorInfo record
  public function newAction() {
    if (!$this->_helper->access->allowedOnAuthorInfo("create")) return false;
    $this->_forward("edit");
  }

  public function findAction() {
    // FIXME: still needed? may not make sense anymore..
    $authorInfo = user::find_by_username($this->_getParam("id"));
    $this->view->authorInfo = $authorInfo;
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
      if (!$this->_helper->access->allowedOnAuthorInfo("create")) return false;

      // create new, empty user object
      //  - note: no longer using subclass for honors; configuration for required fields
      // 	now pulled from per-school configuration
      $authorInfo = new authorInfo();
      $authorInfo->mads->initializeFromEsd($this->view->current_user);
      
      // need access to etd in the view for displaying correct set of instructions
      $this->view->etd = $this->_helper->getFromFedora("etd", "etd");	  
    } else { 		// if pid is defined, action is editing a particular, existing object

      // FIXME:  need to initialize as honors user here too...
      $authorInfo = $this->_helper->getFromFedora("pid", "authorInfo");
      if (!$this->_helper->access->allowedOnAuthorInfo("edit", $authorInfo)) return false;

      // configure view so etd object will be available in the same
      // place when editing new or existing user info
      $this->view->etd = $authorInfo->etd;
    }
    $this->view->authorInfo = $authorInfo;

    $this->view->pid = $pid;

    $this->view->title = "Edit User Information";

    //Countries for select box
    $config_dir = Zend_Registry::get("config-dir");
    $xml = simplexml_load_file($config_dir . "countries.xml");
    $countries = array();

    foreach($xml as $country)
    {
       $countries[(string)$country] = $country;
    }
    $this->view->countries = $countries;

    //if there are any errors add errors to post data and redirect back to edit form
    if($this->_hasParam("errors")){
        $errors = $this->_getParam("errors");
        if(count($errors) > 0){
        $this->view->errors = $this->_getParam("errors");
        $this->view->allParams =  $this->_getAllParams();
        }

    }
        
    
  }


  public function saveAction() {
    //Get all params form post
    $pid = $this->_getParam("pid", null);
    $etd_pid = $this->_getParam("etd", null); // should only be set for new records

    $last = $this->_getParam("last", null);
    $first = $this->_getParam("first-middle", null);
    
    $cur_street = $this->_getParam("cur-street", null);
    $cur_city = $this->_getParam("cur-city", null);
    $cur_state = $this->_getParam("cur-state", null);
    $cur_country = $this->_getParam("cur-country", null);
    $cur_postcode = $this->_getParam("cur-postcode", null);
    $cur_phone = $this->_getParam("cur-phone", null);
    $cur_email = $this->_getParam("cur-email", null);
    
    $perm_street = $this->_getParam("perm-street", null);
    $perm_city = $this->_getParam("perm-city", null);
    $perm_state = $this->_getParam("perm-state", null);
    $perm_country = $this->_getParam("perm-country", null);
    $perm_postcode = $this->_getParam("perm-postcode", null);
    $perm_phone = $this->_getParam("perm-phone", null);
    $perm_email = $this->_getParam("perm-email", null);
    $perm_dae = $this->_getParam("perm-date", null);

    //values to validate
    $values["cur-email"] = $cur_email;
    $values["perm-email"] = $perm_email;

    $errors = $this->validateContactInfo($values);

    if(count($errors) > 0){

        $this->_setParam("errors", $errors);
        $this->_forward("edit");
        return;
    }


    if(empty($pid)){
     $authorInfo = new authorInfo();
     $authorInfo->mads->initializeFromEsd($this->view->current_user);
     // new record - set object label & dc:title, object owner etc
     $authorInfo->label = $authorInfo->mads->name->first . " " . $authorInfo->mads->name->last;
     $authorInfo->owner = $authorInfo->mads->netid;
     $authorInfo->rels_ext->addRelationToResource("rel:authorInfoFor", $etd_pid);
    }
    else{
     $authorInfo = new authorInfo($pid);
    }

   if (empty($pid))	{ // if pid is null, action is actually create (user is not author on an object yet)
      if (!$this->_helper->access->allowedOnAuthorInfo("create")) return false;
    } else { 		// if pid is defined, action is editing an existing object
      if (!$this->_helper->access->allowedOnAuthorInfo("edit", $authorInfo)) return false;
    }

    $authorInfo->mads->name->last = $last;
    $authorInfo->mads->name->first = $first;
        
    $authorInfo->mads->current->address->setStreet($cur_street);
    $authorInfo->mads->current->address->city = $cur_city;
    $authorInfo->mads->current->address->state = $cur_state;
    $authorInfo->mads->current->address->country = $cur_country;
    $authorInfo->mads->current->address->postcode = $cur_postcode;
    $authorInfo->mads->current->phone = $cur_phone;
    $authorInfo->mads->current->email = $cur_email;

    
    $authorInfo->mads->permanent->address->setStreet($perm_street);
    $authorInfo->mads->permanent->address->city = $perm_city;
    $authorInfo->mads->permanent->address->state = $perm_state;
    $authorInfo->mads->permanent->address->country = $perm_country;
    $authorInfo->mads->permanent->address->postcode = $perm_postcode;
    $authorInfo->mads->permanent->phone = $perm_phone;
    $authorInfo->mads->permanent->email = $perm_email;
    $authorInfo->mads->permanent->date = $perm_dae;

    //if no date for current, set to today
    if (!$authorInfo->mads->current->date) $authorInfo->mads->current->date = date("Y-m-d");
    // normalize date format
    $authorInfo->normalizeDates();



    $resource = "contact information";
    if ($authorInfo->mads->hasChanged()) {
	try {
	  $save_result = $authorInfo->save("edited user information");
	} catch (PersisServiceUnavailable $e) {
	  // this can only happen when saving a new record (ingest)
	  $this->_helper->flashMessenger->addMessage("Error: could not create new record because Persistent Identifier Service is not available");
	  // redirect to an error page
	  $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "unavailable"));
	}
	if ($save_result) {
	  $this->_helper->flashMessenger->addMessage("Saved changes to $resource");
	  $this->logger->info("Saved user " . $authorInfo->pid . " at $save_result - changed to $resource");
	} else {
	  $this->_helper->flashMessenger->addMessage("Error: could not save changes to $resource");
	  $this->logger->info("Could not save user " . $authorInfo->pid . " - changing $resource");
	}
      } else {
	$this->_helper->flashMessenger->addMessage("No changes made to $resource");
      }


    if (!empty($etd_pid)) {
      $etd = new etd($etd_pid);
      $etd->rels_ext->addRelationToResource("rel:hasAuthorInfo", $authorInfo->pid);
      $save_result = $etd->save("associated user object with etd");
      // only display a message if there is a problem
      if ($save_result) {
	$this->logger->info("Added user " . $authorInfo->pid . " to etd " . $etd->pid . " at $save_result");
      } else {  // record changed but save failed for some reason
	$this->_helper->flashMessenger->addMessage("Error: problem associating contact information with your ETD record");
	$this->logger->err("Problem adding user " . $authorInfo->pid . " to etd " . $etd->pid);
      }
    }

    //redirect to user view
    $this->_helper->redirector->gotoRoute(array("controller" => "authorInfo",
    						"action" => "view", "pid" => $authorInfo->pid), "", true);


    $this->view->pid = $authorInfo->pid;
    $this->view->title = "save user information";
   }

  // show mads xml - referenced as model for xform
   public function madsAction() {
    // if pid is null, display template xml with netid set to id for current user
     $pid = $this->_getParam("pid", null);

     if (is_null($pid))	{ // if pid is null, action is actually create (user is not author on an object yet)
       $authorInfo = new authorInfo($pid);	// create new object from template
       if (!$this->_helper->access->allowedOnAuthorInfo("create")) return false;
       
       // empty template - set a few values
       $authorInfo->mads->initializeFromEsd($this->view->current_user);
     } else { 		// if pid is defined, action is viewing an existing object
       $authorInfo = $this->_helper->getFromFedora("pid", "authorInfo");
       if (!$this->_helper->access->allowedOnAuthorInfo("view", $authorInfo)) return false;
     }
     $this->view->authorInfo = $authorInfo;

     $this->_helper->displayXml($authorInfo->mads->saveXML());
   }

   function validateContactInfo($values){
    $errors = array();

    //email validator
    $validator = new Zend_Validate_EmailAddress();

    $values["cur-email"] = trim($values["cur-email"]);
    $values["perm-email"] = trim($values["perm-email"]);

    if(!$validator->isValid($values["cur-email"])){
        $errors[] = "Error: email " . $values["cur-email"] .  " is invalid";
    }

    if(!$validator->isValid($values["perm-email"])){
        $errors[] = "Error: non-emory email " . $values["perm-email"] .  " is invalid";
    }
    elseif(substr($values["perm-email"], -10) == "@emory.edu"){
        $errors[] = "Error: non-emory email must be an non-emory or alumni address";
    }
    return $errors;
}

}
