<?php

include("Emory/notifier.php");

class etd_notifier extends notifier {

  private $to;
  private $cc;
  
  public function __construct(etd $etd) {
    parent::__construct();
    $this->to = array();
    $this->cc = array();

    $environment = Zend_Registry::get('env-config');
    $this->view->environment = $environment->mode;
    $config = Zend_Registry::get('config');
    
    $this->mail->setFrom($config->email->etd->address,
			 $config->email->etd->name);
    if ($environment->mode != "production") {
      // use a configured debug email address when in development mode
      $this->mail->addTo($config->email->test);

      // retrieve & display email addresses that would be used in production
      $this->getEmailAddresses($etd);
    } else {
      // add author & committee email addresses from ESD 
      $this->getEmailAddresses($etd);
      foreach ($this->to as $email => $name) $this->mail->addTo($email, $name);
      foreach ($this->cc as $email => $name) $this->mail->addCc($email, $name);
      $this->mail->addBcc($config->email->etd->address, $config->email->etd->name);
    }
    $this->view->etd = $etd;
  }


  private function getEmailAddresses(etd $etd) {
    // author - send to both current & permanent addresses
    if (isset($etd->authorInfo)) {
      $name = $etd->authorInfo->mads->name->first . " " . $etd->authorInfo->mads->name->last;
      if (isset($etd->authorInfo->mads->current->email) && ($etd->authorInfo->mads->current->email != '')) 
	$this->to[$etd->authorInfo->mads->current->email] = $name;
      $this->to[$etd->authorInfo->mads->permanent->email] = $name;
    }
    // committee chair & members  - look up in ESD
    $esd = new esdPersonObject();

    foreach (array_merge($etd->mods->chair, $etd->mods->committee) as $committee) {
      $person = $esd->findByUsername($committee->id);
      if ($person)
	$this->cc[$person->email] = $person->fullname;
      else
	trigger_error("Committee member/chair (" . $committee->id . ") not found in ESD", E_USER_NOTICE);
    }

    // store in the view for debugging output when in development mode
    $this->view->to = $this->to;
    $this->view->cc = $this->cc;
  }

  /* Note: returning "to" addresses so flash message can display more information to user */

  public function submission() {
    // default recipients ?
    $this->mail->setSubject("Your ETD Has Been Successfully Submitted");
    $this->setBodyHtml($this->view->render("email/submission.phtml"));
    $this->send();
    return $this->to;
  }

  public function approval() {
    // default recipients ?
    $this->mail->setSubject("Your ETD Has Been Approved By the Graduate School");
    $this->setBodyHtml($this->view->render("email/approval.phtml"));
    $this->send();
    return $this->to;
  }

  public function publication() {
    // NOT default recipients - send to author's permanent address
    $this->mail->setSubject("Your ETD Has Been Published in the Emory Repository");
    $this->setBodyHtml($this->view->render("email/publication.phtml"));
    $this->send();
    return $this->to;
  }

  public function embargo_expiration() {
    $this->mail->setSubject("Your ETD Access Restriction will Expire in 60 Days");
    $this->setBodyHtml($this->view->render("email/embargo_expiration.phtml"));
    $this->send();
    return $this->to;
  }
  
  public function embargo_expiration_error() {
    $this->mail->setSubject("Please Disregard Automated Message from ETD system sent out today");
    $this->setBodyHtml($this->view->render("email/embargo_expiration_error.phtml"));
    $this->send();
    return $this->to;
  }

  
}
