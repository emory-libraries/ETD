<?php
require_once("xml_acl.php");

abstract class Etd_Controller_Action extends Zend_Controller_Action {

  protected $debug;
  protected $env;
  protected $requires_fedora = false;

  protected $logger;
  
  public function init() {
    Zend_Controller_Action_HelperBroker::addPath('Emory/Controller/Action/Helper',
						 'Emory_Controller_Action_Helper');
    Zend_Controller_Action_HelperBroker::addPath('Etd/Controller/Action/Helper',
						 'Etd_Controller_Action_Helper');
    
    $this->initView();

    
    $this->debug = Zend_Registry::get('debug');
    $this->view->debug = $this->debug;

    $this->logger = Zend_Registry::get('logger');

    //        $this->acl = $this->view->acl;	// FIXME: which is better? Zend_Registry::get("acl");
    $this->acl = Zend_Registry::get('acl');
    if (Zend_Registry::isRegistered('current_user'))
      $this->current_user = Zend_Registry::get('current_user');
    // no user currently logged in -- leave current_user unset
    //        else $this->current_user = "guest";		

    $this->env = Zend_Registry::get('environment');

    
    // these variables are also needed in the view
    $this->view->acl = $this->acl;
    if (isset($this->current_user)) $this->view->current_user = $this->current_user;
    $this->view->env = $this->env;
    if (isset($_SERVER['HTTP_USER_AGENT'])) $this->view->browserInfo = get_browser();
    $config = Zend_Registry::get('config');
    $this->view->supported_browsers = explode(',', $config->supported_browsers);

    // store controller/action  name in view (needed for certain pages)
    $params =  $this->_getAllParams();
    // (not set when testing)
    if (isset($params['controller'])) $this->view->controller = $params['controller'];
    if (isset($params['action']))  $this->view->action = $params['action'];

    if (isset($params['layout']) && $params['layout'] == "printable")
      $this->_helper->layout->setLayout("printable");
    // by default, pages are not printable (don't need print-view link)
    $this->view->printable = false;

    
    /* if this controller requires fedora and it is not configured (unavailable), redirect to an error page */
    if ($this->requires_fedora && !Zend_Registry::isRegistered('fedora'))
      $this->_helper->redirector->gotoRouteAndExit(array("controller" => "error",
      							 "action" => "fedoraUnavailable"), "", true);

  }

  public function postDispatch() {
    $this->view->messages = $this->_helper->flashMessenger->getMessages();

    /*  For some reason, xforms get loaded twice (FormFaces oddity),
        and on the second load the flash message is no longer current,
        so the message appears briefly and then disappears.

       Re-adding the messages on xforms pages so they will show up on the reload.
       (May result in messages being shown to user twice)     */
    if ($this->view->xforms) {
      foreach ($this->view->messages as $msg) {
	$this->_helper->flashMessenger->addMessage($msg);
      }
    }
    
  }


  /**
   * parse parameters into common filter options needed for facets and
   * passed to Solr or EtdSet find functions
   */
  public function getFilterOptions() {
     $start = $this->_getParam("start", 0);
     $max = $this->_getParam("max", 15);
     
     $sort = $this->_getParam("sort", "author");
     $this->view->sort = $sort;
     // FIXME: modified only relevant for admin views (?)
     $this->view->sort_fields = array("author", "title");
     $this->view->sort_display = array("author" => "author",
				       "title" => "title",
				       "modified" => "last modified",
				       "year"   => "year",
				       "relevance" => "relevance");

    
    // FIXME: include other common filters, like what is repeatedly used for paging?
    //     $options = array("query" => $query, "AND" => $opts, "start" => $start, "max" => $max);
    
    $filter_opts = array();
    foreach (array("status", "committee", "year", "program", "subject", "author", "keyword") as
	     $filter) {
      // only include a filter if the parameter is set and is not blank
      if ($this->_hasParam($filter))
	if ($value = $this->_getParam($filter)) {
	  $filter_opts[$filter] = $value;
	}
    }

    $options = array("start" => $start, "max" => $max,
		     "AND" => $filter_opts, "sort" => $sort);

    // pass filters to the view to display for user
    $this->view->filters = $filter_opts;
    $this->view->url_params = array();		// may be overridden, but should always be set

    return $options;
  }

}

?>
