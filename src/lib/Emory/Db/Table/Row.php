<?
/**
 * @category EmoryZF
 * @package Emory_Db
 */
class Emory_Db_Table_Row extends Zend_Db_Table_Row {
  /**
   * array mapping ESD db column names to usable names
   * local name => db name
   * @var array
   */
  protected $column_alias;

  public function __construct(array $config = array()) {
    $this->column_alias = array();
    parent::__construct($config);
  }

  /**
   * use aliases to convert access names into db names
   */
  protected function _transformColumn($columnName) {
    if (isset($this->column_alias[$columnName])) return $this->column_alias[$columnName];
    else return parent::_transformColumn($columnName);
  }


  /**
   * include column alias in fields that should be saved for serialization
   * @return array
   */
  public function __sleep() {
    return array_merge(array("column_alias"), parent::__sleep());
  }




}
