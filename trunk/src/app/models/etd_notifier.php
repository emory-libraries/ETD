<?php

include("Emory/notifier.php");

class etd_notifier extends notifier {

  public function __construct(etd $etd) {
    parent::__construct();

    $environment = Zend_Registry::get('env-config');
    $this->view->environment = $environment->mode;
    $config = Zend_Registry::get('config');
    
    $this->mail->setFrom($config->email->etd->address,
			 $config->email->etd->name);
    if ($environment->mode != "production") {
      // use a configured debug email address when in development mode
      $this->mail->addTo($config->email->test);
    }

    // 	      also: add to template development output with email addresses
    // 		    that would be used in production?...

    // fixme: need a way to look up author & committee email addresses (from ESD perhaps?)
    $this->view->etd = $etd;

  }

  public function submission() {
    // default recipients ?
    $this->mail->setSubject("Your ETD Has Been Successfully Submitted");
    $this->setBodyHtml($this->view->render("email/submission.phtml"));
    $this->send();
  }

  public function approval() {
    // default recipients ?
    $this->mail->setSubject("Your ETD Has Been Approved By the Graduate School");
    $this->setBodyHtml($this->view->render("email/approval.phtml"));
    $this->send();
  }

  public function publication() {
    // NOT default recipients - send to author's permanent address
    $this->mail->setSubject("Your ETD Has Been Published in the Emory Repository");
    $this->setBodyHtml($this->view->render("email/publication.phtml"));
    $this->send();
    
  }
}

/* notes
   make this an etd_notifier
  
   add functions for the different emails that need to get sent-- they
   would set subject, template

   $notify = new etd_notifier();
   $notify->send_submission();
   

 */