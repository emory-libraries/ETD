<?php

/**
 * session handler - save data in an array instead of to the session
 * - used for testing purposes
 * 
 * @category EmoryZF
 * @package Emory_Session
 */
class Emory_Session_SaveHandler_Array implements Zend_Session_SaveHandler_Interface {

  protected $data;

  protected $ns;
  
  protected $mtime;

  public function __construct() {
    $this->data = array();
    $this->mtime = array();
  }

  public function open($save_path, $name) {
    // need to do anything with save path?
    $this->ns = $name;	// name = PHPSESSID .. useful?
    $this->data[$this->ns] = array();
    $this->mtime[$this->ns] = array();
    return true;
  }
    
  public function close() {
    unset($this->data[$this->ns]);
    unset($this->ns);
    return true;
  }
  
  public function read($id) {
    if (isset($this->data[$this->ns][$id]))
      return $this->data[$this->ns][$id];
    else
      return "";
  }
  
  public function write($id, $data) {
    $this->data[$this->ns][$id] = $data;
    $this->mtime[$this->ns][$id] = time();
    return true;
  }
  
  public function destroy($id) {
    unset($this->data[$this->ns][$id]);
    return true;
  }
  
  public function gc($maxlifetime) {
    // ?
    foreach($this->data[$this->ns] as $id) {
      if ($this->mtime[$id] + $maxlifetime < time())
	unset($this->data[$id]);
    }
    return true;
  }

}
										    
