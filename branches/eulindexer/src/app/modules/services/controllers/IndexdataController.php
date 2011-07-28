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

  public function init() {
    $this->_helper->layout->disableLayout();
  }

  public function indexAction() {

    $this->_helper->viewRenderer->setNoRender();    
    
    // Get the ETD content models from config for reindexing
    // Hardcoded example: 
    // (("emory-control:ETD-1.0"), ("emory-control:EtdFile-1.0"))
    $content_models = array();        
    $config = Zend_Registry::get('config');  
    array_push($content_models, array($config->contentModels->etd));   
          
    // Get the solr url from solr config
    // Hardcoded example: 
    // "http://dev11.library.emory.edu:8983/solr/etd/"; 
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
    // Hardcoded example json data returned:
    // '{
    // "SOLR_URL": "http://dev11.library.emory.edu:9083/solr/etd/", 
    // "CONTENT_MODELS": [
    // ["info:fedora/emory-control:ETD-1.0"], 
    // ["info:fedora/emory-control:EtdFile-1.0"], 
    // ["info:fedora/emory-control:AuthorInformation-1.0"]
    // ]}';
    $options = array("SOLR_URL" =>$solr_url, "CONTENT_MODELS" => $content_models);
    // remove escaped slash characters
    echo str_replace("\/", "/", Zend_Json::encode($options));
    $this->getResponse()->setHeader('Content-Type', "application/json");    
  }

  public function aboutAction() {
    $this->_forward("about");    
  }

}
