<?php
/**
 * @category Etd
 * @package Etd_Models
 * @subpackage Etd_File
 */

require_once("policy.php");

class EtdFileXacmlPolicy extends XacmlPolicy {


  public function addRule($name) {
    // override published rule in parent class
    $dom = new DOMDocument();
    switch ($name) {
    case "published": $dom->loadXML(EtdFileXacmlRules::published); break;
    case "draft": $dom->loadXML(EtdFileXacmlRules::draft); break;

    default:
      // for any other rule, inherit from parent
      return parent::addRule($name);
    }

    // if one of the customized rules:
    $newrule = $this->dom->importNode($dom->documentElement, true);
    $this->domnode->appendChild($newrule);
    $this->update();

    // special handling - set the date in published rule
    if ($name == "published") {
      $this->published->condition->embargo_end = date("Y-m-d");
    }
  }

  // customize validation to check date format 
  public function isValid() {

    // Check date format in published conditon, if present
    // -- If the date in the embargo condition is not in YYYY-MM-DD format,
    //    Fedora will choke on the xacml, even though it is technically
    //    valid according to the schema
    if (isset($this->published)) {
      if (! preg_match("/^\d{4}-\d{2}-\d{2}$/",
           $this->published->condition->embargo_end))
        return false;
    }
    return parent::isValid();
  }

  // override to get correct draft policy
  public static function getTemplate() {
    $xml = new DOMDocument();
    $xml->loadXML(file_get_contents("policy.xml", FILE_USE_INCLUDE_PATH));
    $policy = new EtdFileXacmlPolicy($xml);

    // add the appropriate rules for a new etdfile
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

class EtdFileXacmlRules {

  /* variation on the draft rule for etd - different datastreams, different actions */
 const draft = '
  <Rule xmlns="urn:oasis:names:tc:xacml:1.0:policy" RuleId="draft" Effect="Permit">
 <!-- (should only be active when etd is a draft)
    Allow author to modify metadata, policy, or binary file datastreams -->
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

    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">FILE</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                DataType="http://www.w3.org/2001/XMLSchema#string"/>
        </ResourceMatch>
      </Resource>

      <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue 
    DataType="http://www.w3.org/2001/XMLSchema#string">info:fedora/emory-control:EtdFile-1.0</AttributeValue>
            <ResourceAttributeDesignator 
    AttributeId="info:fedora/fedora-system:def/model#hasModel"
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

        <Action>
          <ActionMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">urn:fedora:names:fedora:2.1:action:id-modifyDatastreamByReference</AttributeValue>
            <ActionAttributeDesignator DataType="http://www.w3.org/2001/XMLSchema#string" AttributeId="urn:fedora:names:fedora:2.1:action:id"/>
          </ActionMatch>
        </Action>

  <!-- using modifyObject to set object status to Deleted -->
        <Action>
          <ActionMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">urn:fedora:names:fedora:2.1:action:id-modifyObject</AttributeValue>
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
 

  
  /* variation on the published rule - different datastreams, condition for embargo end date */
const published = '<Rule xmlns="urn:oasis:names:tc:xacml:1.0:policy"  RuleId="published" Effect="Permit">
 <!-- Allow anyone to view metadata + file conditional on embargo end date
      should only be active when etd file is published -->
    <Target>
     <Subjects>
        <AnySubject/>
      </Subjects>
      <Resources>

    <!-- restrict to etd file objects ONLY -->
    <Resource>
      <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
          <AttributeValue
          DataType="http://www.w3.org/2001/XMLSchema#string">info:fedora/emory-control:EtdFile-1.0</AttributeValue>
          <ResourceAttributeDesignator
          AttributeId="info:fedora/fedora-system:def/model#hasModel"
          DataType="http://www.w3.org/2001/XMLSchema#string"
          MustBePresent="false"/>
      </ResourceMatch>
    </Resource> 
     
    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">DC</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                DataType="http://www.w3.org/2001/XMLSchema#string" MustBePresent="false"/>
        </ResourceMatch>
      </Resource>

    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">RELS-EXT</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                DataType="http://www.w3.org/2001/XMLSchema#string" MustBePresent="false"/>
        </ResourceMatch>
      </Resource>

    <Resource>
        <ResourceMatch MatchId="urn:oasis:names:tc:xacml:1.0:function:string-equal">
            <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">FILE</AttributeValue>
            <ResourceAttributeDesignator AttributeId="urn:fedora:names:fedora:2.1:resource:datastream:id" 
                DataType="http://www.w3.org/2001/XMLSchema#string" MustBePresent="false"/>
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


     <Condition FunctionId="urn:oasis:names:tc:xacml:1.0:function:date-greater-than-or-equal">
       <Apply FunctionId="urn:oasis:names:tc:xacml:1.0:function:date-one-and-only">
         <EnvironmentAttributeDesignator 
      AttributeId="urn:fedora:names:fedora:2.1:environment:currentDate"
            DataType="http://www.w3.org/2001/XMLSchema#date"/>
       </Apply>
       <AttributeValue DataType="http://www.w3.org/2001/XMLSchema#date"/>
     </Condition>  

</Rule>';
/* Note: tested date comparison rule manually (2008-02-18) and it works */
 
}
