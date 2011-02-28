<?php
/**
 * @category Etd
 * @package Etd_Models
 */

include("Emory/notifier.php");

/**
 * notification class for sending etd-related emails
 */
class etd_notifier extends notifier {

  /**
   * @var array $to addresses email will be sent to
   * @access private
   */
  private $to;
  /**
   * @var array $cc addresses to be copied on the email
   * @access private
   */
  private $cc;
  /**
   * @var array $send_to who should be included in constructing to and cc
   * @access private
   */
  private $send_to;

  /**
   * create an etd notification object in order to send out an
   * ETD-related email; recipients pulled from etd object
   * 
   * @param etd $etd
   */
  public function __construct(etd $etd) {
    parent::__construct();
    $this->to = array();
    $this->cc = array();
    
    $this->view->etd = $etd;

  }

  /**
   * set the recipients on the mail object
   * @access private
   * @param string $who emails to include, options are:
   *   - author : send to both author email addresses (current and permanent)
   *   - author-permanent : send only to author's permanent address
   *   - permanent : send to author's permanent address and to committee
   *   - all : (default) send to both author's email addresses and to committee
   */
  private function setRecipients($who = "all") {

    // configure who should receive this email
    switch ($who) {
    case "author":	// both author email addresses
      $this->send_to = array("author", "author_permanent"); break;
    case "author-permanent": // only send to author's permanent address
      $this->send_to = array("author_permanent"); break;
    case "permanent":	// author's permanent address and committee
      $this->send_to = array("author_permanent", "committee"); break;
    case "patent_info":
      $this->send_to = array("ott", "etd-admin"); break;
    case "all":
    default:
      // by default, send to all - author (emory & permanent addresses), and committee
      $this->send_to = array("author", "author_permanent", "committee");
    }

    $environment = Zend_Registry::get('env-config');
    $this->view->environment = $environment->mode;
    $config = Zend_Registry::get('config');
    // contact information - where questions should be directed
    $this->view->contact = $config->contact;
    
    $this->mail->setFrom($config->email->etd->address,
			 $config->email->etd->name);
    $this->mail->setReplyTo($config->contact->email,
			 $config->contact->name);
if ($environment->mode != "production") {
      // use a configured debug email address when in development mode
      $this->mail->addTo($config->email->test);

      // retrieve & display email addresses that would be used in production
      $this->getEmailAddresses($this->view->etd);
    } else {
      // add author & committee email addresses from ESD 
      $this->getEmailAddresses($this->view->etd);
      foreach ($this->to as $email => $name) $this->mail->addTo($email, $name);
      foreach ($this->cc as $email => $name) $this->mail->addCc($email, $name);
      $this->mail->addBcc($config->email->etd->address, $config->email->etd->name);
    }

  }

  /**
   * get requested email addresses from the ETD object (author [emory/permanent],
   * committee; inclusion determined by send_to array) and add to recipient variables 
   * @access private
   * @param etd $etd
   */
  private function getEmailAddresses(etd $etd) {
    // author - send to both current & permanent addresses
    if (isset($etd->authorInfo)) {
      $name = $etd->authorInfo->mads->name->first . " " . $etd->authorInfo->mads->name->last;
      if (in_array("author", $this->send_to)) {
	if (isset($etd->authorInfo->mads->current->email) && ($etd->authorInfo->mads->current->email != '')) 
	  $this->to[$etd->authorInfo->mads->current->email] = $name;
      }
      if (in_array("author_permanent", $this->send_to)) {
	$this->to[$etd->authorInfo->mads->permanent->email] = $name;
      }
    }

    // only include committee members if sending to all
    if (in_array("committee", $this->send_to)) {
      // committee chair & members  - look up in ESD
      $esd = new esdPersonObject();
      
      foreach (array_merge($etd->mods->chair, $etd->mods->committee) as $committee) {
	// skip former faculty, as we can't rely on any email address ESD has for them
	if (preg_match("/^esdid/", $committee->id)) continue;
	$person = $esd->findByUsername($committee->id);
	if ($person && $person->email != "")
	  $this->cc[$person->email] = $person->fullname;
	else
	  trigger_error("Committee member/chair (" . $committee->id . ") not found in ESD", E_USER_NOTICE);
      }
    }

    // ott and etd-admin recipient email info must be pulled from config file
    if (in_array("ott", $this->send_to) || in_array("etd-admin", $this->send_to)) {
      $config = Zend_Registry::get('config');
      if (in_array("etd-admin", $this->send_to)) {
	$this->to[$config->email->etd->address] = $config->email->etd->name;
      }
      if (in_array("ott", $this->send_to)) {
	$this->to[$config->email->ott->address] = $config->email->ott->name;
      }
    }
    
    
    // store in the view for debugging output when in development mode
    $this->view->to = $this->to;
    $this->view->cc = $this->cc;
  }

  /* Note: returning "to" addresses so flash message can display more information to user */
  

