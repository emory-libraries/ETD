<?php

/**
 * xacml policy foxml datastream for etd records
 * @category Etd
 * @package Etd_Models
 * @subpackage Etd
 *
 * @property string $policyid
 * @property string $description
 * @property string $pid object this policy applies to
 * @property array $rules array of PolicyRule
 * @property PolicyRule $view
 * @property PolicyRule $draft
 * @property PolicyRule $published
 * @property PolicyRule $deny_most
 */

require_once("models/foxmlDatastreamAbstract.php");

class XacmlPolicy extends foxmlDatastreamAbstract {
  
  public $dslabel = "Policy";
  public $control_group = FedoraConnection::MANAGED_DATASTREAM;
  public $state = FedoraConnection::STATE_ACTIVE;
  public $versionable = true;
  public $mimetype = 'text/xml';  
  protected $namespace = "urn:oasis:names:tc:xacml:1.0:policy";
  protected $schema = "http://www.oasis-open.org/committees/download.php/915/cs-xacml-schema-policy-01.xsd";
  
  protected $xmlconfig;

  
  public function __construct($dom=null, $xpath = null) {
    if (is_null($dom)) {
      $dom = $this->construct_from_template();
    }
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
    $etdrules = array("fedoraAdmin", "view", "draft", "published", "deny_most");
    foreach ($etdrules as $ruleid) {
      $this->xmlconfig[$ruleid] = array("xpath" => "x:Rule[@RuleId='$ruleid']", "class_name" => "PolicyRule");
    }

  }

  /**
   * add a rule to policy by name
   * @param string $name
   */
  public function addRule($name) {
    $dom = new DOMDocument();
    switch ($name) {
    case "view":    $xml = EtdXacmlRules::view; break;
    case "draft":   $xml = EtdXacmlRules::draft; break;
    case "published":   $xml = EtdXacmlRules::published; break;
    default:
      trigger_error("Rule '$name' unknown - cannot add to policy", E_USER_WARNING); return;
    }
    $dom->loadXML($xml);

    $newrule = $this->dom->importNode($dom->documentElement, true);
    // append to the end of the policy
    //  - note that this means rules should be added in the correct order (if they are order dependent)
    $this->domnode->appendChild($newrule);

    $this->update();
    
    // special handling - set the date in published rule
    // (otherwise xacml is not valid)
    //    if ($name == "published" && isset($this->published->condition->embargo_end)) {
    if ($name == "published") {
      $this->published->condition->embargo_end = date("Y-m-d");
    }
  }

  /**
   * remove a rule from policy by id
   * @param string $id
   */
  public function removeRule($id) {
    $nodeList = $this->xpath->query("x:Rule[@RuleId='$id']", $this->domnode); // relative to policy dom
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
    $policy->addRule("draft");
    
    return $policy->saveXML();
  }
  
  protected function construct_from_template() {
    $dom = new DOMDocument();
    $dom->loadXML(self::getTemplate());
    return $dom;
  }  
}

/**
 * xml object for a single policy rule
 *
 * @property string $id rule id
 * @property policyResource $resources
 * @property policyCondition $condition
 */
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

/**
 * xml object for a resource in a policy rule
 *
 * @property array $datastreams datastream resources
 */
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

  /**
   * add a datastream to the policy rule
   * @param string $id datastream id
   */
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

