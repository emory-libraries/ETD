<?php

require_once("../bootstrap.php"); 
require_once('xml_acl.php');

class TestXmlAcl extends UnitTestCase {
    private $acl;

  function setUp() {
    $this->acl = Zend_Registry::get('acl');
  }
  
  function tearDown() {
  }

  function testRoles() {
    $this->assertTrue($this->acl->hasRole("guest"));
    $this->assertTrue($this->acl->hasRole("author"));
    $this->assertTrue($this->acl->hasRole("committee"));
    $this->assertTrue($this->acl->hasRole("admin"));
  }

  function testResources() {
    $this->assertTrue($this->acl->has("etd"));
    $this->assertTrue($this->acl->has("draft etd"));
    $this->assertTrue($this->acl->has("published etd"));
  }

  function testInheritance() {
    $this->assertTrue($this->acl->inherits("published etd", "etd"));
    $this->assertTrue($this->acl->inherits("draft etd", "etd"));
    $this->assertFalse($this->acl->inherits("etd", "draft etd"));
  }

  function testGuest() {
    $this->assertTrue($this->acl->isAllowed("guest", "published etd", "view metadata"));
    $this->assertTrue($this->acl->isAllowed("guest", "published etd", "view statistics"));
    $this->assertFalse($this->acl->isAllowed("guest", "published etd", "view history"));
    $this->assertFalse($this->acl->isAllowed("guest", "etd", "view metadata"));
    $this->assertFalse($this->acl->isAllowed("guest", "draft etd", "view metadata"));

    $this->assertFalse($this->acl->isAllowed("guest", "etd", "edit metadata"));
    $this->assertFalse($this->acl->isAllowed("guest", "draft etd", "edit metadata"));
    $this->assertFalse($this->acl->isAllowed("guest", "etd", "add file"));

    $this->assertFalse($this->acl->isAllowed("guest", "file", "view modified"));

  }

  function testAuthor() {
    $this->assertTrue($this->acl->isAllowed("author", "etd", "view metadata"));
    $this->assertTrue($this->acl->isAllowed("author", "etd", "view history"));
    $this->assertTrue($this->acl->isAllowed("author", "etd", "view status"));
    $this->assertTrue($this->acl->isAllowed("author", "file", "download"));
    $this->assertTrue($this->acl->isAllowed("author", "file", "view modified"));
    $this->assertTrue($this->acl->isAllowed("author", "draft etd", "view metadata"));
    
    $this->assertTrue($this->acl->isAllowed("author", "draft etd", "edit metadata"));
    $this->assertTrue($this->acl->isAllowed("author", "draft etd", "add file"));
		      
    $this->assertFalse($this->acl->isAllowed("author", "etd", "edit metadata"));
    $this->assertFalse($this->acl->isAllowed("author", "etd", "add file"));

  }

  function testCommittee() {
    $this->assertTrue($this->acl->isAllowed("committee", "etd", "view metadata"));
    $this->assertTrue($this->acl->isAllowed("committee", "etd", "view history"));
    $this->assertTrue($this->acl->isAllowed("committee", "etd", "view status"));
    $this->assertTrue($this->acl->isAllowed("committee", "file", "download"));
    $this->assertTrue($this->acl->isAllowed("committee", "draft etd", "view metadata"));
    
    $this->assertFalse($this->acl->isAllowed("committee", "etd", "edit metadata"));
    $this->assertFalse($this->acl->isAllowed("committee", "etd", "add file"));
  }

  function testAdmin() {
    $this->assertTrue($this->acl->isAllowed("admin", "etd", "view metadata"));
    $this->assertTrue($this->acl->isAllowed("admin", "etd", "view status"));
    $this->assertTrue($this->acl->isAllowed("admin", "etd", "view history"));
    $this->assertTrue($this->acl->isAllowed("admin", "file", "download"));
    $this->assertTrue($this->acl->isAllowed("admin", "file", "view modified"));
    $this->assertTrue($this->acl->isAllowed("admin", "etd", "edit history"));
    $this->assertTrue($this->acl->isAllowed("admin", "etd", "edit status"));


    $this->assertTrue($this->acl->isAllowed("admin", "submitted etd", "review"));
    $this->assertFalse($this->acl->isAllowed("admin", "etd", "review"));
    $this->assertFalse($this->acl->isAllowed("admin", "draft etd", "review"));
    
    $this->assertTrue($this->acl->isAllowed("admin", "reviewed etd", "approve"));
    $this->assertFalse($this->acl->isAllowed("admin", "submitted etd", "approve"));

    $this->assertTrue($this->acl->isAllowed("admin", "approved etd", "publish"));
    
    $this->assertTrue($this->acl->isAllowed("admin", "published etd", "inactivate"));
    $this->assertFalse($this->acl->isAllowed("admin", "etd", "inactivate"));

    $this->assertFalse($this->acl->isAllowed("admin", "log", "view"));
  }

}

runtest(new TestXmlAcl());