  /**
   * email to be sent when an ETD is successfully submitted
   * @return array of addresses email was sent to
   */
  public function submission() {
    // default recipients ?
    $this->setRecipients();
    $this->mail->setSubject("Your ETD Has Been Successfully Submitted");
    $this->setBodyHtml($this->view->render("email/submission.phtml"));
    $this->send();
    return $this->to;
  }

  /**
   * email to be sent when an ETD is approved by the Dean
   * @return array of addresses email was sent to
   */
  public function approval() {
    // default recipients ?
    $this->setRecipients();
    $this->mail->setSubject("Your ETD Has Been Approved");
    $this->setBodyHtml($this->view->render("email/approval.phtml"));
    $this->send();
    return $this->to;
  }

  /**
   * email to be sent when an ETD is published in the repository
   * @return array of addresses email was sent to
   */
  public function publication() {
    // NOT default recipients - send to author's permanent address
    $this->setRecipients();
    $this->mail->setSubject("Your ETD Has Been Published in the Emory Repository");
    $this->setBodyHtml($this->view->render("email/publication.phtml"));
    $this->send();
    return $this->to;
  }

  /**
   * email to be sent 60 days before embargo expires
   * @return array of addresses email was sent to
   */
  public function embargo_expiration() {
    $this->setRecipients("permanent");	// send to author's permanent address + committee
    $this->mail->setSubject("Your ETD Access Restriction will Expire in 60 Days");
    $this->setBodyHtml($this->view->render("email/embargo_expiration.phtml"));
    $this->send();
    return $this->to;
  }

  /**
   * quick-fix email: apologizing for mistaken embargo expirations getting sent out
   * @return array of addresses email was sent to
   */
  public function embargo_expiration_error() {
    $this->setRecipients();
    $this->mail->setSubject("Please Disregard Automated Message from ETD system sent out today");
    $this->setBodyHtml($this->view->render("email/embargo_expiration_error.phtml"));
    $this->send();
    return $this->to;
  }

  /**
   * email to be sent on the actual day when an embargo expires
   * @return array of addresses email was sent to
   */
  public function embargo_end() {
    $this->setRecipients("permanent");	// send to author's permanent address + committee
    $this->mail->setSubject("Your ETD Access Restriction Expires Today");
    $this->setBodyHtml($this->view->render("email/embargo_end.phtml"));
    $this->send();
    return $this->to;
  }

  /**
   * email sent when Graduate School administrator requests changes;
   * template includes text input by administrator from a website form.
   * @param string $subject subject for the email
   * @param string $text email content to include in the output
   * @return array of addresses email was sent to
   */
  public function request_changes($subject, $text) {
    // don't send to committee
    $this->setRecipients("author");
    $this->mail->setSubject($subject);
    $this->view->text = $text;
    $this->setBodyHtml($this->view->render("email/request_changes.phtml"));
    $this->send();
    return $this->to;
  }


  /**
   * send an email to etd administrator & OTT to advise patent concerns
   * @return array of addresses email was sent to
   */
  public function patent_concerns() {
    $this->setRecipients("patent_info");

    $esd = new esdPersonObject();
    $author = $esd->findByUsername($this->view->etd->owner);
    $this->view->author_email = $author->email;

    $this->mail->setSubject("ETD author has patent concerns");
    $this->setBodyHtml($this->view->render("email/patent_concerns.phtml"));
    $this->send();
    return $this->to;
  }
}

class etdSet_notifier extends notifier {

  /**
   * create an notification object in order to send out an
   * email related to a group of etds
   * 
   * @param EtdSet $etdSet
   */
  public function __construct(EtdSet $etdSet) {
    parent::__construct();
    $this->to = array();
    $this->cc = array();
    
    $this->view->etdSet = $etdSet;
    $this->setRecipients();
  }

  /**
   * set the recipients on the mail object
   * @access private
   */
  private function setRecipients() {
    $environment = Zend_Registry::get('env-config');
    $this->view->environment = $environment->mode;
    $config = Zend_Registry::get('config');
    // contact information - where questions should be directed
    $this->view->contact = $config->contact;
    
    $this->mail->setFrom($config->email->etd->address,
			 $config->email->etd->name);
    $this->mail->setReplyTo($config->contact->email,
			 $config->contact->name);
    if ($environment->mode != "production") {
      // use a configured debug email address when in development mode
      $this->mail->addTo($config->email->test);
    } else {
      // send to ETD admin
      $this->mail->addTo($config->email->etd->address);
    }
  }


  /**
   * send a list of embargoes expiring in seven days
   * @return array of addresses email was sent to
   */
  public function embargoes_expiring_oneweek(){
    $this->mail->setSubject("ETD embargoes expiring in 7 days");
    $this->setBodyHtml($this->view->render("email/embargoes_expiring_oneweek.phtml"));
    $this->send();
    return $this->to;
  }
  
}

