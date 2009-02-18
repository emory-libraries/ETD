<?php
require_once("../bootstrap.php");
require_once('models/file_policy.php');

class TestFilePolicy extends UnitTestCase {
  private $policy;

  function setUp() {
    $xml = new DOMDocument();
    $xml->loadXML(XacmlPolicy::getTemplate());
    $this->policy = new EtdFileXacmlPolicy($xml);
  }

  function tearDown() {}

  function testBasicProperties() {
    $this->assertIsA($this->policy, "EtdFileXacmlPolicy", "object type");
    $this->assertIsA($this->policy->rules, "array", "array of rules");
    $this->assertIsA($this->policy->rules[0], "PolicyRule", "first rule object type");
  }

  function testValidation() {
    $this->assertTrue($this->policy->isValid());

    // test bad date format in condition - should not be valid
    $this->policy->addRule("published");
    $this->policy->published->condition->embargo_end = "2008-01-01T00:00:00.000Z";
    $this->assertFalse($this->policy->isValid());
    // proper date format should be valid
    $this->policy->published->condition->embargo_end = "2008-01-01";
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

    //    $this->policy->removeRule("published");
    $this->policy->addRule("published");
    $this->assertIsA($this->policy->view, "PolicyRule");
    // published rule for ETD files must have condition or embargo date
    $this->assertTrue(isset($this->policy->published->condition));
    $this->assertTrue(isset($this->policy->published->condition->embargo_end));
    
    
    // add a non-existent rule
    $this->expectError("Rule 'nonexistent' unknown - cannot add to policy");
    $this->policy->addRule("nonexistent");
    
  }


  function testGetTemplate() {
    $xml = new DOMDocument();
    $xml->loadXML(XacmlPolicy::getTemplate());
    $policy = new XacmlPolicy($xml);

    // should not include published rule
    $this->assertFalse(isset($policy->published));
    //should include draft and view
    $this->assertTrue(isset($policy->draft));
    $this->assertTrue(isset($policy->view));
  }
  
}


runtest(new TestFilePolicy());
?>

