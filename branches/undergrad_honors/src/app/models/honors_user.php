
<?php

require_once("user.php");

class honors_user extends user {

  public function __construct($arg = null, etd $parent = null) {
    parent::__construct($arg, $parent);

    // override required fields - only current and permanent email, not address
    $this->required_fields = array("email", "permanent email");
  }

  /* functions for checking required and complete fields are
     inherited-- the logic is the same, and actual tests are based on
     the list of required fields. */
  
}
