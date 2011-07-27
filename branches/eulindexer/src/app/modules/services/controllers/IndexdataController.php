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
    
    // Get the ETD content models from config for reindexing
    // Hardcoded example: 
    // array("emory-control:ETD-1.0", "emory-control:EtdFile-1.0", "emory-control:AuthorInformation-1.0")    
    $config = Zend_Registry::get('config');
    $etd_content_models = $config->contentModels->etd;
          
    // Get the solr url from solr config
    // Hardcoded example: 
    // "http://dev11.library.emory.edu:8983/solr/etd/";     
    $solr_config = Zend_Registry::get('solr');
    $solr_url = $solr_config->server . "/" . $solr_config->path . ":" . $solr_config->port;
   
    
    // Index configuation data
    // Hardcoded example:
    // '{
    // "SOLR_URL": "http://dev11.library.emory.edu:8983/solr/smallpox/", 
    // "CONTENT_MODELS": [
    // ["info:fedora/emory-control:ETD-1.0"], 
    // ["info:fedora/emory-control:EtdFile-1.0"], 
    // ["info:fedora/emory-control:AuthorInformation-1.0"]
    // ]}';
    $options = array("SOLR_URL" =>$solr_url, "CONTENT_MODELS" => $etd_content_models);

    echo  Zend_Json::encode($options);
    
  }

  public function aboutAction() {
    $this->_forward("about");    
  }

}
