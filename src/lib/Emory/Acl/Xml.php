<?php

/**
 *  ACL control list that uses an xml file for configuration
 *
 * @category EmoryZF
 * @package Emory_Acl
 */
class Emory_Acl_Xml extends Zend_Acl {

  public function __construct($filename = "../config/access.xml") {
    $config = simplexml_load_string(file_get_contents($filename));

    // define roles
    foreach ($config->roles->role as $role) {
      $inherits = array();
      if ($role{"inherits"}) {
  array_push($inherits, (string)$role{"inherits"});
      }
      $this->addRole(new Zend_Acl_Role((string)$role{"name"}), $inherits);
    }

    // define resources
    foreach ($config->resources->resource as $resource) {
      $this->add(new Zend_Acl_Resource((string)$resource{"name"}));
      $this->addSubresources($resource);
    }

    // add privileges to each role
    foreach ($config->roles->role as $role) {
      // allow
      foreach ($role->allow as $allow) {
  if ($allow == "") {
    if (isset($allow{"resource"}))
      $this->allow((string)$role{"name"}, (string)$allow{"resource"});	// anything on this resource
    else
      $this->allow((string)$role{"name"});	// anything anywhere
  }
  else
    $this->allow((string)$role{"name"}, (string)$allow{"resource"},
           explode(", ", (string)$allow));
      }
      // deny
      foreach ($role->deny as $deny) {
  if ($deny == "") {
    if (isset($deny{"resource"}))
      $this->deny((string)$role{"name"}, (string)$deny{"resource"});	// anything on this resource
    else
      $this->deny((string)$role{"name"});	// anything anywhere
  }
  else
    $this->deny((string)$role{"name"}, (string)$deny{"resource"},
           explode(", ", (string)$deny));
      }
    }
  }

  // recursive function to add resources that inherit from another resource
  protected function addSubresources(SimpleXMLElement $resource) {
    foreach ($resource->resource as $subresource) {
      $this->add(new Zend_Acl_Resource((string)$subresource{"name"}),
     (string)$resource{"name"});
      $this->addSubresources($subresource);
    }
  }

}
