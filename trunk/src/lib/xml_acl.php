<?php

class Xml_Acl extends Zend_Acl {

    public function __construct($filename = "../config/access.xml") {
      //      $config = simplexml_load_file($filename);
      $config = simplexml_load_string(file_get_contents($filename));

    //roles
    foreach ($config->roles->role as $role) {
      $inherits = array();
      if ($role{"inherits"}) {
	array_push($inherits, (string)$role{"inherits"});
      }
      
      $this->addRole(new Zend_Acl_Role((string)$role{"name"}), $inherits);
    }
    
    //resources
    foreach ($config->resources->resource as $resource) {
      $this->add(new Zend_Acl_Resource((string)$resource{"name"}));
      foreach ($resource->resource as $subresource) {
	$this->add(new Zend_Acl_Resource((string)$subresource{"name"}),
		  (string)$resource{"name"});
      }
    }

    //privileges by role
    foreach ($config->roles->role as $role) {
      foreach ($role->allow as $allow) {
	$this->allow((string)$role{"name"}, (string)$allow{"resource"},
		     explode(", ", (string)$allow));
      }
      // fixme: probably should handle deny also 
    }
  }

  
}


?>