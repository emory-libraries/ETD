<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

require_once("models/etd.php");
require_once("models/programs.php");
require_once("models/languages.php");
require_once("models/degrees.php");
require_once("models/researchfields.php");
require_once("models/vocabularies.php");

class EditController extends Etd_Controller_Action {

  protected $requires_fedora = true;

  public function preDispatch() {
    // suppress sidebar search & browse navigation for all edit actions,
    // for any user
    $this->view->suppress_sidenav_browse = true;
  }

  //Create schools options for dropdown boxes
  public function _school_options($schools){
      //Create options schools
      foreach ($schools as $school){
        if(isset($school->acl_id)){ //This excludes the All Schools collection
            $options[$school->acl_id] = $school->label;
        }

    }
    return $options;

  }
  
  // edit main record metadata
  public function recordAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return false;
      
    $this->view->title = "Edit Record Information";
    $this->view->etd = $etd;

    $this->view->extra_scripts = array(
         "//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js",
    );

    $lang = new languages();
    $language_options = $lang->edit_options();
    $this->view->language_options = $language_options;
    $degrees = new degrees();
    $degree_opts = $degrees->edit_options();
    $degree_info = $degrees->degree_info();
    $this->view->degree_options = $degree_opts;

    // on GET, display edit form (no additional logic)
    $errors = array();
    
