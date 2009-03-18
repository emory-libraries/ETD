<?php

require_once("etd.php");
require_once("honors_mods.php");
require_once("honors_user.php");

class honors_etd extends etd {


  public function __construct($arg = null) {
    parent::__construct($arg);
    
    // variant ACL resource base id (has variants by status, same as generic etd)
    $this->acl_id = "honors etd";

    // all new honors_etd object must be added to honors collection object
    if ($this->init_mode == "template") {
      $config = Zend_Registry::get('config');
      $this->rels_ext->addRelationToResource("rel:isMemberOf",
					     $config->honors_collection);

      // NOTE: PQ research field is marked as optional in the honors
      // mods class, but the actual field has to be removed because if
      // it is present and left blank, the MODS is invalid
      if (count($this->mods->researchfields)) $this->mods->remove("researchfields");
    }

    // for honors etds, admin agent is the college
    $this->admin_agent = "Emory College";
  }


  // minor customization to default etd configuration
  protected function configure() {
    parent::configure();

    // customized datastreams
    $this->xmlconfig["mods"]["class_name"] = "honors_mods";

    // customized related objects
    $this->relconfig["authorInfo"]["class_name"] = "honors_user";
  }
  

  

}