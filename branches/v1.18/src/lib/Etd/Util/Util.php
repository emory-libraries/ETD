<?php

/**
   * checks to see if a value is in the config object
   * Will handle mutiple values and single entries
   * @param string $value - value you are looking for
   * @param $entry - entry in the config file, should be either a string or Zend_Config
   * @return boolean
   */
  function valueInConfig($value, $entry){
      //$value must be a non-empty string
      //$entry must be a non-empty string or a Zend_Config object
      if(empty($value) || !is_string($value) || empty($entry) || ( !($entry instanceof Zend_Config) && !is_string($entry))) return false;

      //has mutiple values
      if(is_object($entry)){
          if(in_array($value, $entry->toArray())) return true;
      }
      //single value
      else{
          if($value == $entry) return true;
      }

  //if you get here there is no match
  return false;
  }