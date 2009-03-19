<?php

require_once("Emory/notifier.php");


class HelpController extends Etd_Controller_Action {
  protected $requires_fedora = false;
  protected $params;
  private $etd_pid;
  private $message;
  
  public function indexAction() {
  	if(isset($this->current_user)) {
	  	$this->view->fullname = $this->current_user;
	  	$this->view->netid    = $this->current_user->netid;
	  	$this->view->etd_link = "";
  	} else {
	  	unset($this->view->fullname);
	  	unset($this->view->netid);
	  	unset($this->view->etd_link);
	}
  }
   
  // display success message with more information
  public function successAction() {
  	/**/
  }
  
  public function submitAction() {
  	if($this->verifyParms()) {
	  	$notify = new Zend_Mail();
	  	
	    $config = Zend_Registry::get('config');
	    $environment = Zend_Registry::get('env-config');
	    if ($environment->mode != "production") {
	    	// use a configured debug email address when in development mode
			$list_addr = $config->email->test;
		} else {
			$list_addr = $config->email->etd->address;
	    }
	   
    	$request = $this->getRequest();
    	$etd_ark = $request->getParam("etd_link", null)!=null?($request->getParam("etd_link")):"NOT ATTAINABLE";
    	$grad_date = $request->getParam("grad_date", null)!=null?($request->getParam("grad_date")):"NOT SPECIFIED";
	  	
	  	$notify->addTo($list_addr);
	  	$notify->setFrom($request->getParam("email"), $request->getParam("username"));
	  	$copy_netid = $request->getParam("copy_netid", null);
	  	if($copy_netid!=null) {
	  		$notify->addCc($request->getParam("email"), $request->getParam("username"));
	  	}
	  	$notify->setSubject("ETD Help: " . $request->getParam("subject"));
    	$tagfilter = new Zend_Filter_StripTags();
	  	$notify->setBodyText(
	  		  "Contact: " . $request->getParam("username") . "\n"
	  		. "Email Address: " . $request->getParam("email") . "\n"
	  		. "ETD: " . $etd_ark . "\n"
	  		. "Expected Graduation Date: " . $grad_date . "\n\n"
	  		. $tagfilter->filter($request->getParam("message"))
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
// TODO: would like to forward these back to the index page so they can be filled in on the form
//		$request->getParam("username", null)!=null && 
//  		$request->getParam("email", null)!=null && 
//  		$request->getParam("message", null)!=null && 
//  		$request->getParam("subject", null)!=null 
		$this->_forward("index");
  	}
  }
  
  protected function verifyParms() {
    $request = $this->getRequest();
  	return (
  		$request->getParam("username", null)!=null && 
  		$request->getParam("email", null)!=null && 
  		$request->getParam("message", null)!=null && 
  		$request->getParam("subject", null)!=null 
  		);
  }

}