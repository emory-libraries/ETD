<?php

require_once("policy.php");

class EtdFileXacmlPolicy extends XacmlPolicy {


  public function addRule($name) {
    // override published rule in parent class
    if ($name == "published") {
      $dom = new DOMDocument(); 
      $dom->loadXML(EtdFileXacmlRules::published);

      $newrule = $this->dom->importNode($dom->documentElement, true);
      $this->domnode->appendChild($newrule);
      $this->update();

      // by default, published stuff is available today
      //      $yesterday = time() - (24 * 60 * 60);	// now - 1 day (24 hours; 60 mins; 60 secs)
      //      $this->published->condition->embargo_end = date("Y-m-d", $yesterday);
      // FIXME: do we need to set default date to yesterday to make it available right away?
      $this->published->condition->embargo_end = date("Y-m-d");
      // greater than or equal should allow access on the day it is published ... xacml tests say different
      
    } else {	// for any other rule, inherit from parent
      parent::addRule($name);
    }
  }

}

class EtdFileXacmlRules {

  /* variation on the published rule - different datastreams, condition for embargo end date */
const published = '<Rule xmlns="urn:oasis:names:tc:xacml:1.0:policy"  RuleId="published" Effect="Permit">
 <!-- Allow anyone to view metadata + file conditional on embargo end date
      should only be active when etd file is published -->
    <Target>
     <Subjects>
        <AnySubject/>
      </Subjects>
      <Resources>

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
