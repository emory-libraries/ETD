<?php
/**
 * service for fedora dissemination - retrieve a section of an xml datastream by id
 * - only allows requests from instance of Fedora this app is configured to talk to
 *
 * @category Etd
 * @package Etd_Services
 * @subpackage Service_Controllers
 */


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
        
    $url =  $this->_getParam("url");  // fedora datastream url
    $url = urldecode($url);
    $id = $this->_getParam("id");   
    try {
      
      // parse the datastream url into component pieces - expects REST API datastream url
      if (preg_match("|(https?)://([a-z0-9.]+):([0-9]+)/[^/]+/objects/([-a-zA-z:0-9]+)/datastreams/([a-zA-Z0-9]+)/?(.*)?|", $url, $matches)) {
        list($full_match, $protocol, $hostname, $port, $pid, $datastream, $datetime) = $matches;



        // check url against the configured fedora instance before sending credentials
        if (! $this->authorized($hostname, $port)) {
              // should match either main protocol & port or http & nonssl port (if configured)
              // hostnames should resolve to the same ip

              $this->_response->setHttpResponseCode(403);
              $this->_response->setBody("Not configured to access '$url'");

              //throw new Exception("Not configured to access '$url'");
              return;
        }
      

      // create a new FedoraConnection using fedora config, but with maintenance account credentials
      $fedora_opts = $fedora_cfg->toArray();
      $fedora_opts["username"] = $fedora_cfg->maintenance_account->username;
      $fedora_opts["password"] = $fedora_cfg->maintenance_account->password;
      $fedora_opts["server"] = $hostname;
      $fedora_opts["port"] = $port;
      $fedora_opts["protocol"] = $protocol;
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
    } else {	// regexp failed
	throw new Exception("Could not parse datastream url '$url'");
      }
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


  public function authorized($hostname, $port){
      $cfg = Zend_Registry::get('fedora-config');
      
      $portOK=false;
      $hostOK = false;

      //hosts and ports that etd will accept a connection from.
      //Inital values are the from the standard  config fields
      $allowed_ips = array(gethostbyname($cfg->server));
      $allowed_ports = array($cfg->port, $cfg->nonssl_port);

      // if included in configuration, allow these alternate hostnames
    if (isset($cfg->alternate_hosts)) {
      if(is_object($cfg->alternate_hosts->server)){
        foreach ($cfg->alternate_hosts->server->toArray() as $ah){
         $allowed_ips[] = gethostbyname($ah);
        }

      }
      else{
          $allowed_ips[] = gethostbyname($cfg->alternate_hosts->server);
      }
      
    }

    // if included in configuration, allow these alternate ports
    if (isset($cfg->alternate_ports)){
      if(is_object($cfg->alternate_ports->port)){
        foreach ($cfg->alternate_ports->port->toArray() as $ap)
            $allowed_ports[] = $ap;
      }
      else
      $allowed_ports[] = $cfg->alternate_ports->port;
    }


      //test for allowed port
      if(in_array($port, $allowed_ports)){
              $portOK = true;
      }

      //test for allowed hosts
      if(in_array(gethostbyname($hostname), $allowed_ips) || in_array($hostname, $allowed_ips)){
        $hostOK = true;
      }

      return ($hostOK && $portOK);
      
      }
}