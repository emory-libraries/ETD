<?php

class notifier {

  protected $mail;
  protected $view;

  public function __construct() {
    $this->mail = new Zend_Mail();
    $this->view = new Zend_View();
    $this->view->setBasePath("../app/views");
  }

  public function setBodyHtml($content) {
    $this->mail->setBodyHtml($content);
    $tagfilter = new Zend_Filter_StripTags();
    $this->mail->setBodyText($tagfilter->filter($content));
  }


  public function send() {
    $this->mail->send();
  }
  
}