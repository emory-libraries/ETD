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
      $this->mailaddBcc($config->email->etd->address, $config->email->etd->name);
    }
    $this->view->etd = $etd;
  }


  private function getEmailAddresses(etd $etd) {
    // author - send to both currnet & permanent addresses
    $name = $etd->authorInfo->mads->name->first . " " . $etd->authorInfo->mads->name->last;
    $this->to[$etd->authorInfo->mads->current->email] = $name;
    $this->to[$etd->authorInfo->mads->permanent->email] = $name;

    // advisor & committee  - look up in ESD
    $esd = new esdPersonObject();

    // advisor
    $person = $esd->findByUsername($etd->mods->advisor->id);
    if ($person)
      $this->cc[$person->email] = $person->fullname;
    else
    	trigger_error("Advisor (" . $etd->mods->advisor->id . ") not found in ESD", E_USER_NOTICE);
    // committee members
    foreach ($etd->mods->committee as $cm) {
      $person = $esd->findByUsername($cm->id);
      if ($person)
	$this->cc[$person->email] = $person->fullname;
      else
	trigger_error("Committee member (" . $cm->id . ") not found in ESD", E_USER_NOTICE);
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
}

