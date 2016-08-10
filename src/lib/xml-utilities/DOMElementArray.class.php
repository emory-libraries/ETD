<?php

/**
 * Array of DOMElements to allow string read/write/append access 
 *
 * When accessed (read or write) via array[n], can be treated as a string.
 * Can use [] to append DOMElement or string (which is converted to
 * DOMElement using class append function).
 * Can use foreach but for read access ONLY.
 *
 * Array elements are expected to be of type DOMElement, but type is
 * not actually checked.  If other types are used, expect this class to behave strangely.
 *
 */
class DOMElementArray extends ArrayObject implements Iterator {

  /**
   * return text value of the node instead the node itself when
   * element is accesed by index
   *
   * @param integer index
   */
  public function offsetGet($index) {
    $obj = parent::offsetGet($index);
    return $this->get($obj);
  }

  /**
   * set node value by text when element is accessed by index
   * 
   * @param integer index
   * @param string $value
   */
  public function offsetSet($index, $value) {
    // note: using array[] calls offsetSet *without* an index value
    
    if (is_numeric($index)) {    // if index is a number, set the value
      $obj = parent::offsetGet($index);
      return $this->set($obj, $value);
    } elseif (is_string($value)) {  // if value is a plain string, append as a dom element
      return $this->append($value);
    }
    
    // otherwise, handle as a regular array
    return parent::offsetSet($index, $value);
  }

  /* get and set functions - separate for extensibility */

  /* get the value of an array member (dom element) */
  protected function get($var) {
    if ($var instanceof DOMElement || $var instanceof DOMAttr)
      return $var->nodeValue;
    else
      return $var;
  }

  /* set the value of an array member */
  protected function set($var, $value) {
    return $var->nodeValue = $value;
  }

  /**
   * create a new DOMElement based on the first in this array, append
   * it to the owner document, and set node value by text
   *
   * @param string $value
   */
  public function append($value) {
    $obj = parent::offsetGet(0);
    
    // If the obj is not set, then throw an meaningful exception.
    if (!isset($obj)) { 
      $traces = debug_backtrace();
      $msg = "The object is not set in class=[DOMElementArray] function=[append] ";
      if (isset($traces[2]))
        $msg = $msg . "called from class=[" . $traces[2]['class'] . "] and function=[" . $traces[2]['function'] . "].";
      throw new Exception($msg);
    }
        
    // create a new node with $value as text, and append it to the DOM
    
    // base new member on a clone of the first element in this array
    $el = $obj->cloneNode();
    $el->nodeValue = $value;

    // get the last element in the array (could also be the first)
    $lastobj = parent::offsetGet(count($this)-1);
    if ($lastobj->nextSibling) {
      // if there is a next sibling, use that as a reference to insert new element
      $el = $obj->parentNode->insertBefore($el, $lastobj->nextSibling);
    } else {
      // otherwise, append to parent of first element
      $el = $obj->parentNode->appendChild($el);
      // **Note: depending on xml structure, new node may not appear
      // after other series members
    }
    
    // append new DOMElement to array normally
    return parent::append($el);
  }

  // iterator functions, to allow accessing with foreach (writing to foreach doesn't work)
  public function rewind() {
    return reset($this);
  }
  public function current() {
    return $this->get(current($this));
  }
  public function key() { // numerical index (named key doesn't make sense here)
    return key($this);
  }
  public function next() {
    return $this->get(next($this));
  }
  public function valid() {
    return (current($this) instanceof DOMElement);
  }



  // because this is not a true array, you can't use in_array function - this is a replacement
  public function includes($value) {
    for ($i = 0; $i < count($this); $i++) {
      if ($this->offsetGet($i) == $value)
  return true;
    }
    // went through the array and didn't find it
    return false;
  }

  // FIXME: would it be better to have a "to_array" function that
  // would allow the use of any normal array functions?

 
}

?>
