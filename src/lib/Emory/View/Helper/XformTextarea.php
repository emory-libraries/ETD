<?


/**
 * view helper for xform textarea tag
 *
 * @todo FIXME: how to make xforms namespace configurable?
 *
 * @category EmoryZF
 * @package Emory_View
 * @subpackage Emory_View_Helpers
 */
class Zend_View_Helper_xformTextarea {

  public function xformTextarea($label, $nodeset) {
    // make sure nodeset expression uses single quotes (nested inside double quotes)
    $nodeset = str_replace('"', "'", $nodeset);

    $xform = '<xf:textarea ref="' . $nodeset . '">
  <xf:label>' . $label . '</xf:label>
</xf:textarea>';
    return $xform;
  }

}
