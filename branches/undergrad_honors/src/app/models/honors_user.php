
<?php

require_once("user.php");

class honors_user extends user {

public function checkRequired() {
    $missing = array();

    // permanent non-emory email address
    if ($this->mads->permanent->email == "") {
      $missing[] = "permanent (non-emory) email address";
    }

	 // current email address
    if ($this->mads->current->email == "") {
      $missing[] = "current email address";
    }


    return $missing;
  }

}