/**
 * xml object for a condition in a policy rule
 *
 * @property array $users array of users by loginId
 * @property string $user single user by loginId
 * @property string $department department name for departmental staff
 * @property string $embargo_end
 * @property array $methods methods (not restricted)
 * @property array $embargoed_methods restricted methods
 */
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
    $this->userxpath = ".//x:Apply[contains(@FunctionId,'string-bag')][preceding-sibling::x:SubjectAttributeDesignator[contains(@AttributeId, 'loginId')]]/x:AttributeValue";
  

    $this->xmlconfig =  array(
            // fairly specific to etd xacml - list of users (logins)
        "users" => array("xpath" => $this->userxpath, "is_series" => true),

        // single user (draft rule)
        //  "user" => array("xpath" => ".[@FunctionId='urn:oasis:names:tc:xacml:1.0:function:string-is-in']/x:AttributeValue[following-sibling::x:SubjectAttributeDesignator[@AttributeId='urn:fedora:names:fedora:2.1:subject:loginId']]"),
        "user" => array("xpath" => "x:AttributeValue[following-sibling::x:SubjectAttributeDesignator[@AttributeId='urn:fedora:names:fedora:2.1:subject:loginId'] and parent::x:Condition[@FunctionId='urn:oasis:names:tc:xacml:1.0:function:string-is-in']]"),

        // department name for departmental staff
        "department" => array("xpath" => "x:Apply[@FunctionId='urn:oasis:names:tc:xacml:1.0:function:string-is-in'][x:SubjectAttributeDesignator/@AttributeId='deptCoordinator']/x:AttributeValue"),


        // date
        "embargo_end" => array("xpath" => ".//x:AttributeValue[@DataType='http://www.w3.org/2001/XMLSchema#date'][ancestor::x:*/@FunctionId='urn:oasis:names:tc:xacml:1.0:function:date-greater-than-or-equal']"),

        // methods  (not restricted)   
        // NOTE: using full xpath to exclude restricted methods from this list
        "methods" => array("xpath" => "x:Apply[@FunctionId='urn:oasis:names:tc:xacml:1.0:function:and']/x:Apply[@FunctionId='urn:oasis:names:tc:xacml:1.0:function:and']/x:Apply[@FunctionId='urn:oasis:names:tc:xacml:1.0:function:or']/x:Apply[x:ResourceAttributeDesignator/@AttributeId='urn:fedora:names:fedora:2.1:resource:disseminator:method']/x:Apply[@FunctionId='urn:oasis:names:tc:xacml:1.0:function:string-bag']/x:AttributeValue[@DataType='http://www.w3.org/2001/XMLSchema#string']",
               "is_series" => true),

        // embargoed methods 
        "embargoed_methods" => array("xpath" => ".//x:Apply[@FunctionId='urn:oasis:names:tc:xacml:1.0:function:and' and x:Apply/@FunctionId='urn:oasis:names:tc:xacml:1.0:function:date-greater-than-or-equal']/x:Apply[x:ResourceAttributeDesignator/@AttributeId='urn:fedora:names:fedora:2.1:resource:disseminator:method']/x:Apply[@FunctionId='urn:oasis:names:tc:xacml:1.0:function:string-bag']/x:AttributeValue[@DataType='http://www.w3.org/2001/XMLSchema#string']",
                    "is_series" => true),

        );
  }

  /**
   * add a user to the list of users in the condition
   * @param string $username
   */
  public function addUser($username) {
    if (!count($this->users)) {
      trigger_error("Cannot add user '$username' because this condition has no users", E_USER_WARNING);
      return;
    }
    // user is already present - no need to add
    if ($this->users->includes($username)) return;
    
    // if first user is blank, store the value there
    if ($this->users[0] == "")
      $this->users[0] = $username;
    else {
      $this->users->append($username);
      $this->update(); 
    }
  }

  /**
   * remove a user from the list of users in the condition
   * @param string $username
   */
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

  /**
   * restrict the specified methods - remove them from methods, add to embargoed_methods
   * @param array $restrict_methods
   */
  public function restrictMethods($restrict_methods) {
    // add restricted methods to embargoed_methods list
    if (isset($this->embargoed_methods)) {
      for ($i = 0; $i < count($restrict_methods); $i++) {
        if (isset($this->embargoed_methods[$i]) && $this->embargoed_methods[$i] == "")
          $this->embargoed_methods[$i] = $restrict_methods[$i];
        else
          $this->embargoed_methods->append($restrict_methods[$i]);
            }
          }  // warn ?


    // remove restricted methods from non-embargoed method list
    for ($i = 0; $i < count($this->methods); $i++) {
      if (in_array($this->methods[$i], $restrict_methods)) {
        $this->methods[$i] = "";
      }
    }
  }

  
}



class EtdXacmlRules {
  /* namespace needs to be explicit so it will match main policy record
     and xpath queries will find these rules
  */
  
const view = '<Rule xmlns="urn:oasis:names:tc:xacml:1.0:policy" RuleId="view" Effect="Permit">
 <!-- Allow committee members and  departmental staff to view everything (mods, dc, xhtml, premis, rels-ext)  -->
    <Target>
     <Subjects>
        <AnySubject/>
      </Subjects>
      <Resources>
  <!-- common datastreams (etd and etdfile) -->
        <Resource>
         <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">DC</AttributeValue>
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
    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">POLICY</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>

  <!-- etd-only datastreams -->
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

  <!-- etd methods (xhtml sections) -->

      <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">title</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:disseminator:method" 
                DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>
      <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">abstract</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:disseminator:method" 
                DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>
      <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">tableofcontents</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:disseminator:method" 
                DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>


  <!-- etdFile-only datastreams -->
        <Resource>
         <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">FILE</AttributeValue>
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

