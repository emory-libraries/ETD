<?php
/**
 * utility for sending notification emails
 * - if 'environment' is set to 'test' in the Zend Registry, email is not actually sent
 * @uses Zend_Mail for sending email
 * @uses Zend_View for email templates
 *
 * @category EmoryZF
 */
class notifier {

  protected $mail;
  protected $view;

  protected $env;

  /**
   * initialize notifier
   */
  public function __construct() {
    if (Zend_Registry::isRegistered('environment'))
      $this->env = Zend_Registry::get('environment');
    else $this->env = "";
    
    $this->mail = new Zend_Mail();
    $this->view = new Zend_View();

    if ($this->env == "test")	   // set base path relative to test directory
      $this->view->setBasePath("../../src/app/views");	
    else			   // default base path relative for normal setup
      $this->view->setBasePath("../app/views");
  }

  /**
   * set the body of the email, in html format; strips tags and sets plain-text content version also
   * @param string $content html-formatted email text
   * @uses Zend_Filter_StripTags
   */
  public function setBodyHtml($content) {
    $this->mail->setBodyHtml($content);
    $tagfilter = new Zend_Filter_StripTags();
    $this->mail->setBodyText($tagfilter->filter($content));
  }

  /**
   * send the email
   * (if environment is 'test', does nothing')
   */
  public function send() {
    // don't actually send the email when testing
    if ($this->env == "test")  return;

    // NOTE: Would be nice to enhance this at some point --
    // store the email somewhere for inspection in test mode, ... ?
    
    $this->mail->send();
  }
  
}