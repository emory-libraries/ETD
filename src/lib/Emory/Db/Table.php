<?

/**
 * extension of Zend DB Table to add magic findBy* functions
 *
 * @category EmoryZF
 * @package Emory_Db
 */
class Emory_DB_Table extends Zend_Db_Table_Abstract {

  /**
   * for magic findBy* functions - converts field name to lowercase by default;
   * set to false to override
   * @var boolean
   */
  protected $lowercase_fields = true;
  protected $uppercase_fields = false;

    /**
     * Magic Methods findBy{Field} and findAllBy{Field}.
     * converts CamelCased field to underscore_notation (eg: FooBar becomes foo_bar)
     * Returns Table_Row or Table_Rowset where db.field = param
     *
     * @param  (mixed) field value
     * @return findByField returns TableRow findAllByField returns TableRowSet
     */
  public function __call($method, $args) {
      if (preg_match('/^find(All)?By([a-zA-Z0-9_]+)$/', $method, $parts)) {
    $field = preg_replace('/([a-z])([A-Z])/', '$1_$2', $parts[2]);
    if ($this->lowercase_fields) $field = strtolower($field);
    if ($this->uppercase_fields) $field = strtoupper($field);
          if (!in_array($field, $this->_getCols())) {
              throw new Zend_Db_Table_Exception(sprintf('\'%s\' field not in row', $field));
          } else {
              $db = $this->getAdapter();
              $where = $db->quoteInto($db->quoteIdentifier($field).' = ?', $args[0]);

              if ($parts[1] == "All")
              {
                return $this->fetchAll($where);
              } else {
                return $this->fetchAll($where)->current();
              }
          }
      } else {
        throw new Zend_Exception(sprintf('\'%s\' method not found', $method));
      }
  }

  /**
   * if only one record is found, return single result instead of table row
   */
  public function find($key)
  {
    $result = parent::find($key);

    if ($result->count() == 0)
      throw new Zend_Db_Table_Exception('record not found');
    elseif ($result->count() == 1) return $result->current();
    else return $result;
  }
}