      <!-- api-m getDatastream for high-level datastream info (time last modified) -->
      <Action>
        <ActionMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
          <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">urn:fedora:names:fedora:2.1:action:id-getDatastream</AttributeValue>
          <ActionAttributeDesignator
                MustBePresent="false"
                DataType="http://www.w3.org/2001/XMLSchema#string" 
                AttributeId="urn:fedora:names:fedora:2.1:action:id"/>
        </ActionMatch>
       </Action>

      </Actions>
    </Target>
  
    <Condition FunctionId="urn:oasis:names:tc:xacml:1.0:function:or">

      <!--  committee by username -->
      <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-at-least-one-member-of">
        <SubjectAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:subject:loginId" DataType="http://www.w3.org/2001/XMLSchema#string"/>
        <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-bag">
          <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </Apply>
      </Apply>

      <!-- program coordinator: ESD db filter should set deptCoordinator attribute -->
        <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-is-in">
         <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string"/>
   <SubjectAttributeDesignator DataType="http://www.w3.org/2001/XMLSchema#string"
    AttributeId="deptCoordinator" MustBePresent="false"/>
      </Apply>  <!-- end program coordinator -->

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
    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">POLICY</AttributeValue>
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
        <AnyResource/>
      </Resources>
      <Actions>
        <AnyAction/>
      </Actions>
    </Target>


    <Condition FunctionId="urn:oasis:names:tc:xacml:1.0:function:or">
      <!-- view datastreams -->
     <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:and">
        <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-is-in">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">urn:fedora:names:fedora:2.1:action:id-getDatastreamDissemination</AttributeValue>
          <ActionAttributeDesignator DataType="http://www.w3.org/2001/XMLSchema#string" AttributeId="urn:fedora:names:fedora:2.1:action:id" MustBePresent="false"/>
        </Apply>
  <!-- publicly accessible datastreams -->
        <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-at-least-one-member-of">
          <ResourceAttributeDesignator DataType="http://www.w3.org/2001/XMLSchema#string" AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" MustBePresent="false"/>
        <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-bag">
          <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">DC</AttributeValue>
          <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">MODS</AttributeValue>
          <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">RELS-EXT</AttributeValue>
        </Apply>  <!-- end string bag -->
      </Apply>    <!-- end at least one member -->
    </Apply>  <!-- end and : view datastreams -->

    <!-- html disseminations -->
     <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:and">
  <!-- action : getDissemination -->
        <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-is-in">
          <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">urn:fedora:names:fedora:2.1:action:id-getDissemination</AttributeValue>
           <ActionAttributeDesignator DataType="http://www.w3.org/2001/XMLSchema#string" AttributeId="urn:fedora:names:fedora:2.1:action:id" MustBePresent="false"/>
  </Apply>
  <!-- methods -->
       <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:and">
        <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-is-in">
          <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">emory-control:ETDmetadataParts</AttributeValue>
      <ResourceAttributeDesignator DataType="http://www.w3.org/2001/XMLSchema#string" AttributeId="urn:fedora:names:fedora:2.1:resource:sdef:pid" MustBePresent="false"/>
        </Apply>

       <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:or">

  <!-- methods allowed, by name -->
        <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-at-least-one-member-of">
          <ResourceAttributeDesignator DataType="http://www.w3.org/2001/XMLSchema#string" AttributeId="urn:fedora:names:fedora:2.1:resource:disseminator:method" MustBePresent="false"/>
        <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-bag">
          <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">title</AttributeValue>
          <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">abstract</AttributeValue>
          <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">tableofcontents</AttributeValue>
        </Apply>  <!-- end string bag -->
      </Apply>    <!-- end at least one member -->

       <!-- these methods only allowed after a certain date -->
       <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:and">
         <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-at-least-one-member-of">
            <ResourceAttributeDesignator DataType="http://www.w3.org/2001/XMLSchema#string" AttributeId="urn:fedora:names:fedora:2.1:resource:disseminator:method" MustBePresent="false"/>
          <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:string-bag">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string"></AttributeValue>
          </Apply>  <!-- end string bag -->
        </Apply>    <!-- end at least one member -->

        <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:date-greater-than-or-equal">
         <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:date-one-and-only">
           <EnvironmentAttributeDesignator 
        AttributeId="urn:fedora:names:fedora:2.1:environment:currentDate"
              DataType="http://www.w3.org/2001/XMLSchema#date"/>
         </Apply>
         <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#date"/>
       </Apply>  
      </Apply>  <!-- end and (method & date) -->
      </Apply>  <!-- end or (methods restricted by date) -->

    </Apply>  <!-- end and (sdef pid & methods) -->
    </Apply>  <!-- end and (html disseminations) -->



   </Condition>  


</Rule>
';
 
/* Note: tested date comparison rule manually (2008-02-18) and it works */

}
