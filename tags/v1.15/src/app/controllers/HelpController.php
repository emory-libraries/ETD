<?php
/**
 * @category Etd
 * @package Etd_Controllers
 */


class HelpController extends Etd_Controller_Action {
  protected $requires_fedora = false;
  protected $params;
  private $etd_pid;
  private $message;
  
  public function indexAction() {

    $this->view->title = "Help Request";
    // using jquery, jqueryui, and jquery form validation function
    $this->view->extra_scripts = array(
         "//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js",
         "//ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/jquery-ui.min.js",
         "//ajax.aspnetcdn.com/ajax/jquery.validate/1.9/jquery.validate.js",
    );
    $this->view->extra_css = array(
         "//ajax.googleapis.com/ajax/libs/jqueryui/1.7.2/themes/ui-lightness/jquery-ui.css",
    );

    
    // on GET, display edit form with default values
    if ($this->getRequest()->isGet()) {
      // if user is logged in, pre-populate fields we know
      if (isset($this->current_user)) {
        $this->view->fullname = $this->current_user->fullname;
        $this->view->email    = $this->current_user->netid . "@emory.edu";
        $user_etds = $this->current_user->getEtds();
        if ($user_etds != null) {
          $this->view->etd_link = $user_etds[0]->ark();
        }
      }

      if ($this->env != "production") {
        // use a configured debug email address when not in production
        $config = Zend_Registry::get('config');
        $this->view->to_email = $config->email->test;
      }

      return;
    } // end GET 

    if ($this->getRequest()->isPost()) {
      // on POST, process form data

      // populate submitted values in case form needs to be displayed
      $this->view->fullname = $this->_getParam("username", "");
      $this->view->email = $this->_getParam("email", "");
      $this->view->message = $this->_getParam("message", "");
      $this->view->subject = $this->_getParam("subject", "");
      $this->view->to_email = $this->_getParam("to_email", "");

      // check for invalid form
      if (! $this->valid_submission()) {
        $this->view->error = "There was a problem submitting your request. " 
          . "Please check that you have filled in all required fields.";
        return;
      }

      if ($this->send_email()) {
        $this->view->email_sent = true;        
      }

    }
  }
   
  // display success message with more information
  public function successAction() {
  	/**/
  }
  
  protected function send_email() {
    $rqst = $this->getRequest();
    if ($this->env != "production") {
      // use a configured debug email address when in development mode
      $to_email = $rqst->getParam("to_email", null);
      if (is_null($to_email)) {
        $config = Zend_Registry::get('config');
        $config->email->test;
      }
    } else {
      // I added the following so the config would get loaded and not have uninitilised value for the contact email
      // 02/16/2013 - M Prefer
      $config = Zend_Registry::get('config');
      $to_email = $config->contact->email;
    }
	   
    $etd_ark = $rqst->getParam("etd_link", "NO RECORD FOUND");
    $grad_date = $rqst->getParam("grad_date", "NOT SPECIFIED");

    $notify = new Zend_Mail();
    $notify->addTo($to_email);
    // set user submitting the form as from address
    $notify->setFrom($rqst->getParam("email"), $rqst->getParam("username"));
    // if user requested a copy, add them as cc
    if ($rqst->getParam("copy_me", False)) {
      $notify->addCc($rqst->getParam("email"), $rqst->getParam("username"));
    }
    $notify->setSubject("ETD Help: " . $rqst->getParam("subject"));
    $notify->setBodyText("Contact: " . $rqst->getParam("username") . "\n"
                         . "Email Address: " . $rqst->getParam("email") . "\n"
                         . "ETD: " . $etd_ark . "\n"
                         . "Expected Graduation Date: " . $grad_date . "\n\n"
                         . $rqst->getParam("message")
                         );

    // special handling to prevent sending email and allow inspecting message
    if ($this->env == "test") {
      return $notify;
    }
    try {
        $notify->send();
    } catch (Exception $exception) {
      $this->view->error = "There was an error sending your message. ["
        . $exception->getMessage() . "]";
      return false;
    }
    		
    // log help request details
    $this->logger->info("Help request sent - '" 
                        .  $rqst->getParam("subject") 
                        . "' from " . $rqst->getParam("username", "")
			. " <" . $rqst->getParam("email", "") . ">");

    // return notify to indicate success
    return $notify;
  }

  
  protected function valid_submission() {
    // check that all required fields are filled in
    $rqst = $this->getRequest();
    return (
            $rqst->getParam("username", null)!=null && 
            $rqst->getParam("email", null)!=null && 
            $rqst->getParam("message", null)!=null && 
            $rqst->getParam("subject", null)!=null 
            );
  }

}
