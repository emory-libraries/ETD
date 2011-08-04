<?php
/**
 * service for providing solr index configuration & data
 * - only allows requests from instance of Fedora this app is configured to talk to
 *
 * @category Etd
 * @package Etd_Services
 * @subpackage Service_Controllers
 */


class Services_IndexdataController extends Zend_Controller_Action {
  protected $etdContentModel;

  public function init() {
          
    $config = Zend_Registry::get('config');
    $this->etdContentModel = $config->contentModels->etd;
        
    $this->_helper->layout->disableLayout();
  }

  public function indexAction() {
    
    // If parameter pid is set, then call indexPid.
    $pid = $this->_getParam('pid', null);    
    if (isset($pid)) {
      $this->indexPid($pid);
      return;   
    }

    // No pid in url, so run the generic indexdata on content model(s)
    $this->_helper->viewRenderer->setNoRender();    
    
    // Get the ETD content models from config for reindexing
    $content_models = array();        
    array_push($content_models, $this->etdContentModel);   
          
    // Get the solr url from solr config
    // Example: "http://dev11.library.emory.edu:8983/solr/etd/"
    $config_dir = Zend_Registry::get("config-dir");
    $env_config = Zend_Registry::get("env-config");    
    $solr_config = new Zend_Config_Xml($config_dir . "solr.xml", $env_config->mode);    
    
    try { // Get the scheme of the url
      $front  = Zend_Controller_Front::getInstance();
      $request = $front->getRequest();
      $scheme = ($request->getServer("HTTPS") == "") ? "http://" : "https://";
    } catch (Exception $e) {  // swallow exception
      $logger->warn("Indexdata service failed to automatically set the scheme the url [" . $e->getMessage() . "]");
    }
    if (!isset($scheme))   $scheme = "http://";
          
    $solr_url = $scheme . $solr_config->server . ":" . $solr_config->port . "/" . $solr_config->path ;
    
    // Index configuation data
    // Example json data returned:
    // '{
    // "SOLR_URL": "http://dev11.library.emory.edu:9083/solr/etd/", 
    // "CONTENT_MODELS": [
    // ["info:fedora/emory-control:ETD-1.0"], 
    // ]}';    
    $options = array("SOLR_URL" =>$solr_url, "CONTENT_MODELS" => $content_models);
    // remove escaped slash characters
    echo str_replace("\/", "/", Zend_Json::encode($options));    
    $this->getResponse()->setHeader('Content-Type', "application/json");    
  }

  public function aboutAction() {
    $this->_forward("about");    
  }
  
  public function indexPid($pid) {
    
    $this->_helper->viewRenderer->setNoRender();
    
    try {
      $etd = new ETD($pid);
      if ($etd->hasContentModel($this->etdContentModel)) {
	$options = $etd->getIndexData($this->etdContentModel);
	// remove escaped slash characters
	echo str_replace("\/", "/", Zend_Json::encode($options)); 
	$this->getResponse()->setHeader('Content-Type', "application/json");
      }
      else {	// return a 404 response \
	echo "404 error incorrect content model\n";          
	$this->_response->setHttpResponseCode(404);    
      } 
    }
    catch (Exception $e) {  
      echo "404 in catch exception\n";    
      $this->_response->setHttpResponseCode(404);      
    }                   
  }  
}
