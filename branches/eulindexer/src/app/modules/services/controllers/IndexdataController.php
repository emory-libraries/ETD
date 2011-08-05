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
  protected $pid;
  
  /**
   * Magic/Missing method to catch all the individual topics so that
   * they can be routed to the topicAction with the subject as a param.
   * @param $name - name of the missing method.
   * @param $arguments - any arguments.
   */  
  public function __call($name, $arguments)
  {
    // Look for url pattern /indexdata/{pid} and set the pid, if recognized
    if (preg_match('@/indexdata/([^/]+/?$)@i', $_SERVER['REQUEST_URI'], $matches)) {
      $this->pid = $matches[1];
      $this->indexPid($this->pid); 	// get the indexdata for this pid 
    }
    else {
      $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "notfound"), "", true);
    }
  } 

  public function init() {
    
    // these next two lines need to be here in order for __call to work
    $this->_helper->layout->disableLayout();    
    $this->_helper->viewRenderer->setNoRender();
 
    $config = Zend_Registry::get('config');
    $this->etdContentModel = $config->contentModels->etd;

  }

  /**
   * Get the index configuration data for the ETD-1.0 content model
   * @return the index configuration data as json.
   */
  public function indexAction() {
    
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

  /**
   * About page for the service indexdata.
   * @return about page for service indexdata.
   */
  public function aboutAction() {
    $this->_forward("about");    
  }
   
  /**
   * Function to provide the data for indexing pid that is a member
   * of ETD content model: ETD-1.0
   * @return the index configuration data for pid as json.
   */   
  public function indexPid() {
    
    try {
      $etd = new ETD($this->pid);
      if ($etd->hasContentModel($this->etdContentModel)) {
	$options = $etd->getIndexData($this->etdContentModel);
	// remove escaped slash characters
	echo str_replace("\/", "/", Zend_Json::encode($options)); 
	$this->getResponse()->setHeader('Content-Type', "application/json");
      }
      else {	// return a 404 response
	$message = "Error: PID=[" . $this->pid . "] is not recognized as a member of Content Model=[" . $this->etdContentModel . "]";
	$this->_helper->flashMessenger->addMessage($message);
	$this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "notfound"), "", true);    
      } 
    }
    catch (Exception $e) {
      $message = "Error: PID=[" . $this->pid . "] is not recognized as a member of Content Model=[" . $this->etdContentModel . "]";
      $this->_helper->flashMessenger->addMessage($message);
      $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "notfound"), "", true);          
    }                   
  } 

}
