<?php

class FezUser extends Zend_Db_Table_Row {}

class FezUsers extends Zend_Db_Table_Rowset {}

class FezUserObject extends Zend_Db_Table {
  protected $_name           = 'fez_user';
  protected $_rowsetClass    = 'FezUsers';  
  protected $_rowClass       = 'FezUser';  
  protected $_primary        = 'usr_id';    
}