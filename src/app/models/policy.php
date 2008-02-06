<?php

require_once("models/foxmlDatastreamAbstract.php");

class XacmlPolicy extends foxmlDatastreamAbstract {
  
  const dslabel = "Policy";
  protected $namespace = "urn:oasis:names:tc:xacml:1.0:policy";
  protected $schema = "http://www.oasis-open.org/committees/download.php/915/cs-xacml-schema-policy-01.xsd";
  
  protected $xmlconfig;
  
  public function __construct($dom, $xpath = null) {
    // note: even though namespace is not used in xml, needs to be used here for xpath to work
    $this->addNamespace("x", $this->namespace);
    
    $this->configure();
    $config = $this->config($this->xmlconfig);

    parent::__construct($dom, $config, $xpath);
  }

  // define xml mappings (separate so it can be extended)
  protected function configure() {

    $this->xmlconfig =  array(
        "policyid" => array("xpath" => "@PolicyId"),
	"description" => array("xpath" => "x:Description"),
	// pid for the object that this policy applies to
	"pid" => array("xpath" => "x:Resources/x:Resource/x:ResourceMatch/x:AttributeValue"),
	"rules" => array("xpath" => "x:Rule", "is_series" => true, "class_name" => "PolicyRule"),
	);

    // specific etd rules that need to be accessible by name/type
    $etdrules = array("fedoraAdmin", "view", "etdadmin", "draft", "published", "deny_most");
    foreach ($etdrules as $ruleid) {
      $this->xmlconfig[$ruleid] = array("xpath" => "x:Rule[@RuleId='$ruleid']", "class_name" => "PolicyRule");
    }

  }


  public function addRule($name) {
    // FIXME: what is a good way to do this? how to manage the rules?
  }

  
  public function removeRule($id) {
    // fixme: should it not allow removing fedoraAdmin rule?
    $nodeList = $this->xpath->query("x:Rule[@RuleId='$id']");
    if ($nodeList->length == 1) {
      $rule = $nodeList->item(0);
      $this->domnode->removeChild($rule);
      $this->update();
    } else {
      trigger_error("Couldn't find rule '$id' to remove", E_USER_NOTICE);
    }
  }


  public static function getTemplate() {
    return file_get_contents("policy.xml", FILE_USE_INCLUDE_PATH);
  }
  
  public static function getFedoraTemplate(){
    return foxml::xmlDatastreamTemplate("POLICY", XacmlPolicy::dslabel,
					XacmlPolicy::getTemplate());
  }
  
  public function datastream_label() {
    return XacmlPolicy::dslabel;
  }

}

class PolicyRule extends XmlObject {

  protected $xmlconfig;
  
  public function __construct($xml, $xpath) {
    $this->configure();
    $config = $this->config($this->xmlconfig);
    parent::__construct($xml, $config, $xpath);
  }

  // define xml mappings (separate so it can be extended)
  protected function configure() {
    $this->xmlconfig =  array(
        "id" => array("xpath" => "@RuleId"),
	// note: only mapping things that currently need to be set/accessed
	
	"resources" => array("xpath" => "x:Target/x:Resources", "class_name" => "policyResource"),
	"condition" => array("xpath" => "x:Condition", "class_name" => "policyCondition"),
	);
  }

  
}

class policyResource extends XmlObject {
  // is actually at resources level
  
  protected $xmlconfig;
  public function __construct($xml, $xpath) {
    $this->configure();
    $config = $this->config($this->xmlconfig);
    parent::__construct($xml, $config, $xpath);
  }
  // define xml mappings 
  protected function configure() {
    $this->xmlconfig =  array(
        "datastreams" => array("xpath" => "x:Resource/x:ResourceMatch[x:ResourceAttributeDesignator/@AttributeId='urn:fedora:names:fedora:2.1:resource:datastream:id']/x:AttributeValue",
			       "is_series" => true),
	);
  }

  public function addDatastream($id) {
    if (!count($this->datastreams)) {
      trigger_error("Cannot add datastream '$id' because this resource has no datastreams", E_USER_WARNING);
      return;
    }

    // copy the first datastream resource match
    $nodelist = $this->xpath->query("x:Resource[x:ResourceMatch/x:ResourceAttributeDesignator/@AttributeId='urn:fedora:names:fedora:2.1:resource:datastream:id']", $this->domnode);
    if ($nodelist->length) {
      $newnode = $nodelist->item(0)->cloneNode(true);
      $this->domnode->appendChild($newnode);
      // update the XmlObject and then set the value
      $this->update();
      $this->datastreams[count($this->datastreams) - 1] = $id;
    }
    
  }
  
}

class policyCondition extends XmlObject {
  
  protected $xmlconfig;
  protected $userxpath;
  public function __construct($xml, $xpath) {
    $this->configure();
    $config = $this->config($this->xmlconfig);
    parent::__construct($xml, $config, $xpath);
  }

  // define xml mappings 
  protected function configure() {
    $this->userxpath = "x:Apply[contains(@FunctionId,'string-bag')][preceding-sibling::x:SubjectAttributeDesignator[contains(@AttributeId, 'loginId')]]/x:AttributeValue";
	

    $this->xmlconfig =  array(
			      // fairly specific to etd xacml - list of users (logins)
        "users" => array("xpath" => $this->userxpath, "is_series" => true),
	);
  }

  public function addUser($username) {
    if (!count($this->users)) {
      trigger_error("Cannot add user '$username' because this condition has no users", E_USER_WARNING);
      return;
    }

    // fixme: what should happen if user is already here ?
    
    $this->users->append($username);
    $this->update(); 
  }

  public function removeUser($username) {
    $nodelist = $this->xpath->query($this->userxpath . "[. = '$username']", $this->domnode);
    if ($nodelist->length == 1) {
      $node = $nodelist->item(0);
      $node->parentNode->removeChild($node);
      $this->update();
    } else {
      trigger_error("Cannot find user to be removed: '$username'", E_USER_NOTICE);
    }
  }
  
}



