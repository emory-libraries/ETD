<?php

require_once("etd.php");

class honors_etd extends etd {

  public function __construct($arg = null) {
    parent::__construct($arg);

    // all new honors_etd object must be added to honors collection object
    if ($this->init_mode == "template") {
      $config = Zend_Registry::get('config');
      $this->rels_ext->addRelationToResource("rel:isMemberOf",
					     $config->honors_collection);
    }
  }

}