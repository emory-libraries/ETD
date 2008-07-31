<?php

require_once("Emory/Service/Persis.php");

// FIXME: need better error handling here...  maybe a generic "service unavailable" error?

/**
 * customized version of Persistent ID service 
 *  - loads ETD configuration directly
 */
class Etd_Service_Persis extends Emory_Service_Persis {
  public function __construct() {
    $env_config = Zend_Registry::get('env-config');
    $config = new Zend_Config_Xml("../config/persis.xml", $env_config->mode);
    return parent::__construct($config->url, $config->username,
			       $config->password, $config->domain);
  }
}