    // on POST, process form data
    if ($this->getRequest()->isPost()) {
      // Update etd mods with posted data;
      // used either to save or redisplay the edit form
      
      $invalid = false;   // assume valid until we find otherwise
      
      $etd->mods->author->last = $this->_getParam("last-name", null);
      if (empty($etd->mods->author->last)) {
        $errors['last-name'] = 'Last name is required';
        $invalid = true;
      }
      $etd->mods->author->first = $this->_getParam("first-name", null);
      if (empty($etd->mods->author->first)) {
        $errors['first-name'] = 'First name is required';
        $invalid = true;
      }
      $etd->mods->author->full = $etd->mods->author->last . ', ' .
        $etd->mods->author->first;
      $lang = $this->_getParam('language', null);
      // check that language is a valid code
      if (! array_key_exists($lang, $language_options)) {
        $errors['language'] = 'Error: Language code "' . $lang . '" not recognized';
        $invalid = true;
      } else {
        // set code and text in xml
        $etd->mods->language->code = $lang;
        $etd->mods->language->text = $language_options[$lang];
      }
      
      $keywords_raw = $this->_getParam('keywords', array());
      $keywords = array();
      // sanitize input slightly: trim whitespace, uniquify
      foreach (array_unique($keywords_raw) as $kwr) {
        $k = trim($kwr);
        if (! empty($k)) {
          $keywords[] = $k;
        }
      }
      $etd->mods->setKeywords($keywords);
      // check that at least one keyword is set
      if (! count($keywords) || empty($keywords[0])) {
        $errors['keywords'] = 'Please enter at least one keyword';
        $invalid = true;
      } 
      
      $degree = $this->_getParam('degree', null);
      if (empty($degree)) {
        $errors['degree'] = 'Please select a degree';
        $invalid = true;
      } else if (! array_key_exists($degree, $degree_info)) {
        $errors['degree'] = 'Error: Degree "' . $degree . '" not recognized';
        $invalid = true;
      } else {
        // set degree level, name, and genre in xml
        $etd->mods->degree->name = $degree;
        $etd->mods->degree->level = $degree_info[$degree]['level'];
        // current logic seems to be setting the same value in both genre fields
        // can't find any documentation about what (if anything) should be different
        $etd->mods->genre = $degree_info[$degree]['genre'];
        $etd->mods->setMarcGenre();  // add in case not already present
        $etd->mods->marc_genre = $degree_info[$degree]['genre'];
      }

      // if invalid, redisplay form with error information
      
      // otherwise, save and redirect to main record page
      if (! $invalid) {
        // valid : save and redirect
        if ($etd->mods->hasChanged()) {
          $save_result = $etd->save("edited record information");
          
          $this->view->save_result = $save_result;
          if ($save_result) {
            $this->_helper->flashMessenger->addMessage("Saved changes to record information");
            $this->logger->info("Saved changes to record information for " . 
                                $etd->pid . " at $save_result");
          } else {
            $this->_helper->flashMessenger->addMessage("Error: could not save changes to record information");
            $this->logger->err("Could not save changes to record information for " .
                               $etd->pid);
          }
          
        } else {
          // mods unchanged
          $this->_helper->flashMessenger->addMessage("No changes made to record information");
        }
        
        // redirect to main etd record
        $this->_helper->redirector->gotoRoute(array("controller" => "view",
                                                    "action" => "record",
                                                    "pid" => $etd->pid), '', true);
      } // end save valid data
      
    } // end POST

    
    // errors should always be set, for form display (always empty on GET)
    $this->view->errors = $errors;
                                                       
    
  }

  // edit school and program and optional subfield
  public function programAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;
    
    if ($this->getRequest()->isGet()) {
        $this->view->title = "Edit Program";
        $this->view->etd = $etd;

        $programObject = new foxmlPrograms();
        $this->view->programs = $programObject->skos;
        // select correct sub-section of progam hierarchy based on which school ETD belongs to
        switch ($etd->schoolId()) {
        case 'grad':
          $this->view->program_section = $programObject->skos->grad;
          break;
        case 'honors':
          $this->view->program_section = $programObject->skos->undergrad;
          break;
        case 'candler':
          $this->view->program_section = $programObject->skos->candler;
          break;
          case 'rollins':
          $this->view->program_section = $programObject->skos->rollins;
          break;
          // NOTE: currently no default; view falls back to full program hierarchy if section is not set
        }

        // info for schools edit form
        $schools = $config = Zend_Registry::get('schools-config');
        $this->view->schoolId = $etd->schoolId(); //Current schoolId used for default value in select list
        $this->view->options = $this->_school_options($schools);
    }

    elseif ($this->getRequest()->isPost()) {
        $schoolId = $this->_getParam("schoolId");
        $schoolIdOld = $this->_getParam("schoolIdOld");

        //If school id has changed then forward to school update action
        if($schoolId != $schoolIdOld){
            $this->_forward("save-school", "edit", null, array('$schoolId' => $schoolIdOld,
                                                           '$schoolIdOld' => $schoolIdOld));
        }
        else{ //else save program
            $this->view->etd = $etd;

            // remove leading # from ids (should be stored in rels-ext without  #)
            $program_id = preg_replace("/^#/", "", $this->_getParam("program_id", null));
            $subfield_id = preg_replace("/^#/", "", $this->_getParam("subfield_id", null));

            $etd->rels_ext->program = $program_id;
            $programObject = new foxmlPrograms();
            $etd->department = $programObject->skos->findLabelbyId("#" . $program_id);
            if ($subfield_id == null) {
              // blank out rel if already set (don't add empty relation if not needed)
              if (isset($etd->rels_ext->subfield)) $etd->rels_ext->subfield = "";
              // blank out mods subfield
              $etd->mods->subfield = "";
            } else {
              $etd->rels_ext->subfield = $subfield_id;
              $etd->mods->subfield = $programObject->skos->findLabelbyId("#" . $subfield_id);
            }

            if ($etd->mods->hasChanged() || $etd->rels_ext->hasChanged()) {
              $save_result = $etd->save("updated program");
              $this->view->save_result = $save_result;
              if ($save_result) {
                  $this->_helper->flashMessenger->addMessage("Saved changes to program");
                  $this->logger->info("Updated etd " . $etd->pid . " program at $save_result");
              } else {
                  $this->_helper->flashMessenger->addMessage("Could not save changes to program (permission denied?)");
                  $this->logger->err("Could not save changes to program on  etd " . $etd->pid);
              }
            } else {
              $this->_helper->flashMessenger->addMessage("No changes made to program");
            }

            $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
                    "pid" => $etd->pid), '', true);
        }
        
    }

  }

  public function saveRightsAction() {
    $etd = $this->_helper->getFromFedora('pid', 'etd');
    if (!$this->_helper->access->allowedOnEtd('edit metadata', $etd)) return;

    $this->_helper->viewRenderer->setNoRender(true);

    if ( ! $this->validateRights($etd) ) {
      // invalid input - forward back to rights edit form and stop processing
      // more specific error messages may be set by validateRights function
      // - if there is no error message, add a generic 'invalid' message
      if (! $this->_helper->flashMessenger->hasMessages()) 
  $this->_helper->flashMessenger->addMessage('Error: invalid input, please check and resubmit.');
      $this->view->messages = $this->_helper->flashMessenger->getCurrentMessages();
      
      $this->_forward("rights", "edit", null, array("pid" => $etd->pid));
      // note: using forward instead of redirect so parameters can be picked up
      return;
    }
    
    $embargo = $this->_getParam('embargo', null);
    $embargo_level = $this->_getParam('embargo_level', null);
    $pq_submit = $this->_getParam('pq_submit', null);
    $pq_copyright = $this->_getParam('pq_copyright', null);
    $submission_agreement = $this->_getParam('submission_agreement', null);

    $this->view->etd = $etd;

    // embargo

    if ($embargo){
      $etd->mods->setEmbargoRequestLevel($embargo_level);
      
      //Restore mods values when embargo level decreases
      if($embargo_level == etd_mods::EMBARGO_TOC)
      { 
          $etd->abstract = $etd->html->abstract;
      }
      elseif($embargo_level <= etd_mods::EMBARGO_FILES){
         $etd->abstract = $etd->html->abstract;
         $etd->contents = $etd->html->contents;
      }
    }
    else{
      //Restore mods values when no embargo
      $etd->mods->setEmbargoRequestLevel(etd_mods::EMBARGO_NONE);
      $etd->abstract = $etd->html->abstract;
      $etd->contents = $etd->html->contents;
    }

    // proquest

    if ( ! isset($etd->mods->pq_submit) )
      $etd->mods->addNote('', 'admin', 'pq_submit');
    if ( ! isset($etd->mods->copyright) )
      $etd->mods->addNote('', 'admin', 'copyright');
    if ($etd->isRequired('send to ProQuest') && 
        ($etd->mods->ProquestRequired() ||
         $pq_submit)) {
      $etd->mods->pq_submit = 'yes';

      if ($pq_copyright)
        $etd->mods->copyright = 'yes';
      else
        $etd->mods->copyright = 'no';
    } else {
      $etd->mods->pq_submit = 'no';
      $etd->mods->copyright = 'no';
    }

    // use and reproduction

    if ($submission_agreement) {
      // if student has checked that they agree to the submission agreement,
      // add the useAndReproduction statement to the mods record
      $config = Zend_Registry::get('config');
      $etd->mods->useAndReproduction = $config->useAndReproduction;
    } else {
      $etd->mods->useAndReproduction = "";  // blank out - submission agreement not accepted
    }

    if ($etd->mods->hasChanged()) {
      $save_result = $etd->save('updated rights');
      $this->view->save_result = $save_result;
      if ($save_result) {
        $this->_helper->flashMessenger->addMessage('Saved changes to rights');
        $this->logger->info('updated etd ' . $etd->pid . " rights at $save_result");
      } else {
        $this->_helper->flashMessenger->addMessage('Could not save changes to rights (permission denied?)');
        $this->logger->err('Could not save changes to rights on etd ' . $etd->pid);
      }
    } else {
      $this->_helper->flashMessenger->addMessage('No changes made to rights');
    }

    $this->_helper->redirector->gotoRoute(array('controller' => 'view', 'action' => 'record',
                                                'pid' => $etd->pid), '', true);
  }

    // form for editing school membership
  public function schoolAction() {
    //Make sure only superuser can access page
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit school", $etd)) return;

    $schools = $config = Zend_Registry::get('schools-config');

    $this->view->title = "Edit School";
    $this->view->etd = $etd;
    $this->view->schoolId = $etd->schoolId(); //Current schoolId used for default value in select list
    $this->view->options = $this->_school_options($schools);


  }

    // save school collection in RELS-EXT
  public function saveSchoolAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;

      $schoolId = $this->_getParam('schoolId', null);
      $schoolIdOld = $this->_getParam('schoolIdOld', null);
      $school_cfg = Zend_Registry::get("schools-config");

     if($schoolId != $schoolIdOld){

        //Get old collection name based on acl id from form
        $school = $school_cfg->getSchoolByAclId($schoolIdOld);
        $collectionOld = $school->fedora_collection;
        $labelOld = $school->label;

        //Get new collection name based on acl id from form
        $school = $school_cfg->getSchoolByAclId($schoolId);
        $collection = $school->fedora_collection;
        $label = $school->label;

        //Remove old collection and add new collection
        $etd->rels_ext->removeRelation("rel:isMemberOfCollection", $collectionOld);
        $etd->rels_ext->addRelation("rel:isMemberOfCollection", $collection, true);

        //Remove Program info so it will have to be set again for the new school
        $etd->rels_ext->program = "";
        $etd->department = "";
        if (isset($etd->rels_ext->subfield)) $etd->rels_ext->subfield = "";
        $etd->mods->subfield = "";

        $etd->save("Changed school collection from $collectionOld to $collection");
        $this->logger->info("Changed {$etd->pid} School from $labelOld to $label");
        $this->_helper->flashMessenger->addMessage("School has been changed from $labelOld to $label");
        $this->_helper->flashMessenger->addMessage("Program will have to be reassigned");
     }

     //redirects to program because the program will have to be rest every time the school is changed
     $this->_helper->redirector->gotoRoute(array("controller" => "edit", "action" => "program",
            "pid" => $etd->pid), '', true);
  }



  /**
   * Sanity-check input from rights edit form.
   * @return boolean valid/invalid
   */
  private function validateRights($etd) {
    $embargo = $this->_getParam('embargo', null);
    $embargo_level = $this->_getParam('embargo_level', null);
    $pq_submit = $this->_getParam('pq_submit', null);
    $pq_copyright = $this->_getParam('pq_copyright', null);
    $submission_agreement = $this->_getParam('submission_agreement', null);

    // Embargo is always in the form. If it's missing from the response then
    // the client is doing something funny. This one has some legal
    // implications, so reject it if it's not specified.
    if ($embargo === null) return false;
    // If they say they want an embargo, require them to specify what type.
    // This time, the form doesn't have a default, so let the user know
    // if he failed to enter a field.
    if ($embargo && ($embargo_level === null)) {
      $this->_helper->flashMessenger->addMessage('Error: You have requested an access restriction; please specify the type of restriction.');
      return false;
    }
    // The form only allows valid levels. If they send an invalid level then
    // something funny is going on.
    if ( ! etd_mods::validEmbargoRequestLevel($embargo_level) )
      return false;

    // ProQuest submission is only on the form if PQ submission is available
    // at all (not the case for honors undergrads) and not required (as it
    // is for PhDs.
    if ($etd->isRequired("send to ProQuest") && 
        ! $etd->mods->ProquestRequired() && 
        $pq_submit === null)
      return false;
    // If ProQuest stuff isn't on the form at all (e.g., for an Honors ETD,
    // don't accept a hacked-in yes option.
    if ( ! $etd->isRequired("send to ProQuest") && $pq_submit)
      return false;
    // If the ETD is for a PhD dissertation, then the results must go to
    // ProQuest. It won't be in the form in that case, but if the client does
    // something funny and sends it anyway then reject it if they say no.
    if ($etd->mods->ProquestRequired() && ($pq_submit === 0))
      return false;
    // If the user requests ProQuest or is required to send it to them, then
    // copyright preference must be specified.
    if ($etd->isRequired('send to ProQuest') and
        ($pq_submit or
         $etd->mods->ProquestRequired()) and 
        ($pq_copyright === null))
      return false;


    // Other than that, we should be OK.
    return true;
  }


  public function facultyAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;
    $this->view->title = "Edit Committee/Adviser";
    $this->view->etd = $etd;
  }

  
  //Saves changes to Committee chair and members
  //Possible post values are:
  //ids for emory chair (Array) - info looked up in ESD
  //ids for emory committee members (Array) - info looked up in ESD
  //nonemory_first (Array)
  //nonemory_last (Array)
  //nonemory_affiliation (Array)
  //nonemory_chair_first (Array)
  //nonemory_chair_last (Array)
  //nonemory_chair_affiliation (Array)
  public function savefacultyAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;
    
    $this->view->etd = $etd;

    $chair_ids = $this->_getParam("chair", array());         
    $committee = $this->_getParam("committee", array());

    $committee_ids = array();
    // don't allow the same id to be added to both chair and member lists (results in duplicate ids ->  invalid xml)
    foreach ($committee as $id) {
      if (in_array($id, $chair_ids)) {  // don't add to committee list, display a message to the user
  $this->_helper->flashMessenger->addMessage("Warning: cannot add '$id' as both Committee Chair / Thesis Adviser and Member; adding as Chair only");
      } else {    // add to the list of committee ids that will be processed
  $committee_ids[] = $id;
      }
    }
    $this->view->committee_ids = $committee_ids;

    // set fields
    $etd->setCommittee($chair_ids, "chair");
    $etd->setCommittee($committee_ids);

    // any of these could now be former faculty; loop through and check for affiliation
    foreach ($chair_ids as $id) {
      if ($this->_hasParam($id . "_affiliation") &&
    ($affiliation = $this->_getParam($id . "_affiliation", "")) != "") {
  $etd->mods->setCommitteeAffiliation($id, $affiliation, "chair");
      }
    }
    foreach ($committee_ids as $id) {
      if ($this->_hasParam($id . "_affiliation") &&
    ($affiliation = $this->_getParam($id . "_affiliation", "")) != "") {
  $etd->mods->setCommitteeAffiliation($id, $affiliation);
      }
    }

    //non-emory chairs and non-emory committee members
    $nonemory_chair_first = $this->_getParam("nonemory_chair_firstname");
    $nonemory_chair_last = $this->_getParam("nonemory_chair_lastname");
    $nonemory_chair_affiliation = $this->_getParam("nonemory_chair_affiliation");
    $nonemory_first = $this->_getParam("nonemory_firstname");
    $nonemory_last = $this->_getParam("nonemory_lastname");
    $nonemory_affiliation = $this->_getParam("nonemory_affiliation");
    
    
    $etd->mods->setNonemoryCommittee($nonemory_chair_first,
                                     $nonemory_chair_last,
                                     $nonemory_chair_affiliation,
                                     $nonemory_first, $nonemory_last,
                                     $nonemory_affiliation);

    //Save mods if any info changed
    if ($etd->mods->hasChanged()) {
      $save_result = $etd->save("updated committe chair(s) & members");
      $this->view->save_result = $save_result;
      if ($save_result) {
  $this->_helper->flashMessenger->addMessage("Saved changes to committee/adviser");
  $this->logger->info("Updated etd " . $etd->pid . " committee chairs & members at $save_result");
      } else {
  $this->_helper->flashMessenger->addMessage("Could not save changes to committee/adviser (permission denied?)");
  $this->logger->err("Could not save changes to committee chairs & members on  etd " . $etd->pid);
      }
    } else {
      $this->_helper->flashMessenger->addMessage("No changes made to committee/adviser");
    }


    $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
                "pid" => $etd->pid), '', true);
  }




  public function rightsAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;

    // PQ submission question depends on degree name; if not set, forward to main edit page
    if ($etd->mods->degree->name == "") {
      $this->_helper->flashMessenger->addMessage("You must select your degree before editing Rights and Access Restrictions");
      // forward to main record edit page (includes degree)
      $this->_helper->redirector->gotoRoute(array("controller" => "edit", "action" => "record",
                "pid" => $etd->pid), '', true);
      return;
      
    }

    // get *current* messages to get validation errors when forwarding from save-rights
    $this->view->messages = $this->_helper->flashMessenger->getCurrentMessages();

    // if redirected from save-rights (invalid input), pre-set to user's last selections
    // if params are not present, default to what is set in the record
    if ($this->_hasParam("embargo")) 
      $this->view->embargo = $this->_getParam('embargo');
    else
      $this->view->embargo = $etd->mods->isEmbargoRequested();
    if ($this->_hasParam("embargo_level"))
      $this->view->embargo_level = $this->_getParam('embargo_level');
    else
      $this->view->embargo_level = $etd->mods->embargoRequestLevel();
    if ($this->_hasParam("pq_submit"))
      $this->view->pq_submit = $this->_getParam('pq_submit');
    else
      $this->view->pq_submit = $etd->mods->submitToProquest();
    if ($this->_hasParam("pq_copyright"))
      $this->view->pq_copyright = $this->_getParam('pq_copyright');
    else
      $this->view->pq_copyright = ($etd->mods->copyright == "yes");
    if ($this->_hasParam("submission_agreement")) 
      $this->view->submission_agreement = $this->_getParam('submission_agreement');
    else
      $this->view->submission_agreement = $etd->mods->hasSubmissionAgreement();
    
    $this->view->title = "Edit Author Rights/Access Restrictions";
    $this->view->etd = $etd;

    // fixme: unused until record is saved? 
    $this->view->rights_stmt = $config->useAndReproduction;
    
  }


  public function researchfieldAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;
    
    $this->view->title = "Edit Research Fields";
    $this->view->etd = $etd;

    $this->view->fields = new researchfields(); 
  }

  public function saveresearchfieldAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;
    
    $this->view->fields = $this->_getParam("fields");

    // construct an array of id => text name
    $values = array();
    foreach ($this->view->fields as $i => $value) {
      if(preg_match("/#([0-9]{4}) (.*)$/", $value, $matches)) {
  $id = $matches[1];
  $text = $matches[2];
  $values[$id] = $text;
      }
    }
    $etd->mods->setResearchFields($values);

    if ($etd->mods->hasChanged()) {
      $save_result = $etd->save("modified research fields");
      $this->view->save_result = $save_result;
      if ($save_result) {
  $this->_helper->flashMessenger->addMessage("Saved changes to research fields");
  $this->logger->info("Updated etd " . $etd->pid . " research fields at $save_result");
      } else {  // record changed but save failed for some reason
  $this->_helper->flashMessenger->addMessage("Could not save changes to research fields");
  $this->logger->err("Could not save etd " . $etd->pid . " research fields");
      }
    } else {
      $this->_helper->flashMessenger->addMessage("No changes made to research fields");
    }

    // return to record (fixme: make this a generic function of this controller? used frequently)
    $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
                "pid" => $etd->pid), '', true);

  }
  

  // formatted/html fields - edit one at a time 
  public function titleAction() {
    $this->_setParam("mode", "title");
    $this->_forward("html");
  }

  public function abstractAction() {
    $this->_setParam("mode", "abstract");
    $this->_forward("html");
  }
  
  public function contentsAction() {
    $this->_setParam("mode", "contents");
    $this->_forward("html");
  }

  // edit formatted fields
  public function htmlAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    $mode = $this->_getParam("mode", "title");

    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;

    $this->view->mode = $mode;
    $this->view->etd = $etd;
    $this->view->edit_content = $etd->html->{$mode};
    $label = ($mode == "contents") ? "Table of Contents" : ucfirst($mode);
    $this->view->mode_label =  $label;
    $this->view->title = "Edit $label";
  }


  public function savetitleAction() {
    $this->_setParam("mode", "title");
    $this->_forward("savehtml");
  }
  
  public function saveabstractAction() {
    $this->_setParam("mode", "abstract");
    $this->_forward("savehtml");
  }

  public function savecontentsAction() {
    $this->_setParam("mode", "contents");
    $this->_forward("savehtml");
  }

  
  // save formatted fields
  public function savehtmlAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");

    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;

    $mode = $this->_getParam("mode");
    $content = $this->_getParam("edit_content");
    // title/abstract/contents - special fields that set values in html, mods and dublin core
    $etd->$mode = $content;


    // fixme: make this a generic function? similar throughout controller
    if ($etd->html->hasChanged()) {
      $save_result = $etd->save("modified $mode");
      $this->view->save_result = $save_result;
      if ($save_result) {
  $this->_helper->flashMessenger->addMessage("Saved changes to $mode");
  $this->logger->info("Updated etd " . $etd->pid . " html $mode at $save_result");
      } else {  // record changed but save failed for some reason
  $this->_helper->flashMessenger->addMessage("Could not save changes to $mode");
  $this->logger->err("Could not save changes to etd " . $etd->pid . " html $mode");
      }
    } else {
      $this->_helper->flashMessenger->addMessage("No changes made to $mode");
    }

    $this->view->pid = $etd->pid;
    $this->view->mode = $mode;
    $this->view->title = "save $mode";


    // return to record (fixme: make this a generic function of this controller? used frequently)
    $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
                "pid" => $etd->pid), '', true);

  }

  // FIXME: do we still need this now after XForms are removed?
   public function savemodsAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;

    $component = $this->_getParam("component", "record information"); // what just changed
    $log_message = "edited $component";

    global $HTTP_RAW_POST_DATA;
    $xml = $HTTP_RAW_POST_DATA;

    if ($xml == "") {
      // if no xml is submitted, don't modify 
      // forward to a different view?
      $this->view->noxml = true;
    } else {
      $etd->mods->updateXML($xml);
      // update main record in case dept. has changed - set in etd & etdfile xacml, etc.
      $etd->department = $etd->mods->department;
      
      if ($etd->mods->hasChanged()) {
  $save_result = $etd->save($log_message);
  $this->view->save_result = $save_result;
  if ($save_result) {
    $this->_helper->flashMessenger->addMessage("Saved changes to $component");  // more info?
    $this->logger->info("Saved etd " . $etd->pid . " changes to $component at $save_result");
  } else {  // record changed but save failed for some reason
    $message = "Could not save changes to $component";
    $this->_helper->flashMessenger->addMessage($message);
    $this->logger->err($message);
  }
      } else {
  $this->_helper->flashMessenger->addMessage("No changes made to $component");
      }
    }


    $this->view->pid = $etd->pid;
    $this->view->xml = $xml;
    $this->view->title = "save mods";

    // return to record (fixme: make this a generic function of this controller? used frequently)
    $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
            "pid" => $etd->pid), '', true);
   }


   /* FIXME: should these two actions have a different action in the ACL, or is this sufficient? */
   
  public function fileorderAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");

    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;
    $this->view->title = "Edit File order";
    $this->view->etd = $etd;
  }

  public function savefileorderAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");

    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;
    
    $order['pdf'] = $this->_getParam("pdfs");
    $order['orig'] = $this->_getParam("originals");
    $order['supp'] = $this->_getParam("supplements");

    foreach ($order as $type) {
      for ($i = 0; $i < count($type); $i++) {
  $file = new etd_file($type[$i]);
  $seq = ($i + 1);
  // only set it if it's changed
  if ($file->rels_ext->sequence != $seq) {
    $file->rels_ext->sequence = $seq;
    $result = $file->save("re-ordered in sequence");
    if ($result) {
      $this->logger->info("Saved re-ordered file sequence on " . $file->pid);
    } else {
      $this->logger->err("Could not saved re-ordered file sequence for " . $file->pid);
    }
  }
      }
    }
    // error checking ?
    if ($result)
      $this->_helper->flashMessenger->addMessage("Saved changes");  // more info?
    else
      $this->_helper->flashMessenger->addMessage("No changes made");
    
    // return to record (fixme: make this a generic function of this controller? used frequently)
    $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
                "pid" => $etd->pid), '', true);

    
    $this->_helper->viewRenderer->setNoRender(true);
  }


  // edit partnering agencies
  public function partneringagencyAction() {
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;
    
    $this->view->title = "Edit Partnering Agencies";
    $this->view->etd = $etd;

    $vocabularyObject = new foxmlVocabularies("#vocabularies", "#vocabularies");
    $this->view->partnering_agencies = $vocabularyObject->skos;
    $this->view->vocabulary_section = $vocabularyObject->skos->partnering_agencies;  
  }
  
  public function savepartneringagencyAction() {
       
    $etd = $this->_helper->getFromFedora("pid", "etd");
    if (!$this->_helper->access->allowedOnEtd("edit metadata", $etd)) return;
    
    $this->view->fields = $this->_getParam("fields");

    // construct an array of id => text name
    $values = array();
    foreach ($this->view->fields as $i => $value) {      
      if(preg_match("/#([a-z\-]*) (.*)$/", $value, $matches)) {
        $id = $matches[1];
        $text = $matches[2];
        $values[$id] = $text; 
        //$this->_helper->flashMessenger->addMessage("FIELD[$id] = [$text]<br>");             
      }
    }
    $etd->mods->setPartneringAgencies($values);

    if ($etd->mods->hasChanged()) {
      $save_result = $etd->save("modified partnering agencies");
      $this->view->save_result = $save_result;
      if ($save_result) {
        $this->_helper->flashMessenger->addMessage("Saved changes to partnering agencies");
        $this->logger->info("Updated etd " . $etd->pid . " partnering agencies at $save_result");
      } else {  // record changed but save failed for some reason
        $this->_helper->flashMessenger->addMessage("Could not save changes to partnering agencies");
        $this->logger->err("Could not save etd " . $etd->pid . " partnering agencies");
      }
    } else {
      $this->_helper->flashMessenger->addMessage("No changes made to partnering agencies");
    }
    
    // return to record (fixme: make this a generic function of this controller? used frequently)
    $this->_helper->redirector->gotoRoute(array("controller" => "view", "action" => "record",
                "pid" => $etd->pid), '', true);
  }
   
}
