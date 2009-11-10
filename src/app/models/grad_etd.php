<?php

/**
 * Placeholder for now; main etd is effectively grad etd.
 * Extending here to conditionally set grad school collection membership.
 *
 * @category Etd
 * @package Etd_Models
 * @subpackage Etd
 */

require_once("etd.php");

class grad_etd extends etd {

  public function __construct($arg = null) {
    parent::__construct($arg);
    
    // all new grad_etd objects must be added to grad school collection object
    if ($this->init_mode == "template") {
      $config = Zend_Registry::get('config');
      $this->rels_ext->addRelationToResource("rel:isMemberOfCollection",
					     $config->collections->grad_school);
    }
  }
}