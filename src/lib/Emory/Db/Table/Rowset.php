<?
/**
 * @category EmoryZF
 * @package Emory_Db
 */
class Emory_Db_Table_Rowset extends Zend_Db_Table_Rowset
{
  public function __construct($config = null)
  {
    if (is_array($config))
    {
      parent::__construct($config);
    }
  }

  public function add($object)
  {
    array_push($this->_rows, $object);
    $this->_count++;
  }
}
