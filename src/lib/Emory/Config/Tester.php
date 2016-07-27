<?php

require_once("Zend/Config.php");
/**
 * class to test that fields in a config file are set as expected
 *
 * @category EmoryZF
 * @package Emory_Config
 */
class Emory_Config_Tester {

  /**
   * @var Zend_Log $logger log for output that can be filtered
   */
  protected $logger;

  /**
   * @var Zend_Config $config current config to be tested
   */
  public $config;
  /**
   * @var string $shortname short name for current
   */
  public $shortname;

  /**
   * @var array $errcount total # of errors/warnings/etc for current config
   */
  private $errcount;
  
  public function __construct() {
    // setup output/logging
    $writer = new Zend_Log_Writer_Stream("php://output");
    // minimal output format - don't display timestamp or numeric priority
    $format = '%priorityName%: %message%' . PHP_EOL;
    $formatter = new Zend_Log_Formatter_Simple($format);
    $writer->setFormatter($formatter);
    $this->logger = new Zend_Log($writer);
  }

  /**
   * set what level of output should be displayed; defaults to warning
   * @param string log level
   */
  public function log_level($level) {
    switch ($level) {
    case "notice":  $verbosity = Zend_Log::NOTICE; break;
    case "info":    $verbosity = Zend_Log::INFO; break;
    case "debug":   $verbosity = Zend_Log::DEBUG; break;   
    case "error":   $verbosity = Zend_Log::ERR; break; 
    case "warn":    
    default:
      $verbosity = Zend_Log::WARN; break;
    }
    $filter = new Zend_Log_Filter_Priority($verbosity);
    $this->logger->addFilter($filter);
  }


  /**
   * load an xml config file for testing
   * @param string $path path to config
   * @param string $mode mode to use for loading xml config
   */
  public function load_xmlconfig($path, $mode) {
    // blank out any previous tested configuration settings
    $this->config = null;
    $this->shortname = "";
    $this->errcount = array("err" => 0, "warn" => 0, "notice" => 0,
			    "info" => 0, "debug" => 0);
    
    $this->info("** Checking " . basename($path) . " ($mode)");
    try {
      $this->config = new Zend_Config_Xml($path, $mode);
      $this->shortname = basename($path, ".xml");
    } catch (Zend_Config_Exception $e) {
      $this->err("Could not load $path in mode $mode");
      $this->debug($e->getMessage());
    }
  }


  /**
   * internal function for getting a configuration field an arbitrary
   * depth in the configuration tree
   * @param string $path field name of xpath style name, e.g. param/dbname
   * @return config field
   */
  protected function getField($path) {
    $segments = split("/", $path);
    $var = $this->config;
    foreach ($segments as $seg) {
      $var = $var->{$seg};
    }
    return $var;
  }

  /**
   * check that a list of fields are set and not blank
   * @param array $fields flat array of field names,
   * or hash where key is a field and the value is an array of subfields
   * (may use / for more deeply nested fields), or some combination of the two 
   */
  public function check_notblank(array $fields) {
    foreach ($fields as $field => $value) {
      // simple single-dimension array
      if (is_numeric($field)) $this->notblank($value);
      if (is_array($value))
	foreach ($value as $subfield) $this->notblank($field . "/" . $subfield);
    }
  }

  /**
   *  check that specified field is set to one of the specified options
   * @param string $field field name/path
   * @param array $options allowable options for this field
   */
  public function check_oneof($field, array $options) {
    $var = $this->getField($field);
    if (!in_array($var, $options))
      $this->err($this->shortname . " " . $field . " '"
		 . $this->config->{$field} . "' is not a valid option");
  }

  /**
   * compare a field to a regular expression
   * @param string $field field name/path
   * @param string $regexp
   */
  public function check_pattern($field, $regexp) {
    $var = $this->getField($field);
    if (!preg_match($regexp, $var))
      $this->err($this->shortname . "$field '" . $this->config->{$field} .
		 "' does not match expected pattern $regexp");
  }

  /**
   * output a notice if field is not set to recommended value
   * @param string $field field name/path
   */
  public function check_recommended($field, $value) {
    $var = $this->getField($field);
    if ($var != $value)
      $this->notice("Recommended setting for " . $this->shortname .
		    " '$field' is $value");
  }

  

  /**
   * error if config field is not set or if it is blank
   * @param string $field field name/path
   */
  public function notblank($field) {
    $var = $this->getField($field);
    if (! isset($var))
      $this->err($this->shortname . " " . $field . " is not set");
    elseif ($var == "")
      $this->err($this->shortname . " " . $field . " is blank");
  }

  /**
   * error if field cannot be converted to array
   * @param string $field field name/path
   */
  public function plural($field) {
    $var = $this->getField($field);
    // if not an object, we get a fatal error on the toArray call
    if (!is_object($var))
      $this->err($this->shortname .
		 " $field - must be multiple (cannot convert to array)");
    elseif ( !is_array($var->toArray())) 
      $this->err($this->shortname . " $field - cannot convert to error");
  }

  /**
   * warn if field does not match email regexp
   * @param string $field field name/path
   */
  public function email($field) {
    $var = $this->getField($field);
    if (!preg_match("/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i", $var))
      $this->warn($this->shortname . " $field is not a valid email: " . $field);
  }

  /**
   * run checks on a field that contains a filename/path
   * (note: file/dir type checks generate errors, rwx generate warnings)
   * 
   * @param string $field field name/path
   * @param string $mode - some combination of crwxdf
   * to specify which tests should be run of  create/read/write/exec/directory/file
   */
  public function check_file($field, $mode) {
    $filename = $this->getField($field);
    
    $mode = strtolower($mode);
    if ((strpos($mode, "c") !== false)) {
        // if test create specified, use touch to create (if not already existing), then run tests
        if (!touch($filename)) {
            $this->err($this->shortname . " cannot create file '$filename'");
        } 
    }
    if (!file_exists($filename))
      $this->err($this->shortname . " file '$filename' does not exist");
    if ((strpos($mode, "d") !== false) && !is_dir($filename))    
      $this->err($this->shortname . " dir '$filename' is not a directory");
    if ((strpos($mode, "f") !== false) && !is_file($filename))    
      $this->err($this->shortname . " file '$filename' is not a file");
    if ((strpos($mode, "r") !== false) && !is_readable($filename))
      $this->warn($this->shortname . " file '$filename' is not readable");
    if ((strpos($mode, "w") !== false) && !is_writable($filename))    
      $this->warn($this->shortname . " file '$filename' is not writable");
    if ((strpos($mode, "x") !== false) && !is_executable($filename))    
      $this->warn($this->shortname . " file '$filename' is not executable");

    // if test create was specified and file is 0 size, assume created by test (is this safe/reasonable?)
    if ((strpos($mode, "c") !== false) && filesize($filename) == 0) unlink($filename);
  }

  /**
   * output whether current config ok/not ok based any errors or warnings found
   */
  public function ok() {
    if (($this->errcount["err"] + $this->errcount["warn"]) == 0)
      print($this->shortname . " OK\n");
    else
      print($this->shortname . " may have problems (" .
	    $this->errcount["err"] . " errors, " .
	    $this->errcount["warn"] . " warnings)\n");
  }

  /**
   * wrapper around logger functions so that errors can be counted
   */
  public function __call($name, $args) {
    if (in_array($name, array("err", "warn", "notice", "info", "debug"))) {
      $this->logger->$name($args[0]);
      // count # of errors for reporting purposes
      $this->errcount[$name]++;
    } else {
      trigger_error("function '$name' not implemented", E_USER_ERROR);
    }
  }
  

}