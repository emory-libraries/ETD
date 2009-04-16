<?php

require_once("user.php");
require_once("honors_etd.php");

class honors_user extends user {

  public function __construct($arg = null, etd $parent = null) {
    parent::__construct($arg, $parent);

    // override required fields - only current and permanent email, not address
    $this->required_fields = array("email", "permanent email");
  }

  protected function configure() {
    parent::configure();
    // use honors_etd instead of default etd class
    $this->relconfig["etd"]["class_name"] = "honors_etd";
  }



  /* functions for checking required and complete fields are
     inherited-- the logic is the same, and actual tests are based on
     the list of required fields. */
  
}
