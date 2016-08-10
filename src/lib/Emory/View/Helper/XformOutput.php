<?
/**
 * view helper for xform output tag
 *
 * @category EmoryZF
 * @package Emory_View
 * @subpackage Emory_View_Helpers
 */
class Zend_View_Helper_xformOutput {

  public function xformOutput($label, $nodeset) {
    $nodeset = str_replace('"', "'", $nodeset);

    $xform = '<xf:output ref="' . $nodeset . '">
      <xf:label>' . $label . '</xf:label>
   </xf:output>';
    return $xform;
  }
}
