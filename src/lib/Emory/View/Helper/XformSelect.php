<?
/**
 * view helper for xform select tag
 *
 * @category EmoryZF
 * @package Emory_View
 * @subpackage Emory_View_Helpers
 */
class Zend_View_Helper_xformSelect {

  public function xformSelect($label, $nodeset, $select_options, $options = null, $action = "") {
    //$multiple = false,
    //			      $appearance = null) {
    // make sure nodeset expression uses single quotes (nested inside double quotes)
    $nodeset = str_replace('"', "'", $nodeset);

    // multiple or single select
    if (isset($options['multiple']) && $options['multiple'])
      $select = "select";
    else $select = "select1";

    $xform = '<xf:' . $select . ' ref="' . $nodeset . '"';
    if (isset($options['appearance']))
      $xform .= ' appearance="' . $options['appearance'] . '" ';
    $xform .= '>
   <xf:label>' . $label . '</xf:label>
';

    foreach ($select_options as $opt_value => $opt_label) {
      $xform .= "<xf:item>
  <xf:label>" . $opt_label . "</xf:label>
  <xf:value>" . $opt_value . "</xf:value>
</xf:item>
";
    }
    // any binding or trigger attached to this object
    $xform .= $action;

    $xform .= "</xf:" . $select . ">";

    return $xform;
  }

}
