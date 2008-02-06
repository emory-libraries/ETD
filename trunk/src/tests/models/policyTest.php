<?php

require_once('models/policy.php');

class policyTest extends UnitTestCase {
  private $policy;

  function setUp() {
    $xml = new DOMDocument();
    $xml->load("fixtures/policy.xml");
    $this->policy = new XacmlPolicy($xml);
  }

  function tearDown() {}

  function testBasicProperties() {

    // object types & subtypes, correctly mapping & can read values from xml
    
    $this->assertIsA($this->policy, "XacmlPolicy", "object type");
    $this->assertIsA($this->policy->rules, "array", "array of rules");
    $this->assertIsA($this->policy->rules[0], "PolicyRule", "first rule object type");

    $this->assertEqual("emory-123", $this->policy->policyid, "policy id value");
    $this->assertEqual("object-specific policy", $this->policy->description, "policy description value");
    $this->assertEqual("fedoraAdmin", $this->policy->rules[0]->id, "rule id value");

    $this->assertIsA($this->policy->rules[0]->resources, "policyResource",
		     "rule resource type");
    $this->assertIsA($this->policy->rules[1]->resources->datastreams, "DOMElementArray");
    $this->assertEqual("DC", $this->policy->rules[1]->resources->datastreams[0]);

    // specific etd rules
    $this->assertIsA($this->policy->fedoraAdmin, "PolicyRule");
    $this->assertIsA($this->policy->view, "PolicyRule");
    $this->assertIsA($this->policy->etdadmin, "PolicyRule");
    $this->assertIsA($this->policy->draft, "PolicyRule");
    $this->assertIsA($this->policy->published, "PolicyRule");

    $this->assertIsA($this->policy->view->condition, "policyCondition");
    $this->assertIsA($this->policy->view->condition->users, "DOMElementArray");
    
    $this->assertEqual($this->policy->view->condition->users[0], "author");
    $this->assertEqual($this->policy->view->condition->users[1], "committee");
    $this->assertEqual($this->policy->view->condition->users[2], "etdadmin");
  }

  function testValidation() {
    $this->assertTrue($this->policy->isValid());
  }


  function testRemoveRule() {
    $rulecount = count($this->policy->rules);
    $this->policy->removeRule("draft");

    // three different ways of checking that the remove succeeded
    $this->assertFalse(isset($this->policy->draft));
    $this->assertNoPattern('/Rule RuleId="draft"/', $this->policy->saveXML());
    $this->assertEqual($rulecount - 1, count($this->policy->rules));

    $this->expectError("Couldn't find rule 'notarule' to remove");
    $this->policy->removeRule("notarule");
  }

  
  function testAddRule() {
    // add rule function not yet implemented
  }

  function testAddDatastream() {
    $dscount = count($this->policy->etdadmin->resources->datastreams);
    $this->policy->etdadmin->resources->addDatastream("TEI");
    $this->assertEqual($dscount + 1, count($this->policy->etdadmin->resources->datastreams));
    $this->assertEqual("TEI", $this->policy->etdadmin->resources->datastreams[$dscount]);

    $this->expectError("Cannot add datastream 'bogus' because this resource has no datastreams");
    $this->policy->deny_most->resources->addDatastream("bogus");
  }

  function testAddUser() {
    $usercount = count($this->policy->view->condition->users);
    $this->policy->view->condition->addUser("newuser");
    $this->assertEqual($usercount + 1, count($this->policy->view->condition->users));
    $this->assertEqual("newuser", $this->policy->view->condition->users[$usercount]);
    $this->assertPattern('|<AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">newuser</AttributeValue>|', $this->policy->saveXML());

    // try adding to a policy with a condition but no users
    $this->expectError("Cannot add user 'failure' because this condition has no users");
    $this->policy->deny_most->condition->addUser("failure");
  }

  function testRemoveUser() {
    $usercount = count($this->policy->view->condition->users);
    $this->policy->view->condition->removeUser("committee");
    $this->assertEqual($usercount - 1, count($this->policy->view->condition->users));
    $this->assertNoPattern('|<AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">committee</AttributeValue>|', $this->policy->saveXML());

    // attempt to remove a user that doesn't exist
    $this->expectError("Cannot find user to be removed: 'nonexistent'");
    $this->policy->view->condition->removeUser("nonexistent");
    
  }

  
}
