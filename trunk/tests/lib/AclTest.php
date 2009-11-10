<?php

require_once("../bootstrap.php"); 

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
    
    $this->assertTrue($this->acl->has("honors etd"), "resource 'honors etd' is defined");
    $this->assertTrue($this->acl->has("draft honors etd"),
		      "resource 'draft honors etd' is defined");
    $this->assertTrue($this->acl->has("published honors etd"),
		      "resource 'published honors etd' is defined");
  }

  function testInheritance() {
    $this->assertTrue($this->acl->inherits("published etd", "etd"));
    $this->assertTrue($this->acl->inherits("draft etd", "etd"));
    $this->assertFalse($this->acl->inherits("etd", "draft etd"));

    $this->assertTrue($this->acl->inherits("honors etd", "etd"));
    $this->assertTrue($this->acl->inherits("draft honors etd", "draft etd"));
    // FIXME: how can both of these be true?  Thought Zend Acl was more limited..
    $this->assertTrue($this->acl->inherits("draft honors etd", "honors etd"));
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

    $this->assertTrue($this->acl->isAllowed("guest", "published honors etd", "view metadata"));
    $this->assertTrue($this->acl->isAllowed("guest", "published honors etd", "view statistics"));
    $this->assertFalse($this->acl->isAllowed("guest", "published honors etd", "view history"));
    $this->assertFalse($this->acl->isAllowed("guest", "honors etd", "edit metadata"));
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

    $this->assertTrue($this->acl->isAllowed("author", "draft honors etd", "edit metadata"));
    $this->assertTrue($this->acl->isAllowed("author", "honors etd", "view metadata"));
  }

  function testCommittee() {
    $this->assertTrue($this->acl->isAllowed("committee", "etd", "view metadata"));
    $this->assertTrue($this->acl->isAllowed("committee", "etd", "view history"));
    $this->assertTrue($this->acl->isAllowed("committee", "etd", "view status"));
    $this->assertTrue($this->acl->isAllowed("committee", "file", "download"));
    $this->assertTrue($this->acl->isAllowed("committee", "draft etd", "view metadata"));
    
    $this->assertFalse($this->acl->isAllowed("committee", "etd", "edit metadata"));
    $this->assertFalse($this->acl->isAllowed("committee", "etd", "add file"));

    $this->assertTrue($this->acl->isAllowed("committee", "honors etd", "view metadata"));
    $this->assertTrue($this->acl->isAllowed("committee", "honors etd", "view history"));
    $this->assertTrue($this->acl->isAllowed("committee", "honors etd", "view status"));
    
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
    $this->assertTrue($this->acl->isAllowed("admin", "inactive etd", "reactivate"));
    
    $this->assertFalse($this->acl->isAllowed("admin", "log", "view"));
  }

  function testHonorsAdmin() {
    $this->assertTrue($this->acl->isAllowed("honors admin", "honors etd", "view metadata"),
		      'honors admin can view metadata on honors etd');
    $this->assertTrue($this->acl->isAllowed("honors admin", "honors etd", "view status"),
		      'honors admin can view status on honors etd');
    $this->assertTrue($this->acl->isAllowed("honors admin", "honors etd", "view history"),
		      'honors admin can view history on honors etd');
    $this->assertTrue($this->acl->isAllowed("honors admin", "file", "download"),
		      'honors admin can download file');
    $this->assertTrue($this->acl->isAllowed("honors admin", "file", "view modified"),
		      'honors admin can view modified on file');
    $this->assertTrue($this->acl->isAllowed("honors admin", "honors etd", "edit history"),
		      'honors admin can edit history on honors honors etd');
    $this->assertTrue($this->acl->isAllowed("honors admin", "honors etd", "edit status"),
		      'honors admin can edit status on honors honors etd');


    // cannot only do workflow steps on etds in the correct states
    $this->assertTrue($this->acl->isAllowed("honors admin", "submitted honors etd", "review"),
		      'honors admin can review submitted honors etd');
    $this->assertFalse($this->acl->isAllowed("honors admin", "honors etd", "review"),
		       "honors admin cannot review honors etd");
    $this->assertFalse($this->acl->isAllowed("honors admin", "draft honors etd", "review"),
		       "honors admin cannot review draft honors etd");
    
    $this->assertTrue($this->acl->isAllowed("honors admin", "reviewed honors etd", "approve"),
		      "honors admin can approve reviewed honors etd");
    $this->assertFalse($this->acl->isAllowed("honors admin", "submitted honors etd", "approve"),
		       "honors admin cannot approve submitted honors etd");

    $this->assertTrue($this->acl->isAllowed("honors admin", "published honors etd", "inactivate"),
		      "honors admin can inactivate published honors etd");
    $this->assertFalse($this->acl->isAllowed("honors admin", "honors etd", "inactivate"),
		       "honors admin cannot inactivate generic (not published) honors etd");

    $this->assertFalse($this->acl->isAllowed("honors admin", "submitted etd", "review"),
		       'honors admin cannot review submitted non-honors etd');

    $this->assertFalse($this->acl->isAllowed("honors admin", "log", "view"),
		       "honors admin can view log");

    $this->assertFalse($this->acl->isAllowed("honors admin", "inactive etd", "reactivate"));
  }

}

runtest(new TestXmlAcl());