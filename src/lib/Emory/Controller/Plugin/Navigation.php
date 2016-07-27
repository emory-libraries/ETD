<?php

/**
 * front controller plugin to initialize navigation via controllers themselves
 * - caches data needed to initialize Zend_Navigation so it doesn't
 *   need to be regenerated for every page load
 * - scans controller directories and includes them using require_once
 *   (otherwise they do not get autoloaded properly before dispatch)
 * - calls static getNav function of default controller (e.g., IndexController)
 *
 * @author Rebecca Sutton Koeser, November 2009
 */
class Emory_Controller_Plugin_Navigation extends Zend_Controller_Plugin_Abstract {


  /**
   * Array used to set the location of the cache file along with the file prefix
   * for the cache file's name.
   * @var array
   */
  protected $_cacheOptions = array('cache_dir' => "/tmp/",
				      "file_name_prefix" => "GHC_cache");

   /**
   * Array used to set the duration of the cache's life
   */
   protected $_cacheDuration = array('lifetime' => 3600);
  
   /**
   * Constructor
   *
   * Options may include:
   * - cache_dir
   * - file_name_prefix
   * - lifetime
   *
   * @param  Array $options
   * @return void
   */
  public function __construct(Array $options = array())
  {
    $this->setCacheOptions($options);
  }  

  public function setCacheOptions(Array $options = array())
  {
    
    if (isset($options['cache_dir'])) 
        $this->_cacheOptions['cache_dir'] = $options['cache_dir']; 
    
    
    if (isset($options['cache_dir'])) 
	$this->_cacheOptions["file_name_prefix"] = $options['file_name_prefix'];
 
    if (isset($options['lifetime']))
      $this->_cacheDuration['lifetime'] = $options['lifetime']; 
  }

  

  public function dispatchLoopStartup(Zend_Controller_Request_Abstract $request) {
    // initialize navigation based on controllers
    
     $cache = Zend_Cache::factory('Output', 'File',
				 array('lifetime' => $this->_cacheDuration['lifetime'], "automatic_serialization" => true),
				 $this->_cacheOptions);
    
      if (!($navinfo = $cache->load('zend_navigation'))) {
	  
      $front = Zend_Controller_Front::getInstance();
      
      // associative array of module => controller dir
      $controller_dirs = $front->getControllerDirectory();
      $default_controller = $front->getDefaultControllerName();
      
      // loop through controller directories and include all controllers
      foreach ($controller_dirs as $module => $dir) {
	$files = scandir($dir);
	foreach ($files as $file) {
	  if (preg_match("/^[A-Za-z].*Controller.php$/", $file)) {
	    require_once($dir . "/" . $file);
	  }
	}
      }
      
      // call static function getNav of the default controller
      $index_controller = ucfirst($default_controller) . "Controller";
      $navinfo = call_user_func(array($index_controller, "getNav"));
      
      $cache->save($navinfo);
    }
    
    $nav = new Zend_Navigation($navinfo);
    Zend_Registry::set('Zend_Navigation', $nav);
  }
}
