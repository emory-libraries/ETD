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
	"pid" => array("xpath" => "x:Target/x:Resources/x:Resource/x:ResourceMatch/x:AttributeValue[following-sibling::x:ResourceAttributeDesignator[@AttributeId='urn:fedora:names:fedora:2.1:resource:object:pid']]"),
	"rules" => array("xpath" => "x:Rule", "is_series" => true, "class_name" => "PolicyRule"),
	);

    // specific etd rules that need to be accessible by name/type
    $etdrules = array("fedoraAdmin", "view", "etdadmin", "draft", "published", "deny_most");
    foreach ($etdrules as $ruleid) {
      $this->xmlconfig[$ruleid] = array("xpath" => "x:Rule[@RuleId='$ruleid']", "class_name" => "PolicyRule");
    }

  }


  public function addRule($name) {
    $dom = new DOMDocument();
    switch ($name) {
    case "view": 	$xml = EtdXacmlRules::view; break;
    case "etdadmin":	$xml = EtdXacmlRules::etdadmin; break;
    case "draft":	$xml = EtdXacmlRules::draft; break;
    case "published":	$xml = EtdXacmlRules::published; break;
    default:
      trigger_error("Rule '$name' unknown - cannot add to policy", E_USER_WARNING); return;
    }
    $dom->loadXML($xml);

    $newrule = $this->dom->importNode($dom->documentElement, true);
    $this->domnode->insertBefore($newrule, $this->map{"deny_most"}->domnode);

    $this->update();
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
    $xml = new DOMDocument();
    $xml->loadXML(file_get_contents("policy.xml", FILE_USE_INCLUDE_PATH));
    $policy = new XacmlPolicy($xml);

    // starts with a bare-bones xacml  (fedoraAdmin rule & deny-most rule)
    // add the appropriate rules for a new etd
    $policy->addRule("view");
    $policy->addRule("etdadmin");
    $policy->addRule("draft");
    
    return $policy->saveXML();
  }
  
  public static function getFedoraTemplate(){
    return foxml::xmlDatastreamTemplate("POLICY", XacmlPolicy::dslabel,
					XacmlPolicy::getTemplate(), "A", "false");
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

	// single user (draft rule)
	//	"user" => array("xpath" => ".[@FunctionId='urn:oasis:names:tc:xacml:1.0:function:string-is-in']/x:AttributeValue[following-sibling::x:SubjectAttributeDesignator[@AttributeId='urn:fedora:names:fedora:2.1:subject:loginId']]"),
	"user" => array("xpath" => "x:AttributeValue[following-sibling::x:SubjectAttributeDesignator[@AttributeId='urn:fedora:names:fedora:2.1:subject:loginId'] and parent::x:Condition[@FunctionId='urn:oasis:names:tc:xacml:1.0:function:string-is-in']]"),
	
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



class EtdXacmlRules {
  /* namespace needs to be explicit so it will match main policy record
     and xpath queries will find these rules
  */
  
const view = '<Rule xmlns="urn:oasis:names:tc:xacml:1.0:policy" RuleId="view" Effect="Permit">
 <!-- Allow author, committee members, departmental staff, and
	etd admin to view everything (mods, dc, xhtml, premis, rels-ext)  -->
    <Target>
     <Subjects>
        <AnySubject/>
      </Subjects>
      <Resources>

        <Resource>
         <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">DC</AttributeValue>
             <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                 DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>


        <Resource>
         <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">MODS</AttributeValue>
             <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                 DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>

        <Resource>
         <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">XHTML</AttributeValue>
             <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                 DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>


    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">PREMIS</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>

    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">RELS-EXT</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>
      </Resources>
      <Actions>
        <Action>
          <ActionMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">urn:fedora:names:fedora:2.1:action:id-getDatastreamDissemination</AttributeValue>
            <ActionAttributeDesignator DataType="http://www.w3.org/2001/XMLSchema#string" AttributeId="urn:fedora:names:fedora:2.1:action:id"/>
          </ActionMatch>
        </Action>
      </Actions>
    </Target>
	
    <!-- author and committee by username -->
    <Condition FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-at-least-one-member-of">
        <SubjectAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:subject:loginId" DataType="http://www.w3.org/2001/XMLSchema#string"/>
        <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-bag">
          <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">author</AttributeValue>
        </Apply>
    </Condition>
  </Rule>
';



const etdadmin = '<Rule  xmlns="urn:oasis:names:tc:xacml:1.0:policy" RuleId="etdadmin" Effect="Permit">
   <!-- Allow admin to modify history and status (premis, rels-ext)  -->
    <Target>
     <Subjects>
        <AnySubject/>
      </Subjects>
      <Resources>

    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">PREMIS</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>

    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">RELS-EXT</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>
      </Resources>
      <Actions>
        <Action>
          <ActionMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">urn:fedora:names:fedora:2.1:action:id-modifyDatastreamByValue</AttributeValue>
            <ActionAttributeDesignator DataType="http://www.w3.org/2001/XMLSchema#string" AttributeId="urn:fedora:names:fedora:2.1:action:id"/>
          </ActionMatch>
        </Action>
      </Actions>
    </Target>

    <Condition FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-at-least-one-member-of">
        <SubjectAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:subject:loginId" DataType="http://www.w3.org/2001/XMLSchema#string"/>
        <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-bag">
          <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">etdadmin</AttributeValue>
        </Apply>
    </Condition>
</Rule>
     ';

 const draft = '
  <Rule xmlns="urn:oasis:names:tc:xacml:1.0:policy" RuleId="draft" Effect="Permit">
 <!-- (should only be active when etd is a draft)
    Allow author to modify metadata, history, and status (mods, premis, rels-ext)  -->
    <Target>
     <Subjects>
        <AnySubject/>
      </Subjects>
      <Resources>
    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">MODS</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>

    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">PREMIS</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>

    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">RELS-EXT</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>
      </Resources>
      <Actions>
        <Action>
          <ActionMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">urn:fedora:names:fedora:2.1:action:id-modifyDatastreamByValue</AttributeValue>
            <ActionAttributeDesignator DataType="http://www.w3.org/2001/XMLSchema#string" AttributeId="urn:fedora:names:fedora:2.1:action:id"/>
          </ActionMatch>
        </Action>
      </Actions>
    </Target>

    <Condition FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-is-in">
         <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">author</AttributeValue>
        <SubjectAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:subject:loginId" DataType="http://www.w3.org/2001/XMLSchema#string"/>
   </Condition>
</Rule>';
 

const published = '<Rule xmlns="urn:oasis:names:tc:xacml:1.0:policy"  RuleId="published" Effect="Permit">
 <!-- Allow anyone to view metadata + related objects (mods, dc, xhtml, rels-ext)  
      should only be active when etd is published -->
    <Target>
     <Subjects>
        <AnySubject/>
      </Subjects>
      <Resources>
    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">DC</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>
    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">XHTML</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>

    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">MODS</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>

    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">RELS-EXT</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>
      </Resources>
      <Actions>
        <Action>
          <ActionMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">urn:fedora:names:fedora:2.1:action:id-getDatastreamDissemination</AttributeValue>
            <ActionAttributeDesignator DataType="http://www.w3.org/2001/XMLSchema#string" AttributeId="urn:fedora:names:fedora:2.1:action:id"/>
          </ActionMatch>
        </Action>
      </Actions>
    </Target>
</Rule>
';
  


}