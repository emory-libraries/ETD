<?php

require_once("Emory/notifier.php");


class HelpController extends Etd_Controller_Action {
  protected $requires_fedora = false;
  protected $params;
  private $etd_pid;
  private $message;
  
  public function indexAction() {
  	/**/
  }
   
  // display success message with more information
  public function successAction() {
  }
  
  public function submitAction() {
  	if($this->verifyParms()) {
	  	$notify = new Zend_Mail();
	  	
	    $config = Zend_Registry::get('config');
	  	//$list_addr = $config->email->etd->address
	    //$list_addr = "etd-help@listserv.cc.emory.edu";
	  	$list_addr = "rwrober@emory.edu";
	  	
	  	$etd_ark = isset($this->$_GET['etd_link'])?($this->$_GET['etd_link']):"NOT ";
	  	
	  	$notify->addTo($list_addr);
	  	$notify->setFrom(array("netid", $this->$_GET['email']), $this->$_GET['username']);
	  	$notify->setSubject("Automated ETD Help Request");
    	$tagfilter = new Zend_Filter_StripTags();
	  	$notify->setBodyText(
	  		  "Contact: " . $this->$_GET['username'] . "\n"
	  		. "Email Address: " . $this->$_GET['email'] . "\n"
	  		. "ETD: " . $etd_ark . "\n"
	  		. "Expected Graduation Date: " . $this->$_GET['grad_date'] . "\n\n"
	  		. $tagfilter->filter($this->$_GET['message'])
	  		);
	    $notify->send();
    		
	    // send notification only if submit succeeded 
	    $this->_helper->flashMessenger->addMessage("Help email sent.");
	    
	    // forward to success message
	    $this->_helper->redirector->gotoRoute(array("controller" => "help", "action" => "success"), "", true);
    	$this->_forward("success");
  	} else {
      $this->logger->err("Problem submitting your message to the help list");
      $this->_helper->flashMessenger->addMessage("Validation Error: there was a problem submitting your message for help.");
      
      // Validation Errors
//      $this->_helper->flashMessenger->addMessage("Validation Error: ");
      
      /*
       * TODO: Should we suggest manually submit with mailto:  by redirecting to failure page within controller?
       */
    	$this->_forward("index");
  	}
  }
  
  protected function verifyParms() {
/*  	
    return (
    		   (isset($this->$_GET['etd_link']))
    		&& (isset($this->$_GET['username']))
    		&& (isset($this->$_GET['email']))
    		&& (isset($this->$_GET['grad_date']))
    		&& (isset($this->$_GET['message']))
    		);
*/
  	return true;
  }

}