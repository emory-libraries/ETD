<?php

require_once("models/unAPIresponse.php");
require_once("models/EtdFactory.php");
	    

// unAPI implementation

class UnapiController extends Etd_Controller_Action {

  protected $requires_fedora = true;

  public function indexAction() {
    // disable layouts and view script rendering;
    $this->_helper->layout->disableLayout();
    $this->_helper->viewRenderer->setNoRender();		// don't use view templates
    // set content-type header as application/xml
    $this->getResponse()->setHeader('Content-Type', "application/xml"); 
    // set response code for format list - multiple choices (may be overridden)
    if (! $this->_hasParam("format")) $this->_response->setHttpResponseCode(300);

	  
    //unAPI passes URI as id parameter; using resolvable ARK as id
    $ark = $this->_getParam("id", null);
    $persis = new Emory_Service_Persis(Zend_Registry::get("persis-config"));
    // if ark is set
    if ($ark) {
      // if id looks like an ark
      if ($persis->isArk($ark))  {      
	// convert ARK to fedora pid
	$pid = $persis->pidfromArk($ark);
	$this->_setParam("pid", $pid);
	try { 
	  $etd = EtdFactory::init($pid, "etd");
	} catch (Exception $e) {
	  // for any exception at all (not found, not authorized) - assume invalid id
	  $this->_response->setHttpResponseCode(404);
	}
	
	// if a format is requested, serve it out
	if ($this->_hasParam("format")) {
	  $format = $this->_getParam("format");
	  switch ($format) {
	  case "oai_dc":
	    $this->_forward("dc", "view", null, array("pid" => $pid)); break;
	  case "mods":
	    $this->_forward("mods", "view", null, array("pid" => $pid)); break;
	  case "pdf":
	    // NOTE: an ETD may have more than one PDF; only serving out the first
	    if ($etd->mods->embargo_end > date("Y-m-d")) {
	      $this->_response->setHttpResponseCode(403);
	    } else {
	      $filepid = $etd->rels_ext->pdf[0];
	      $this->_setParam("pid", $filepid);
	      $this->_forward("view", "file", null, array("pid" => $filepid)); break;
	    }
	    break;
	  default:
	    // Not Acceptable - valid identifier, bad format
	    $this->_response->setHttpResponseCode(406);
	  }
	}
      } else { 	// id is not an ark
	$this->_response->setHttpResponseCode(404);
      }
    }

    // no format requested, possibly no identifier - list formats
    
    $response = new unAPIresponse();
    if ($ark) $response->setId($ark);
    $response->addFormat("oai_dc", "text/xml",
			 "http://www.openarchives.org/OAI/2.0/oai_dc.xsd");
    $response->addFormat("mods", "text/xml",
			 "http://www.loc.gov/standards/mods/v3/mods-3-3.xsd");   
    $response->addFormat("pdf", "application/pdf",
			 "http://partners.adobe.com/public/developer/pdf/index_reference.html");
    $this->getResponse()->setBody($response->saveXML());
  }
  
}