<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

require_once("models/programs.php");

class ProgramController extends Etd_Controller_Action {

  public function indexAction() {
    $section = $this->_getParam("section", "programs");
    
    $programs = new foxmlPrograms("#" . $section);
    $this->view->programs = $programs->skos;
    $this->view->title = "Programs Hierarchy";
    if ($section != "programs") $this->view->title .= " ($section)";
    $this->view->section = $section;
  }

  public function xmlAction() {
    $programs = new foxmlPrograms();
    $this->_helper->displayXml($programs->skos->saveXML());
    
  }

  public function saveAction() {
    // check that the user is authorized to edit some portion of the program hierarchy
    if (!($this->acl->isAllowed($this->current_user, "programs", "edit") ||
	  $this->acl->isAllowed($this->current_user, "undergrad programs", "edit"))) {
      $this->_helper->access->notAllowed("edit", $this->current_user->role, "programs");
      return false;
    }

    // initialize programs object at the specified level
    $section = $this->_getParam("section", "programs");
    $programObj = new foxmlPrograms("#" . $section);
    $programs = $programObj->skos;
    // update from parameters passed in from edit form
    $this->updateMembers($programs);
    
    if ($programObj->skos->hasChanged()) {
      $save_result = $programObj->save("updating programs - section $section");
      $this->view->save_result = $save_result;
      if ($save_result) {
        $this->_helper->flashMessenger->addMessage("Saved changes to programs hierarchy ($section)");
        $this->logger->info("Saved programs hierarchy (" . $programObj->pid . ") changes to $section at $save_result");
      } else {	// record changed but save failed for some reason
        $message = "Could not save changes to programs hierarchy $section";
        $this->_helper->flashMessenger->addMessage($message);
        $this->logger->err($message);
      }
    } else {
      $this->_helper->flashMessenger->addMessage("No changes made to programs hierarchy $section");
    }

    // redirect to programs index page
    $this->_helper->redirector->gotoRoute(array("controller" => "program",
						"action" => "index"), '', true);

  }

  // recursive function to update programs hierarchy
  // from form data - labels and members of collections
  protected function updateMembers($item) {
    $newlabel = $this->_getParam($item->getId(), null);
    if ($newlabel) $item->label = $newlabel;	// don't update with blank
    
    $memberlist = $this->_getParam($item->getId() . "_members", null);
    if ($memberlist != null) {
      $members = explode(" ", trim($memberlist));
      // update collection members to these items in this order
      $item->setMembers($members);
    }
    
    // recurse on subcollections
    foreach ($item->members as $member) {
      if ($member->id) $this->updateMembers($member);
    }
  }
  
}
