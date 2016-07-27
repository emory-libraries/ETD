<?php

/**
 * Use a very simple xml file format for authentication.
 * xml file should be structured like this:
 *   <users>
 *     <user id="username" name="User Name" password="md5sumpass"/>
 *   </users>
 *
 *
 * @category EmoryZF
 * @package Emory_Auth
 */

class Emory_Auth_Adapter_Xml implements Zend_Auth_Adapter_Interface {

  protected $username;
  protected $password;
  protected $xml;

  /**
   * initialize adapter
   * @param string $xml authentication xml file as string
   * @param string $username user to authenticate
   * @param string $password 
   */
  public function __construct($xml, $username, $password) {
    $this->xml = $xml;
    $this->username = $username;
    $this->password = md5($password);
  }

  /**
   * authenticate user against xml file
   */
  public function authenticate() {

    // hide any errors or warnings
    $sxml = simplexml_load_string($this->xml, null, LIBXML_NOERROR | LIBXML_NOWARNING);
    // minimal error checking
    if (!$sxml) {
      throw new Zend_Auth_Adapter_Exception("Could not load password xml file");
    }

    // look for user by username in the xml
    $nodes = $sxml->xpath("/users/user[@id='" . $this->username ."']");
    if (count($nodes) == 0) { 	// no match
      return new Zend_Auth_Result(Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND, $this->username);
    } elseif (count($nodes) > 1) { 	// too many matches (should only happen if xml is broken)
      return new Zend_Auth_Result(Zend_Auth_Result::FAILURE_IDENTITY_AMBIGUOUS, $this->username);
    } else {	// one and only match
      if ($nodes[0]["password"] == $this->password)
	return new Zend_Auth_Result(Zend_Auth_Result::SUCCESS, $this->username);
      else
	return new Zend_Auth_Result(Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID, $this->username);
    }
  }
  
}