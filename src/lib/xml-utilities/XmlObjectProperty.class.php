<?php

/**
 * Configuration parameters for a property in an XMLObject object.
 * 
 */
class XmlObjectProperty
{
  /**
   * The name of the property.
   * 
   * For example, if the object is $x, and the property
   * is "title", then using $x->title will return the property value.
   *
   * @var string
   */
  public $name;
  
  /**
   * The XPath in the XML chunk that points to the location of the property value.
   *
   * @var string
   */
  public $xpath;
  
  /**
   * The name of the class to instantiate from the XML found at the xpath.
   *
   * @var string
   */
  public $class_name = null;
  
  /**
   * A flag to indicate whether the xpath could return multiple values.  If set to
   * true, this property will be an array.  If not, it will either be a single value
   * (the first one found) or null (if nothing is found).
   *
   * @var boolean
   */
  public $is_series = false;

  /**
   * Name of array class to use if this property is a series.
   *
   * @var string
   */
  public $array_class = null;

  /**
   * Constructor to set all values at once
   *
   * @param string $name
   * @param array $options
   *  - must include xpath, may include class_name, is_series, and array_class
   */
  public function __construct($name, array $opts) {
    $this->name = $name;
    $this->xpath = $opts["xpath"];
    // optional configuration parameters
    foreach (array("class_name", "is_series", "array_class") as $param) {
      if (isset($opts[$param])) $this->$param = $opts[$param];
    }
  }

}


/**
 * Collection object for configuration properties in an XMLObject object.
 */
class XmlObjectPropertyCollection implements Iterator, ArrayAccess {
  
  protected $properties = array();

  /**
   * merge properties from another XmlObjectPropertyCollection; any
   * values (except for null) in the new set of properties will
   * overwrite any existing ones.
   * @param XmlObjectPropertyCollection $props 
   *
   */
  public function mergeProperties(XmlObjectPropertyCollection $props) {
    foreach ($props as $name => $prop) {
      if (isset($this->properties[$name])) {
	// property already exists - update values
	foreach (array("xpath", "class_name", "is_series", "array_class") as $param) {
	  if ($prop->$param != null) $this->properties[$name]->$param = $prop->$param;
	}
      } else {
	// property does not yet exist
	$this->properties[$name] = $prop;
      }
    }
  }
  
  public function getXpath($key) {
    // if key is not configured, throw an exception
    if (! isset($this->properties[$key])) {
      throw new XmlObjectException("$key is not a defined property");
    }
    return $this->properties[$key]->xpath;
  }

  // iterator functions - expose internal properties as if an  array 
  public function rewind() { reset($this->properties); }
  public function current() { return current($this->properties); }
  public function key() { return key($this->properties); }
  public function next() { return next($this->properties); }
  public function valid() { return $this->current() != false; }
  
  // array access functions
  public function offsetExists($offset) {return isset($this->properties[$offset]); }
  public function offsetGet($offset) { return $this->properties[$offset]; }
  public function offsetSet($offset, $value) { return $this->properties[$offset] = $value; }
  public function offsetUnset($offset) { unset($this->properties[$offset]); }

  
}
