<?php

  // FIXME: how do we restrict this so only Fedora can get all the content
  // only allow certain host/ip ? ....

class Services_XmlbyidController extends Zend_Controller_Action {

  public function init() {
    $this->_helper->layout->disableLayout();
  }

  public function indexAction() {
    $this->_forward("about");
  }

  public function viewAction() {
    $this->_helper->viewRenderer->setNoRender();

    // load Fedora configuration
    $fedora_cfg = Zend_Registry::get('fedora-config');

    // ONLY allow requests from Fedora, since this service may have access to restricted content
    if ($this->_request->getServer("REMOTE_ADDR") != gethostbyname($fedora_cfg->server)) {
      $this->_response->setHttpResponseCode(403);	// Forbidden
      // FIXME: should any text/message be displayed here?
      return;
    }
    
    $url =  $this->_getParam("url");  // fedora datastream url
    $id = $this->_getParam("id");   
    try {
      
      // parse the datastream url into component pieces
      if (preg_match("|(https?)://([a-z0-9.]+):([0-9]+)/[^/]+/get/([-a-zA-z:0-9]+)/([a-zA-Z0-9]+)/?(.*)?|", $url, $matches)) {
	list($full_match, $protocol, $hostname, $port, $pid, $datastream, $datetime) = $matches;

	// check url against the configured fedora instance before sending credentials
	if ((($protocol == $fedora_cfg->protocol && $port == $fedora_cfg->port) ||
	     (isset($fedora_cfg->nonssl_port) && $protocol == "http" && $port = $fedora_cfg->nonssl_port))
	    && gethostbyname($hostname) == gethostbyname($fedora_cfg->server)) {

	  // should match either main protocol & port or http & nonssl port (if configured)
	  // hostnames should resolve to the same ip
	  
	  // everything is good - proceed
	  
	} else {	// url doesn't match fedora config
	  throw new Exception("Not configured to access '$url'");
	}
      } else {	// regexp failed
	throw new Exception("Could not parse datastream url '$url'");
      }

      // create a new FedoraConnection using fedora config, but with maintenance account credentials
      $fedora_opts = $fedora_cfg->toArray();
      $fedora_opts["username"] = $fedora_cfg->maintenance_account->username;
      $fedora_opts["password"] = $fedora_cfg->maintenance_account->password;
      $fedora = new FedoraConnection($fedora_opts);
      
      $xml = $fedora->getDatastream($pid, $datastream);
      
      if (!$xml) throw new Exception("No content returned from Fedora for $pid/$datastream");
      
      $dom = new DOMDocument();
      if (! $dom->loadXML($xml))
	throw new Exception("Could not load content as xml");
      
      // NOTE: not using DOMDocument function getElementById because if looks for xml:id only
      $xpath = new DOMXpath($dom);
      $nodelist = $xpath->query("//node()[@id = '$id']");
      // if there were any matches for the xquery, display the xml for the first result
      // (id should only match one)
      if ($nodelist->length) 
	$this->_helper->displayXml($dom->saveXml($nodelist->item(0)));
      else 
	throw new Exception("id '$id' not found");
      
    } catch (Exception $e) {
      $this->_response->setHttpResponseCode(400);	// bad request
      $this->_helper->viewRenderer->setNoRender(true);

      // if debug is turned on, display the error message
      if (Zend_Registry::isRegistered('env-config')) {
	$env = Zend_Registry::get('env-config');
	if ($env->debug || $env->mode == "test")
	  $this->_response->setBody("<p>Error: " . $e->getMessage() . "</p>");
      }
    }
  }
  
  public function aboutAction() {}

}