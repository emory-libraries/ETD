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
  
  /**
   * Content Model of ETD
   * @var string 
   */
  public $etdContentModel;
  
  /**
   * PID to be indexed.
   * @var string 
   */  
  public $pid;
  
  /**
   * copy of fedoraConnection with current user's auth credentials
   * (to be restored in postDispatch)
   * @var FedoraConnection
   */
  protected $_fedoraConnection;  
  
  /**
   * Overriding fedora connection with a connection that uses
   * maintenance account credentials.
   * Note: this will be done before all actions in this
   * controller, and the default fedora connection will restored
   * by postDispatch.
   */
  public function preDispatch() {
    
    $logger = Zend_Registry::get('logger');    
    
    // store fedoraConnection with user auth credentials - to be restored in postDispatch
    $this->_fedoraConnection = Zend_Registry::get("fedora");
    
    $fedora_cfg = Zend_Registry::get('fedora-config');
    try {
      $fedora_opts = $fedora_cfg->toArray();
      // use default fedora config opts but with maintenance account credentials
      $fedora_opts["username"] = $fedora_cfg->maintenance_account->username;
      $fedora_opts["password"] = $fedora_cfg->maintenance_account->password;
      $maintenance_fedora = new FedoraConnection($fedora_opts);
    } catch (FedoraNotAvailable $e) {
      $this->logger->err("Error connecting to Fedora with maintenance account - " . $e->getMessage());
      $this->_forward("fedoraunavailable", "error"); 
    } 
    Zend_Registry::set("fedora", $maintenance_fedora);
    
    // these next two lines need to be here in order for __call to work
    $this->_helper->layout->disableLayout();    
    $this->_helper->viewRenderer->setNoRender();
 
    $config = Zend_Registry::get('config');
    $this->etdContentModel = $config->contentModels->etd;
  }  
  
  /**
   * restore fedoraConnection with currently-logged in user's credentials
   */
  public function postDispatch() {
    Zend_Registry::set("fedora", $this->_fedoraConnection);  
  }  
  
  /**
   * Magic/Missing method to catch all the individual topics so that
   * they can be routed to the topicAction with the subject as a param.
   * @param $name - name of the missing method.
   * @param $arguments - any arguments.
   */  
  public function __call($name, $arguments)
  {
    // Look for url pattern /indexdata/{pid} and set the pid, if recognized
    if (preg_match('@/indexdata/([^/]+)/?$@i', $_SERVER['REQUEST_URI'], $matches)) {
      $this->pid = $matches[1];
      $this->indexPid($this->pid); 	// get the indexdata for this pid 
    }
    else {
      //$this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "notfound"), "", true);
    }
  } 

  /**
   * Get the index configuration data for the ETD-1.0 content model
   * @return the index configuration data as json.
   */
  public function indexAction() {
    
    // Get the ETD content models from config for reindexing
    $content_models = array();        
    array_push($content_models, array("info:fedora/" . $this->etdContentModel));   
          
    // Get the solr url from solr config
    // Example: "http://dev11.library.emory.edu:8983/solr/etd/"
    $config_dir = Zend_Registry::get("config-dir");
    $env_config = Zend_Registry::get("env-config");    
    $solr_config = new Zend_Config_Xml($config_dir . "solr.xml", $env_config->mode);           
    $solr_url = "http://" . $solr_config->server . ":" . $solr_config->port . "/" . $solr_config->path ;
    
    // Index configuation data
    // Example json data returned:
    // '{
    // "SOLR_URL": "http://dev11.library.emory.edu:9083/solr/etd/", 
    // "CONTENT_MODELS": [
    // ["info:fedora/emory-control:ETD-1.0"], 
    // ]}';    
    $options = array("SOLR_URL" =>$solr_url, "CONTENT_MODELS" => $content_models);
    $this->getResponse()->setHeader('Content-Type', "application/json");
    $this->getResponse()->setBody(Zend_Json::encode($options));
  }

  /**
   * About page for the service indexdata.
   * @return about page for service indexdata.
   */
  public function aboutAction() {
    $this->_helper->viewRenderer->setNoRender(false); 
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
	$this->getResponse()->setHeader('Content-Type', "application/json");
	$this->getResponse()->setBody(Zend_Json::encode($etd->getIndexData()));	
      }
      return;
    }
    catch (Exception $e) { }  // fall through to 404 error  
    
    // Display the 404 error
    $message = "Error: Object (" . $this->pid . ") does not appear to be an ETD object (does not have content model : " . $this->etdContentModel . ")";
    $this->_helper->flashMessenger->addMessage($message);
    $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error", "action" => "notfound"), "", true);                        
    $this->_response->setHttpResponseCode(404);	// bad request
  } 

}
