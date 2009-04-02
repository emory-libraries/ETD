<?php

require_once("Emory/notifier.php");


class HelpController extends Etd_Controller_Action {
  protected $requires_fedora = false;
  protected $params;
  private $etd_pid;
  private $message;
  
  public function indexAction() {
  	if(isset($this->current_user)) {
	  	$this->view->fullname = $this->current_user->fullname;
	  	$this->view->email    = $this->current_user->netid . "@emory.edu";
	  	$user_etds = $this->current_user->getEtds();
	  	if($user_etds!=null) {
	  		$this->view->etd_link = $user_etds[0]->ark();
  		} else {
	  		unset($this->view->etd_link);
	  	}
  	} else {
	  	unset($this->view->fullname);
	  	unset($this->view->email);
	  	unset($this->view->etd_link);
	}
	
	$config = Zend_Registry::get('config');
	$environment = Zend_Registry::get('env-config');
	if ($environment->mode != "production") {
		// use a configured debug email address when in development mode
		$this->view->list_addr = $config->email->test;
	}

	$this->view->title = "Help Request";
  }
   
  // display success message with more information
  public function successAction() {
  	/**/
  }
  
  public function submitAction() {
    	
  	$request = $this->getRequest();
  	
    if($this->verifyParms()) {
	  	$notify = new Zend_Mail();
	  	
	  	
    	$config = Zend_Registry::get('config');
	    $environment = Zend_Registry::get('env-config');
	    if ($environment->mode != "production") {
	    	// use a configured debug email address when in development mode
			$list_addr = $request->getParam("list_addr", $config->email->test);
		} else {
			$list_addr = $config->email->etd->address;
	    }
	   
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

	  	try {
		    $notify->send();
  		} catch (Exception $exception) {
			$this->view->errorMessage = "There was an error sending your message. [" . $exception->getMessage() . "]";
			$this->view->submittedUsername = $request->getParam("username", "");
			$this->view->submittedEmail = $request->getParam("email", "");
			$this->view->submittedMessage = $request->getParam("message", "");
			$this->view->submittedSubject = $request->getParam("subject", "");
			$this->_helper->viewRenderer->setScriptAction("index");   	
  		}
    		
	    // send notification only if submit succeeded 
	    $this->_helper->flashMessenger->addMessage("Help email sent.");
	    $messageToLog = "";
  		if(!isset($this->current_user)) {
  			$messageToLog = $messageToLog . " from " . $this->view->fullname . " <" . $this->view->email . "emory.edu>";
  		}
   	    $this->logger->info($messageToLog);
	    
	    // forward to success message
	    $this->_helper->redirector->gotoRoute(array("controller" => "help", "action" => "success"), "", true);
    	$this->_forward("success");
  	} else {
		$this->view->errorMessage = "Validation Error: there was a problem submitting your message for help.  Ensure that the Name, email, subject and message fields are populated before clicking submit.";
		$this->view->submittedUsername = $request->getParam("username", "");
		$this->view->submittedEmail = $request->getParam("email", "");
		$this->view->submittedMessage = $request->getParam("message", "");
		$this->view->submittedSubject = $request->getParam("subject", "");
		$this->_helper->viewRenderer->setScriptAction("index");   	
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