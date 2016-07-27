<?php

 /**
  * customized router that uses two-letter language code at the beginning of the url
  * Extends default Module route.
  * @author Rebecca Sutton Koeser, July 2008.
  *
  * @category EmoryZF
  * @package Emory_Router
  */
class Emory_Controller_Router_Route_Language extends Zend_Controller_Router_Route_Module {

  /**
   * key for language parameter
   * @var string 
   */
  protected $_langKey = 'language';

  /**
   * default language to use if none is set
   * @var string
   */
  protected $default_language = "en";

  /**
   * current language, based on last url matched
   * @var string
   */
  protected $current_language;

  /**
   * regular expression used to recognize language codes in url
   * @var string
   */
  protected $lang_regexp = "[a-z]{2}";

  /**
   * flag to ignore reset value (only with regards to the language code)
   * @var boolean 
   */
  protected $ignore_reset = false;

  /**
   * wrapper to inherited constructor;
   * removes language key from defaults used by Module router.
   */ 
  public function __construct(array $defaults,
			      Zend_Controller_Dispatcher_Interface $dispatcher = null,
			      Zend_Controller_Request_Abstract $request = null) {
    $this->default_language = $defaults[$this->_langKey];
    $this->current_language = $this->default_language;
    unset($defaults[$this->_langKey]);
    parent::__construct($defaults, $dispatcher, $request);
    unset($this->_defaults[$this->_langKey]);
  }

  /**
   * Ignore reset when assembling urls (only with regard to language code)
   * allows resetting parameters without losing language
   */
  public function ignoreReset() {
    $this->ignore_reset = true;
  }


  /**
   * match two-letter language code at the beginning of a normal MVC Module route
   */
  public function match($path) {
    if (preg_match("|^/(" . $this->lang_regexp . ")/|", $path, $matches)
	// language code by itself with no trailing slash
	|| preg_match("|^/(" . $this->lang_regexp . ")$|", $path, $matches)) {	
      $language = $matches[1];
      $this->current_language = $language;      // store current value

      // remove language from the path: now looks like normal MVC Module route
      $path = preg_replace("|^/(" . $this->lang_regexp . ")(/)?|", "/", $path);
    } else {
      $language = $this->default_language;
    }

    $params = parent::match($path);
    // add language to params determined by Module router
    $params[$this->_langKey] = $language;
    return $params;
  }

  /**
   * build url with language code at the beginning
   */
  public function assemble($data = array(), $reset = false, $encode = false) {

    // parameter passed in by user supercedes any others
    if (isset($data[$this->_langKey])) {
      $language = $data[$this->_langKey];
      // don't pass to parent assembly method (don't duplicate the parameter)
      unset($data[$this->_langKey]);	

      // if reset has been specified and language override has not been set, use default
    } elseif ($reset === true && $this->ignore_reset === false) {
      $language = $this->default_language;
      // use current value
    } else {
      // use the last set language
      $language = $this->current_language;
    }

    $path = parent::assemble($data, $reset, $encode);
    
    $path = $language . "/" . $path;
    return $path;
  }

}
