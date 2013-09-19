<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */

require_once("models/etd.php");
require_once("models/stats.php");

class ViewController extends Etd_Controller_Action {

  protected $requires_fedora = true;
  
   // view a full record
   public function recordAction() {
     $etd = $this->_helper->getFromFedora("pid", "etd");
     if ($etd) {
       if (!$this->_helper->access->allowedOnEtd("view metadata", $etd)) return false;

       // using jquery for minor submission status interaction
       if ($etd->status() == 'draft') {
        $this->view->extra_scripts = array(
          "//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js",
        );
       }

       
       $this->view->etd = $etd;
       $this->view->title = $etd->dc->title;	// need non-formatted version of title
       //$this->view->dc = $etd->dc;		/* DC not as detailed as MODS; using etd DC header template */
       $this->view->messages = $this->_helper->flashMessenger->getMessages();

       // ETD abstract page should have print view link
       $this->view->printable = true;

       // set last modified header based on object modification date
       $this->getResponse()->setHeader('Last-Modified',
                                       date(DATE_RFC1123, strtotime($etd->last_modified)),
                                            true);

       // if user is not logged in, override session-based cache
       // headers that prevents any caching whatsoever
       if (! isset($this->current_user)) {
         $this->getResponse()->setHeader('Cache-Control', 'public', true)
           ->setHeader('Expires', date(DATE_RFC1123, strtotime('+1 year')))
           ->setHeader('Pragma', null, true);

         // send a not-modified response for the public version of this page, if possible
         $modified_since = $this->getRequest()->getHeader('If-Modified-Since', '');
         if ($modified_since &&
             strtotime($etd->last_modified) <= strtotime($modified_since)){
           $this->getResponse()->setHttpResponseCode(304);
           // no content needed for 304 Not Modified
           $this->_helper->layout->disableLayout();
           $this->_helper->viewRenderer->setNoRender(true);
         }    
       } elseif (strstr($this->current_user->role, 'student')  // could also be student with submission
            && $etd->status() == 'draft') {
        // if this is a student viewing a draft record, suppress sidenav search & browse
        // to avoid distraction during the submission process
         $this->view->suppress_sidenav_browse = true;
       }
     }
   }

   // show mods xml 
   public function modsAction() {
     $this->_setParam("datastream", "mods");
     $this->_forward("xml");
   }

   // show dublin core xml 
   public function dcAction() {
     $this->_setParam("datastream", "dc");
     $this->_forward("xml");
   }
   
   // show rels-ext xml 
   public function relsAction() {
     $this->_setParam("datastream", "rels_ext");
     $this->_forward("xml");
   }

   // show an xml datastream from etd or etdFile object
   public function xmlAction() {
     $type = $this->_getParam("type", "etd");

     if ($type == "etd") {
       $object = $this->_helper->getFromFedora("pid", $type);
       if (!$this->_helper->access->allowedOnEtd("view metadata", $object)) return;
     } elseif ($type == "etdfile") {
       $object = $this->_helper->getFromFedora("pid", "etd_file");
       if (!$this->_helper->access->allowedOnEtdFile("view", $object)) return;
     }

     $datastream = $this->_getParam("datastream");

     // new field added to make PQ submission optional for Masters students 
     // - used in edit/rights action
     if (($type == "etd" && $datastream == "mods")) {
       // if new field is not present, set it
       if  (!isset($object->mods->pq_submit)) {
       $object->mods->addNote("", "admin", "pq_submit");
       }
       // PhDs are always required to submit to ProQuest
       if ($object->mods->degree->name == "PhD") {
	 $object->mods->pq_submit = "yes";
       }
     }

     $mode = $this->_getParam("mode");
     
     if (isset($object->$datastream)) {	// check that it is the correct type, also?
       $this->_helper->displayXml($object->$datastream->saveXML());
     } else {
       throw new Exception("'$datastream' is not a valid datastream for " . $object->pid);
     }
   }

   /**
    * public version of MODS - sanitized to remove etd-specific stuff
    */
   public function publicModsAction() {
       $etd = $this->_helper->getFromFedora("pid", "etd");
       if ($etd) {
           if (!$this->_helper->access->allowedOnEtd("view metadata", $etd)) return false;
           $this->_helper->displayXml($etd->getMods());
       }
   }
   

}
?>
