<?php
require_once("../bootstrap.php");
require_once('models/datastreams/policy.php');

class TestPolicy extends UnitTestCase {
  private $policy;

  function setUp() {
    $xml = new DOMDocument();
    $xml->load("../fixtures/policy.xml");
    $this->policy = new XacmlPolicy($xml);
  }

  function tearDown() {}

  function testBasicProperties() {

    // object types & subtypes, correctly mapping & can read values from xml
    
    $this->assertIsA($this->policy, "XacmlPolicy", "object type");
    $this->assertIsA($this->policy->rules, "array", "array of rules");
    $this->assertIsA($this->policy->rules[0], "PolicyRule", "first rule object type");

    $this->assertEqual("emory-123", $this->policy->policyid, "policy id value");
    $this->assertEqual("emory:123", $this->policy->pid, 
        "policy pid value should be 'emory:123', got '" . $this->policy->pid . '"');
    $this->assertEqual("object-specific policy", $this->policy->description, "policy description value");
    $this->assertEqual("fedoraAdmin", $this->policy->rules[0]->id, "rule id value");

    $this->assertIsA($this->policy->rules[0]->resources, "policyResource",
		     "rule resource type");
    $this->assertIsA($this->policy->rules[1]->resources->datastreams, "DOMElementArray");
    $this->assertEqual("DC", $this->policy->rules[1]->resources->datastreams[0]);

    // specific etd rules
    $this->assertIsA($this->policy->fedoraAdmin, "PolicyRule");
    $this->assertIsA($this->policy->view, "PolicyRule");
    $this->assertIsA($this->policy->draft, "PolicyRule");
    $this->assertIsA($this->policy->published, "PolicyRule");

    $this->assertIsA($this->policy->view->condition, "policyCondition");
    $this->assertIsA($this->policy->view->condition->users, "DOMElementArray");
    
    $this->assertEqual($this->policy->view->condition->users[0], "author");
    $this->assertEqual($this->policy->view->condition->users[1], "committee");
    $this->assertEqual($this->policy->view->condition->users[2], "etdadmin");

    $this->assertEqual($this->policy->view->condition->department, "Chemistry");

    $this->assertIsA($this->policy->published->condition, "policyCondition");
    $this->assertEqual($this->policy->published->condition->embargo_end, "2008-01-01");
    
    // single user
    $this->assertEqual($this->policy->draft->condition->user, "author");

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

    $this->policy->removeRule("draft");
    $this->policy->addRule("draft");
    $this->assertIsA($this->policy->draft, "PolicyRule");

    $this->policy->removeRule("view");
    $this->policy->addRule("view");
    $this->assertIsA($this->policy->view, "PolicyRule");

    $this->policy->removeRule("published");
    $this->policy->addRule("published");
    $this->assertIsA($this->policy->published, "PolicyRule");
    // published rule for ETDs now includes conditional logic
    $this->assertTrue(isset($this->policy->published->condition),
		      "etd publish policy includes a condition");
    $this->assertIsA($this->policy->published->condition->methods,
		     "DOMElementArray");
    $this->assertEqual("title", $this->policy->published->condition->methods[0]);
    $this->assertEqual("abstract",
		       $this->policy->published->condition->methods[1]);
    $this->assertEqual("tableofcontents",
		       $this->policy->published->condition->methods[2]);
    $this->assertEqual(3, count($this->policy->published->condition->methods),
		       "non-embargoed methods should only include 3 default methods");
    $this->assertIsA($this->policy->published->condition->embargoed_methods,
		     "DOMElementArray");
    $this->assertTrue(isset($this->policy->published->condition->embargoed_methods[0]),
		     "embargoed_methods array has one entry");
	
    // embargo end should be pre-set to today
    $this->assertEqual(date("Y-m-d"), $this->policy->published->condition->embargo_end);

    // add a non-existent rule
    $this->expectError("Rule 'nonexistent' unknown - cannot add to policy");
    $this->policy->addRule("nonexistent");
    
  }

  function testAddDatastream() {
    $dscount = count($this->policy->draft->resources->datastreams);
    $this->policy->draft->resources->addDatastream("TEI");
    $this->assertEqual($dscount + 1, count($this->policy->draft->resources->datastreams));
    $this->assertEqual("TEI", $this->policy->draft->resources->datastreams[$dscount]);

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

    // add a user that is already present - should not change
    $usercount = count($this->policy->view->condition->users);
    $this->policy->view->condition->addUser("newuser");
    $this->assertEqual($usercount, count($this->policy->view->condition->users));
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

  function testGetTemplate() {
    $xml = new DOMDocument();
    $xml->loadXML(XacmlPolicy::getTemplate());
    $policy = new XacmlPolicy($xml);

    // these rules should be included in a new policy
    //    $this->assertTrue(isset($policy->fedoraAdmin));	// removed
    $this->assertTrue(isset($policy->view));
    $this->assertTrue(isset($policy->draft));

    // should not include published rule
    $this->assertFalse(isset($policy->published));

    // some kind of problem accessing users from template
    // 'author' no longer set as default in policies... - store and then check
    $policy->view->condition->addUser("author");
    $this->assertEqual($policy->view->condition->users[0], "author");
    $policy->draft->condition->user = "author";
    $this->assertEqual($policy->draft->condition->user, "author");
  }

  function testRestrictMethods() {
    $this->policy->removeRule("published");
    $this->policy->addRule("published");
    $pub_condition = $this->policy->published->condition;
    $pub_condition->restrictMethods(array("abstract",
							       "tableofcontents"));

    $this->assertTrue($pub_condition->embargoed_methods->includes("abstract"),
		      "abstract now listed in embargoed methods");
    $this->assertTrue($pub_condition->embargoed_methods->includes("tableofcontents"),
		      "tableofcontents now listed in embargoed methods");
    $this->assertFalse($pub_condition->methods->includes("abstract"),
		       "abstract no longer listed in non-embargoed methods");
    $this->assertFalse($pub_condition->methods->includes("tableofcontents"),
		       "tableofcontents no longer listed in non-embargoed methods");
    
  }
  
}


runtest(new TestPolicy());
?>
