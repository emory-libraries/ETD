<?php

require_once("etd_mods.php");

class honors_mods extends etd_mods {


  // add etd-specific mods mappings
  protected function configure() {
    parent::configure();


    // override required fields - shares etd fields with these exceptions:
    // PQ researchfields are optional
    $this->makeOptional("researchfields");
    
    // Honors ETDs are not sent to PQ -- ignore these fields completely
    unset($this->required_fields["send to ProQuest"]);
    unset($this->required_fields["copyright"]);

  }

  private function makeOptional($field) {
    // copy field value into optional list
    $this->optional_fields[$field] = $this->required_fields[$field];
    // then remove from required list
    unset($this->required_fields[$field]);

    // now actually remove from the xml
    // NOTE: this *has* to be done for research fields because if it is
    // present and left blank, the xml is invalid
    if (isset($this->field)) $this->remove($field);
  }
    
    
  /* NOTE: functions for checking required and complete fields are
     inherited-- the logic is the same, and actual tests are based on
     the list of required fields. */

  /**
   * check if the record should  be sent to ProQuest-- never, for honors etds
   * @return boolean
   */
  public function submitToProquest() {
    return false;
  }


  /**
   * Override to remove reference to ProQuest
   * @param string $field field name
   * @return string label
   */
  public function fieldLabel($field) {
    switch ($field) {
    case "researchfields":
      return "research fields";
    default:
      return parent::fieldLabel($field);
    }
  }

}