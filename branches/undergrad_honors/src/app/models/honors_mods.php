<?php

require_once("etd_mods.php");

class honors_mods extends etd_mods {


  // add etd-specific mods mappings
  protected function configure() {
    parent::configure();


    // override required fields - shares etd fields with these exceptions:
    // PQ researchfields are optional
    unset($this->required_fields["researchfields"]);
    // Honors ETDs are not sent to PQ
    unset($this->required_fields["send to ProQuest"]);
    unset($this->required_fields["copyright"]);
  }
    
  /* NOTE: functions for checking required and complete fields are
     inherited-- the logic is the same, and actual tests are based on
     the list of required fields. */

  /**
   * check if the record should  be sent to ProQuest-- never, for honors etds
   * @return boolean
   */
  function submitToProquest() {
    return false;
  }

}